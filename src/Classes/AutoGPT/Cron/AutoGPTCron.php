<?php

declare(strict_types=1);
/**
 * AutoGPT Cron — picks due tasks, dispatches to processor, logs result.
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT\Cron
 */

namespace Linked3\Classes\AutoGPT\Cron;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}

use Linked3\Classes\AutoGPT\{AutoGPTTaskRepository, Processors\AutoGPTProcessorFactory};
final class AutoGPTCron
{
    /** Constitution: a single cron tick must never exceed this wall-clock
     *  budget (set_time_limit(300) is the hard PHP ceiling; we leave 60s
     *  headroom for shutdown + mark_run + log writes so the tick ends
     *  cleanly before PHP kills it mid-AI-call). */
    const TICK_BUDGET_SECONDS = 240;

    /** Consecutive failures before a task is auto-paused (circuit breaker). */
    const FAILURE_THRESHOLD = 3;

    public static function init()
    : void {
        add_action('linked3_autogpt_run', [__CLASS__, 'run']);
    }

    /**
     * Main cron tick — process all due tasks.
     *
     * @return void
     */
    public static function run()
    : void {
        set_time_limit(300);

        // 时间段限制 (原版隐藏功能)
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $settings = $enhancer->get_settings();
            if (!$enhancer->is_within_time_window($settings)) {
                return; // 不在时间段内,跳过本次执行
            }
        }

        $started = time();

        self::process_due_tasks($started);

        // 2) Drain the task_queue: retry any pending items whose scheduled_for
        //    has arrived. This is the async-retry path for failed publish / AI
        //    calls that were enqueued by processors.
        self::drain_task_queue($started);
    }

    /**
     * Process all due tasks subject to concurrency + wall-clock budget.
     *
     * @param int $started  Timestamp when the tick started.
     * @return void
     */
    private static function process_due_tasks(int $started): void
    {
        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
        $log  = Logger::instance();
        $due  = $repo->get_due_tasks();
        $log->info('cron', sprintf('AutoGPT: %d due tasks', count($due)));

        $plan         = \Linked3\Classes\License\LicenseService::instance()->plan();
        $concurrency  = ['free' => 1, 'pro' => 3, 'premium' => 10][$plan] ?? 1;
        $run          = 0;

        foreach ($due as $task) {
            if ((time() - $started) > self::TICK_BUDGET_SECONDS) {
                $log->warning('cron', sprintf(
                    'AutoGPT tick budget %ds exceeded after %d/%d tasks — remaining deferred to next tick',
                    self::TICK_BUDGET_SECONDS, $run, count($due)
                ));
                break;
            }
            if ($run >= $concurrency) break;

            $task['config'] = json_decode($task['config'], true) ?: [];
            $cfg = $task['config'];

            if (!self::is_within_smart_schedule($cfg)) {
                $log->info('cron', sprintf('Task #%d skipped: not in smart schedule window', $task['id']));
                continue;
            }

            $processor = AutoGPTProcessorFactory::make($task['task_type']);
            if (!$processor) {
                $repo->mark_run($task['id'], 'error');
                $log->error('cron', "No processor for task_type={$task['task_type']}", ['task_id' => $task['id']]);
                self::trip_breaker($repo, $task, sprintf('No processor for type %s', $task['task_type']));
                $run++;
                continue;
            }

            $ok      = false;
            $message = '';
            try {
                $result  = $processor->process($task);
                $ok      = !empty($result['ok']);
                $message = $result['message'] ?? '';
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $log->error('cron', "Task #{$task['id']} exception: " . $message);
            }

            $repo->mark_run($task['id'], $ok ? 'success' : 'error');
            $log->info('cron', sprintf(
                'Task #%d (%s) %s: %s',
                $task['id'], $task['task_type'], $ok ? 'ok' : 'FAIL', $message
            ));

            if ($ok) {
                delete_transient('linked3_agpt_fail_' . $task['id']);
                delete_option(LINKED3_OPTION_PREFIX . 'agpt_fail_' . $task['id']);
            } else {
                self::trip_breaker($repo, $task, $message);
            }
            $run++;
        }
    }

    /**
     * Drain the async-retry queue: process pending items whose scheduled_for has arrived.
     *
     * @param int $started  Timestamp when the tick started.
     * @return void
     */
    private static function drain_task_queue(int $started): void
    {
        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
        $queue_items = $repo->dequeue(5);

        foreach ($queue_items as $item) {
            if ((time() - $started) > self::TICK_BUDGET_SECONDS) break;

            $payload      = $item['payload'] ?? [];
            $payload_type = $payload['type'] ?? '';

            if ((int) $item['task_id'] === 0 && in_array($payload_type, ['distribute_retry', 'publish_retry'], true)) {
                self::process_standalone_retry($item);
                continue;
            }

            $task = $repo->get((int) $item['task_id']);
            if (!$task || $task['status'] !== 'active') {
                $repo->mark_queue_done($item['id'], 'skipped', __('Task inactive.', 'linked3'));
                continue;
            }
            $task['config'] = json_decode($task['config'], true) ?: [];
            if (!empty($payload) && is_array($payload)) {
                $task['config'] = array_merge($task['config'], $payload);
            }
            $processor = \Linked3\Classes\AutoGPT\Processors\AutoGPTProcessorFactory::make($task['task_type']);
            if (!$processor) {
                $repo->mark_queue_done($item['id'], 'error', __('无处理器。', 'linked3'));
                continue;
            }
            $repo->mark_queue_done($item['id'], 'processing', '');
            try {
                $r = $processor->process($task);
                $repo->mark_queue_done($item['id'], $r['ok'] ? 'done' : 'error', $r['message']);
            } catch (\Throwable $e) {
                $repo->mark_queue_done($item['id'], 'error', $e->getMessage());
            }
        }
    }

    /**
     * v3.0.0: 处理独立的重试队列项 (task_id=0)。
     * 支持 distribute_retry / publish_retry 两种类型。
     */
    private static function process_standalone_retry(array $item)
    : void {
        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
        $log = Logger::instance();
        $payload = $item['payload'] ?? [];
        $type = $payload['type'] ?? '';

        // 检查重试次数 (最多 3 次)
        if ((int) $item['attempts'] >= 3) {
            $repo->mark_queue_done($item['id'], 'failed', __('已超过最大重试次数。', 'linked3'));
            $log->warning('cron', "Queue item #{$item['id']} ({$type}) exhausted retries");
            return;
        }

        $repo->mark_queue_done($item['id'], 'processing', '');
        try {
            if ($type === 'distribute_retry') {
                $post_id = (int) ($payload['post_id'] ?? 0);
                $platform = $payload['platform'] ?? '';
                if ($post_id && $platform) {
                    $mgr = \Linked3\Classes\Distribute\DistributeManager::instance();
                    $results = $mgr->distribute_post_to_platforms($post_id, [$platform]);
                    $r = $results[0] ?? ['ok' => false, 'message' => 'no result'];
                    $repo->mark_queue_done($item['id'], $r['ok'] ? 'done' : 'error', $r['message']);
                    return;
                }
            } elseif ($type === 'publish_retry') {
                $target_id = (int) ($payload['target_id'] ?? 0);
                $post_data = $payload['post_data'] ?? [];
                $user_id = (int) ($payload['user_id'] ?? get_current_user_id());
                if ($target_id && !empty($post_data)) {
                    $r = \Linked3\Classes\Publish\PublishManager::instance()->publish_to_target($target_id, $user_id, $post_data);
                    if (is_wp_error($r)) {
                        $repo->mark_queue_done($item['id'], 'error', $r->get_error_message());
                    } else {
                        $repo->mark_queue_done($item['id'], 'done', 'ok');
                    }
                    return;
                }
            }
            $repo->mark_queue_done($item['id'], 'error', __('未识别的重试类型。', 'linked3'));
        } catch (\Throwable $e) {
            $repo->mark_queue_done($item['id'], 'error', $e->getMessage());
        }
    }

    /**
     * v3.0.0: smart schedule 检查 — 时间窗 + 精确到分钟。
     *
     * @param array $cfg task config
     * @return bool 是否在允许的执行窗口内
     */
    private static function is_within_smart_schedule(array $cfg)
    : bool {
        $now = current_time('H:i');

        // 精确到分钟: 如 "09:30" 只在 09:30-09:39 之间执行 (cron 是 10min 粒度)
        $specific_time = $cfg['publish_at_specific_time'] ?? '';
        if ($specific_time) {
            $target_hour = (int) substr($specific_time, 0, 2);
            $now_hour = (int) substr($now, 0, 2);
            if ($target_hour !== $now_hour) return false;
            // 在目标小时内的任意 10 分钟 tick 都允许 (cron 粒度限制)
        }

        // 时间窗: 如 "09:00-12:00,14:00-18:00"
        $window = $cfg['publish_time_window'] ?? '';
        if ($window) {
            $windows = explode(',', $window);
            $in_window = false;
            foreach ($windows as $w) {
                $w = trim($w);
                if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $w, $m)) {
                    if ($now >= $m[1] && $now <= $m[2]) {
                        $in_window = true;
                        break;
                    }
                }
            }
            if (!$in_window) return false;
        }

        return true;
    }

    /**
     * Circuit-breaker helper: advance the consecutive-failure counter for a
     * task and auto-pause + email-alert when the threshold is reached.
     *
     * @param AutoGPTTaskRepository $repo
     * @param array                           $task
     * @param string                          $message
     * @return void
     */
    private static function trip_breaker(AutoGPTTaskRepository $repo, array $task, string $message)
    : void {
        $log = Logger::instance();
        // Persist the consecutive-failure count in an option (not transient)
        // so it survives cache eviction / object-cache flush. The counter is
        // reset to 0 on any successful run (see run()).
        $opt_key = LINKED3_OPTION_PREFIX . 'agpt_fail_' . $task['id'];
        $data = get_option($opt_key, ['count' => 0, 'first_at' => 0, 'last_message' => '']);
        $consec = (int) ($data['count'] ?? 0) + 1;
        $first_at = (int) ($data['first_at'] ?? 0);
        if ($first_at === 0) $first_at = time();
        update_option($opt_key, [
            'count' => $consec,
            'first_at' => $first_at,
            'last_message' => substr((string) $message, 0, 255),
        ], false);

        if ($consec >= self::FAILURE_THRESHOLD) {
            $repo->update_status($task['id'], $task['user_id'], 'paused');
            $log->critical('cron', sprintf(
                'Task #%d (%s) auto-paused after %d consecutive failures: %s',
                $task['id'], $task['name'] ?? '', $consec, $message
            ));
            // Email alert — best-effort, never block the tick on mail failure.
            $subject = sprintf(__('[Linked3] AutoGPT task "%s" paused', 'linked3'), $task['name'] ?? ('#' . $task['id']));
            $body = sprintf(
                __("The AutoGPT task \"%s\" (type: %s, ID: %d) was auto-paused after %d consecutive failures.\n\nLast error: %s\n\nPlease review the task configuration in the WordPress admin.", 'linked3'),
                $task['name'] ?? '', $task['task_type'] ?? '', $task['id'], $consec, $message
            );
            @wp_mail(get_option('admin_email'), $subject, $body);
        }
    }
}
