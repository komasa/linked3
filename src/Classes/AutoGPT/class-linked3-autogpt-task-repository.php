<?php
/**
 * AutoGPT Task Repository — CRUD on linked3_tasks + linked3_task_queue.
 *
 * v4.5.4: now extends Linked3_Base_Repository for the linked3_tasks table.
 * Queue methods (enqueue/dequeue/mark_queue_done) operate on
 * linked3_task_queue and use $wpdb directly (a second repository could
 * be created for the queue table in the future).
 *
 * Task types: content-writing / content-enhancement / content-indexing / comment-reply / collect-rewrite
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT
 */

namespace Linked3\Classes\AutoGPT;

use Linked3\Includes\DB\Linked3_Base_Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_AutoGPT_Task_Repository extends Linked3_Base_Repository
{
    const VALID_TYPES = ['content-writing', 'content-enhancement', 'content-indexing', 'comment-reply', 'collect-rewrite'];
    const VALID_STATUS = ['active', 'paused', 'deleted'];

    /**
     * {@inheritdoc}
     */
    protected function table_name(): string
    {
        return 'linked3_tasks';
    }

    /**
     * {@inheritdoc}
     */
    protected function primary_key(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    protected function fillable(): array
    {
        return ['user_id', 'task_type', 'name', 'config', 'status',
                'next_run_time', 'last_run_time', 'last_run_status', 'run_count'];
    }

    /**
     * @return string The queue table name (prefixed).
     */
    private function queue_table(): string
    {
        return $this->wpdb->prefix . 'linked3_task_queue';
    }

    /**
     * 按任务类型获取默认频率 (v2.5.0)。
     */
    public static function default_frequency_for_type($type) : mixed {
        $defaults = [
            'content-writing'     => 'hourly',
            'content-enhancement' => 'daily',
            'content-indexing'    => 'linked3_every_10min',
            'comment-reply'       => 'hourly',
        ];
        return $defaults[$type] ?? 'hourly';
    }

    public function all($user_id = 0) : mixed     {
        global $wpdb;
        $table = $this->get_table();
        if ($user_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND status != 'deleted' ORDER BY id DESC",
                $user_id
            ), ARRAY_A) ?: [];
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status != %s ORDER BY id DESC", "deleted"), ARRAY_A) ?: [];
    }

    public function get($id, $user_id = 0) : mixed {
        global $wpdb;
        $table = $this->get_table();
        if ($user_id) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                $id, $user_id
            ), ARRAY_A);
        } else {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        }
        if ($row) {
            $row['config'] = json_decode($row['config'], true) ?: [];
        }
        return $row;
    }

    public function create(array $data) : mixed     {
        global $wpdb;
        $table = $this->get_table();
        $type = sanitize_text_field($data['task_type'] ?? 'content-writing');
        if (!in_array($type, self::VALID_TYPES, true)) {
            return new \WP_Error('bad_type', __('无效的任务类型。', 'linked3'));
        }
        // Plan limit check.
        $user_id = (int) ($data['user_id'] ?? get_current_user_id());
        $plan = \Linked3\Classes\License\LicenseService::instance()->plan();
        $limits = ['free' => 1, 'pro' => 5, 'premium' => -1];
        $limit = $limits[$plan] ?? 1;
        if ($limit >= 0) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = 'active'",
                $user_id
            ));
            if ($count >= $limit) {
                return new \WP_Error('plan_limit', sprintf(__('当前套餐允许 %d 个活跃 Agent。', 'linked3'), $limit));
            }
        }

        $config = $this->sanitize_config($data['config'] ?? [], $type);
        $next_run = $this->compute_next_run($data['schedule'] ?? 'hourly');
        $id = parent::insert_row([
            'user_id'       => $user_id,
            'task_type'     => $type,
            'name'          => sanitize_text_field($data['name'] ?? ''),
            'config'        => wp_json_encode($config),
            'status'        => 'active',
            'next_run_time' => $next_run,
        ]);
        if ($id === null) {
            return new \WP_Error('db', $wpdb->last_error);
        }
        return $id;
    }

    public function update_status($id, $user_id, $status)
    {
        global $wpdb;
        $table = $this->get_table();
        if (!in_array($status, self::VALID_STATUS, true)) {
            return false;
        }
        return (bool) $wpdb->update($table, ['status' => $status], ['id' => $id, 'user_id' => $user_id], ['%s'], ['%d', '%d']);
    }

    public function delete($id, $user_id)
    {
        return $this->update_status($id, $user_id, 'deleted');
    }

    public function mark_run($id, $status)
    : void {
        global $wpdb;
        $table = $this->get_table();
        $next = $this->compute_next_run($this->get_schedule($id));
        // v0.8.0 fix: single atomic UPDATE that increments run_count.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET last_run_time = %s, last_run_status = %s, run_count = run_count + 1, next_run_time = %s WHERE id = %d",
            current_time('mysql'),
            sanitize_text_field($status),
            $next,
            (int) $id
        ));
    }

    public function get_schedule($id)
    {
        $task = $this->get($id);
        return $task['config']['schedule'] ?? 'hourly';
    }

    /**
     * @return array Tasks due to run now.
     */
    public function get_due_tasks()
    {
        global $wpdb;
        $table = $this->get_table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'active' AND next_run_time IS NOT NULL AND next_run_time <= %s",
            current_time('mysql', true)
        ), ARRAY_A) ?: [];
    }

    // ----- Queue -----

    public function enqueue($task_id, array $payload, $scheduled_for = null)
    {
        global $wpdb;
        $table = $this->queue_table();
        $wpdb->insert($table, [
            'task_id'      => (int) $task_id,
            'payload'      => wp_json_encode($payload),
            'status'       => 'pending',
            'scheduled_for' => $scheduled_for ? sanitize_text_field($scheduled_for) : current_time('mysql'),
        ], ['%d', '%s', '%s', '%s']);
        return (int) $wpdb->insert_id;
    }

    public function dequeue($limit = 5)
    {
        global $wpdb;
        $table = $this->queue_table();
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_for <= %s ORDER BY added_at ASC LIMIT %d",
            current_time('mysql'), $limit
        ), ARRAY_A);
        foreach ($items as &$item) {
            $item['payload'] = json_decode($item['payload'], true) ?: [];
        }
        return $items;
    }

    public function mark_queue_done($queue_id, $status, $message = '')
    : void {
        global $wpdb;
        $table = $this->queue_table();
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, attempts = attempts + 1, last_attempt_time = %s, error_message = %s WHERE id = %d",
            sanitize_text_field($status),
            current_time('mysql'),
            substr($message, 0, 65535),
            (int) $queue_id
        ));
    }

    private function compute_next_run($schedule)
    {
        $intervals = [
            'every_10min' => 10 * MINUTE_IN_SECONDS,
            'hourly'      => HOUR_IN_SECONDS,
            'daily'       => DAY_IN_SECONDS,
            'weekly'      => 7 * DAY_IN_SECONDS,
        ];
        $gap = $intervals[$schedule] ?? HOUR_IN_SECONDS;
        return gmdate('Y-m-d H:i:s', time() + $gap);
    }

    private function sanitize_config(array $cfg, $type)
    {
        $cfg['schedule'] = sanitize_text_field($cfg['schedule'] ?? 'hourly');
        $cfg['max_attempts'] = max(1, min(5, (int) ($cfg['max_attempts'] ?? 3)));
        $cfg['publish_time_window'] = sanitize_text_field($cfg['publish_time_window'] ?? '');
        $cfg['publish_at_specific_time'] = sanitize_text_field($cfg['publish_at_specific_time'] ?? '');
        $cfg['distribute_platforms'] = isset($cfg['distribute_platforms']) && is_array($cfg['distribute_platforms'])
            ? array_map('sanitize_key', $cfg['distribute_platforms']) : [];
        switch ($type) {
            case 'content-writing':
                $cfg['keyword'] = sanitize_text_field($cfg['keyword'] ?? '');
                $cfg['template_id'] = (int) ($cfg['template_id'] ?? 0);
                $cfg['publish_target_id'] = (int) ($cfg['publish_target_id'] ?? 0);
                $cfg['count_per_run'] = max(1, min(10, (int) ($cfg['count_per_run'] ?? 1)));
                $cfg['publish_directly'] = !empty($cfg['publish_directly']);
                $cfg['inject_images'] = !empty($cfg['inject_images']);
                break;
            case 'content-enhancement':
                $cfg['min_score'] = (int) ($cfg['min_score'] ?? 60);
                $cfg['max_per_run'] = max(1, min(20, (int) ($cfg['max_per_run'] ?? 5)));
                break;
            case 'content-indexing':
                $cfg['batch_size'] = max(10, min(500, (int) ($cfg['batch_size'] ?? 100)));
                break;
            case 'comment-reply':
                $cfg['sentiment_filter'] = sanitize_text_field($cfg['sentiment_filter'] ?? 'all');
                break;
            case 'collect-rewrite':
                $cfg['urls'] = sanitize_textarea_field($cfg['urls'] ?? '');
                $cfg['tone'] = sanitize_text_field($cfg['tone'] ?? 'professional');
                $cfg['complexity'] = sanitize_text_field($cfg['complexity'] ?? 'intermediate');
                $cfg['seo_focus'] = !empty($cfg['seo_focus']);
                $cfg['simplify'] = !empty($cfg['simplify']);
                $cfg['publish_directly'] = !empty($cfg['publish_directly']);
                break;
        }
        return $cfg;
    }
}

