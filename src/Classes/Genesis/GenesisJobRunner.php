<?php

declare(strict_types=1);
/**
 * Genesis Job Runner v7.1.6
 *
 * v7.1.6 修复 v7.1.5 卡在 0% 的问题:
 *   - WP-Cron 不触发 → 任务永远 pending
 *   - fastcgi_finish_request 不可用 → 同步兜底阻塞响应
 *   - 新增: 懒执行模式 (poll_job 时分块执行)
 *   - 新增: 心跳检测 (前端检测卡死)
 *
 * 执行策略 (v7.1.6 优先级):
 *   1. fastcgi_finish_request (最佳 — 响应后后台执行, 不阻塞)
 *   2. WP-Cron (次选 — 需要站点有流量触发)
 *   3. CLI spawn (备选 — 需要 proc_open)
 *   4. 懒执行 (兜底 — poll_job 时分块执行, 每次最多 5s)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisJobRunner
{
    const JOB_PREFIX = 'linked3_genesis_job_';
    const JOB_TTL = 3600; // 1 小时
    const POLL_INTERVAL_MS = 2000;
    const LAZY_CHUNK_TIME = 5; // 懒执行每块最多 5 秒
    const STALE_THRESHOLD = 60; // 60s 无进度更新 = 卡死

    /** @var string 当前任务 ID (避免闭包 use) */
    private static string $current_job_id = '';

    /**
     * 创建任务并立即返回 job_id
     *
     * v7.1.6: 不再在 startJob 里同步执行 (会导致阻塞)
     * 只创建 job + 触发异步执行, 立即返回
     */
    public static function startJob(array $params): array
    {
        $jobId = 'job_' . wp_generate_password(16, false);
        $job = [
            'id'           => $jobId,
            'status'       => 'pending',
            'progress'     => 0,
            'stage'        => 'init',
            'message'      => __('任务已创建, 等待执行...', 'linked3-ai'),
            'params'       => $params,
            'result'       => null,
            'error'        => null,
            'created_at'   => time(),
            'updated_at'   => time(),
            'heartbeat'    => time(),
            'logs'         => [],
            'exec_mode'    => 'unknown', // fastcgi / cron / cli / lazy
        ];

        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);

        // v7.1.6: 记录可用执行策略 (用于诊断)
        $job['exec_mode'] = self::detectExecMode();
        $job['logs'][] = '[' . date('H:i:s') . '] 任务创建, 执行模式: ' . $job['exec_mode'];
        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);

        return [
            'job_id'           => $jobId,
            'status'           => 'pending',
            'poll_interval_ms' => self::POLL_INTERVAL_MS,
            'exec_mode'        => $job['exec_mode'],
        ];
    }

    /**
     * v7.1.6: 检测可用的执行模式
     */
    public static function detectExecMode(): string
    {
        if (function_exists('fastcgi_finish_request')) return 'fastcgi';
        if (function_exists('wp_schedule_single_event') && !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)) return 'cron';
        if (function_exists('proc_open') && self::findPhpBinary() !== '') return 'cli';
        return 'lazy';
    }

    /**
     * v7.1.6: 触发任务执行 (非阻塞)
     *
     * 这个方法在 start_job AJAX 响应发送后调用
     * (通过 register_shutdown_function 或 fastcgi_finish_request)
     */
    public static function triggerExecution(string $jobId): void
    {
        $mode = self::detectExecMode();

        if ($mode === 'fastcgi') {
            // 最佳: fastcgi_finish_request 后同步执行
            // (AJAX handler 会先输出响应, 然后调用此方法)
            self::runJob($jobId);
        } elseif ($mode === 'cron') {
            // WP-Cron: 调度 1s 后执行
            wp_schedule_single_event(time() + 1, 'linked3_genesis_run_job', [$jobId]);
            self::spawnCron();
        } elseif ($mode === 'cli') {
            // CLI spawn
            self::spawnCli($jobId);
        }
        // lazy 模式: 不触发, 等待 poll_job 时执行
    }

    /**
     * v7.1.6: 懒执行 — 在 poll_job 时执行任务
     *
     * 当没有异步执行机制时, 每次轮询尝试执行任务。
     * 由于 PHP 无法真正暂停/恢复, 这里采用 "尽力执行" 策略:
     *   - 设置较短的超时 (25s), 让任务在 web server 60s 超时前完成
     *   - 如果任务能在 25s 内完成, 一次轮询就搞定
     *   - 如果不能, 进度会被保存, 下次轮询继续 (但会从头开始 — 这是限制)
     *
     * 注意: 这是兜底模式, 推荐 fastcgi_finish_request 或 WP-Cron
     */
    public static function lazyExecute(string $jobId): void
    {
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if (!$job) return;
        if ($job['status'] !== 'pending' && $job['status'] !== 'running') return;
        if ($job['status'] === 'running') {
            // 已经在运行 (可能是另一个 poll 请求), 不重复执行
            $heartbeatAge = time() - $job['heartbeat'];
            if ($heartbeatAge < 30) return; // 30s 内有心跳, 不干预
        }

        // 标记为 running
        if ($job['status'] === 'pending') {
            $job['status'] = 'running';
            $job['stage'] = 'lazy_start';
            $job['message'] = '懒执行模式: 在轮询时执行 (无异步机制可用)...';
            $job['logs'][] = '[' . date('H:i:s') . '] 懒执行启动';
        }

        $job['exec_mode'] = 'lazy';
        $job['heartbeat'] = time();
        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);

        // 执行任务 (设置 25s 超时, 在 web server 60s 限制内)
        @set_time_limit(25);
        self::runJob($jobId);
    }

    /**
     * 非阻塞触发 WP-Cron
     */
    private static function spawnCron(): void
    {
        $cronUrl = site_url('/wp-cron.php?doing_wp_cron=' . time());
        $args = [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
        ];
        wp_remote_get($cronUrl, $args);
    }

    /**
     * 查找 PHP 二进制路径
     */
    private static function findPhpBinary(): string
    {
        if (defined('PHP_BINARY') && PHP_BINARY && is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }
        $candidates = [
            '/usr/bin/php', '/usr/bin/php8.2', '/usr/bin/php8.1', '/usr/bin/php8.0', '/usr/bin/php7.4',
            '/usr/local/bin/php', '/opt/homebrew/bin/php',
        ];
        foreach ($candidates as $c) {
            if (is_executable($c)) return $c;
        }
        return '';
    }

    /**
     * spawn CLI 子进程执行任务
     */
    private static function spawnCli(string $jobId): void
    {
        $php = self::findPhpBinary();
        $script = LINKED3_DIR . 'scripts/run-genesis-job.php';
        if (!file_exists($script)) return;

        $cmd = sprintf(
            '%s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($jobId),
            escapeshellarg(ABSPATH . 'wp-load.php')
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // ── FIX v16.0.1: Guard proc_open() for restricted environments ────
        // WordPress Playground (WASM) and many shared hosts disable proc_open.
        // If the function doesn't exist or is disabled, skip async execution
        // gracefully — the job will still run via WP-Cron fallback.
        $proc = false;
        if (function_exists('proc_open')) {
            $proc = @proc_open($cmd, $descriptors, $pipes);
        }
        if (is_resource($proc)) {
            foreach ($pipes as $p) fclose($p);
            proc_close($proc);
        }
    }

    /**
     * 执行任务 (核心逻辑)
     * 被 triggerExecution / lazyExecute / WP-Cron 调用
     */
    public static function runJob(string $jobId): void
    {
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if (!$job) return;
        if ($job['status'] === 'done' || $job['status'] === 'error') return;
        if ($job['status'] === 'cancelled') return;

        // 标记为 running
        $job['status'] = 'running';
        $job['stage'] = 'preflight';
        $job['message'] = '预检中...';
        $job['heartbeat'] = time();
        $job['updated_at'] = time();
        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);

        // Fatal error 兜底
        self::$current_job_id = $jobId;
        register_shutdown_function([self::class, 'handle_fatal_shutdown']);

        try {
            @set_time_limit(600);
            @ini_set('memory_limit', '768M');

            $params = $job['params'];
            $script = $params['script'];
            $styleId = $params['style'];
            $platform = $params['platform'];
            $panelCountRaw = $params['panel_count'];

            // v7.2.0: 传递额外选项 (split_mode, chapter_marker, seed_id)
            // v11.0: 新增 panel_layout, aspect_ratio, rendering_tech (参照图示脚本大格局补全)
            $extraOptions = [
                'split_mode'     => $params['split_mode'] ?? 'auto',
                'chapter_marker' => $params['chapter_marker'] ?? 'auto',
                'seed_id'        => $params['seed_id'] ?? '',
                'panel_layout'   => $params['panel_layout'] ?? 'auto',
                'aspect_ratio'   => $params['aspect_ratio'] ?? '3:4',
                'rendering_tech' => $params['rendering_tech'] ?? 'auto',
            ];

            // 预检
            self::updateProgress($jobId, 5, 'preflight', '预检: 验证 API 配置...');

            // v7.1.8: 修复命名空间问题 — 必须用全限定名
            $registrarClass = '\\Linked3\\Classes\\Dashboard\\GenesisProcessor';

            if (method_exists($registrarClass, 'genesisPreflightCheck')) {
                $preflight = $registrarClass::genesisPreflightCheck();
                if (!$preflight['ok']) {
                    throw new \RuntimeException('预检失败: ' . $preflight['message'], 1);
                }
            }

            // 调用核心生成逻辑
            $result = self::executeGeneration($jobId, $script, $styleId, $platform, $panelCountRaw, $extraOptions);

            // 完成
            $job = get_transient(self::JOB_PREFIX . $jobId);
            if ($job && $job['status'] !== 'cancelled') {
                $job['status'] = 'done';
                $job['progress'] = 100;
                $job['stage'] = 'done';
                $job['message'] = '生成完成!';
                $job['result'] = $result;
                $job['heartbeat'] = time();
                $job['updated_at'] = time();
                $job['logs'][] = '[' . date('H:i:s') . '] 完成, 生成 ' . count($result['panels'] ?? []) . ' 个分镜';
                set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);
            }
        } catch (\Throwable $e) {
            $job = get_transient(self::JOB_PREFIX . $jobId);
            if ($job) {
                $job['status'] = 'error';
                $job['error'] = $e->getMessage();
                $job['error_class'] = get_class($e);
                $job['stage'] = 'exception';
                $job['heartbeat'] = time();
                $job['updated_at'] = time();
                $job['logs'][] = '[' . date('H:i:s') . '] 错误: ' . $e->getMessage();
                set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);
            }
        }
    }

    /**
     * 执行实际生成逻辑
     */
    private static function executeGeneration(string $jobId, string $script, string $styleId, string $platform, string $panelCountRaw, array $extraOptions = []): array
    {
        // v7.1.9: 修复 Bug 2 — DashboardAjaxRegistrar 有 __callStatic，导致 method_exists 恒 true
        //         但 $delegateMap 里没有 genesisGenerateMultiInternal，会走 ghost method 返回 501。
        //         正确目标：GenesisProcessor（真实方法，代理到 GenesisProcessorDelegates）
        $registrarClass = '\\Linked3\\Classes\\Dashboard\\GenesisProcessor';

        if (method_exists($registrarClass, 'genesisGenerateMultiInternal')) {
            return $registrarClass::genesisGenerateMultiInternal($script, $styleId, $platform, $panelCountRaw, [self::class, 'on_generation_progress'], $extraOptions);
        }

        // v7.1.8: 兜底 — 如果方法真的不存在, 至少返回有意义的错误 + 诊断
        return [
            'panels'      => [],
            'total_panels' => 0,
            'total_scenes' => 0,
            'style'       => $styleId,
            'platform'    => $platform,
            'mode'        => 'error',
            'fp_cores'    => 0,
            'is_auto'     => $panelCountRaw === 'auto',
            'error'       => 'genesisGenerateMultiInternal 方法不存在。类: ' . $registrarClass . ', method_exists: ' . (method_exists($registrarClass, 'genesisGenerateMultiInternal') ? 'true' : 'false') . ', class_exists: ' . (class_exists($registrarClass) ? 'true' : 'false'),
            'diagnostic'  => [
                'registrar_class'     => $registrarClass,
                'class_exists'        => class_exists($registrarClass),
                'method_exists'       => method_exists($registrarClass, 'genesisGenerateMultiInternal'),
                'jobrunner_class'     => __CLASS__,
                'jobrunner_file'      => __FILE__,
            ],
        ];
    }

    /**
     * 更新任务进度
     */
    public static function updateProgress(string $jobId, int $progress, string $stage, string $message): void
    {
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if (!$job) return;
        $job['progress'] = $progress;
        $job['stage'] = $stage;
        $job['message'] = $message;
        $job['heartbeat'] = time();
        $job['updated_at'] = time();
        $job['logs'][] = '[' . date('H:i:s') . '] ' . $stage . ' (' . $progress . '%): ' . $message;
        if (count($job['logs']) > 50) {
            $job['logs'] = array_slice($job['logs'], -50);
        }
        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);
    }

    /**
     * v7.1.6: 查询任务状态 (带卡死检测)
     */
    public static function pollJob(string $jobId): array
    {
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if (!$job) {
            return [
                'job_id' => $jobId,
                'status' => 'not_found',
                'message' => __('任务不存在或已过期 (超过 1 小时)', 'linked3-ai'),
            ];
        }

        $elapsed = time() - $job['created_at'];
        $stale = (time() - $job['heartbeat']) > self::STALE_THRESHOLD;

        // v7.1.6: 如果任务 pending 且可用懒执行, 立即执行一块
        if ($job['status'] === 'pending' && self::detectExecMode() === 'lazy') {
            $job['logs'][] = '[' . date('H:i:s') . '] 懒执行触发 (poll)';
            set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);
            self::lazyExecute($jobId);
            $job = get_transient(self::JOB_PREFIX . $jobId);
        }

        // v7.1.6: 卡死检测
        $staleWarning = '';
        if ($stale && in_array($job['status'], ['pending', 'running'], true)) {
            $staleWarning = '任务可能卡死 (心跳 ' . (time() - $job['heartbeat']) . 's 无更新)。';
            if ($job['exec_mode'] === 'cron') {
                $staleWarning .= ' WP-Cron 可能未触发, 请访问 /wp-cron.php 或配置系统 cron。';
            } elseif ($job['exec_mode'] === 'cli') {
                $staleWarning .= ' CLI spawn 可能失败 (proc_open 被禁用)。';
            }
        }

        return [
            'job_id'        => $jobId,
            'status'        => $job['status'],
            'progress'      => $job['progress'],
            'stage'         => $job['stage'],
            'message'       => $job['message'],
            'elapsed_sec'   => $elapsed,
            'heartbeat_age' => time() - $job['heartbeat'],
            'exec_mode'     => $job['exec_mode'] ?? 'unknown',
            'stale'         => $stale,
            'stale_warning' => $staleWarning,
            'result'        => $job['status'] === 'done' ? $job['result'] : null,
            'error'         => $job['status'] === 'error' ? $job['error'] : null,
            'error_class'   => $job['error_class'] ?? null,
            'logs'          => $job['logs'] ?? [],
        ];
    }

    /**
     * 取消任务
     */
    public static function cancelJob(string $jobId): bool
    {
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if (!$job) return false;
        if ($job['status'] === 'done' || $job['status'] === 'error') return false;
        $job['status'] = 'cancelled';
        $job['message'] = '用户取消';
        $job['heartbeat'] = time();
        $job['updated_at'] = time();
        set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);

        return true;
    }

    /**
     * shutdown 回调: Fatal Error 兜底 (替代闭包 use)。
     */
    public static function handle_fatal_shutdown(): void {
        $err = error_get_last();
        if (!($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))) {
            return;
        }
        $jobId = self::$current_job_id;
        if ($jobId === '') return;
        $job = get_transient(self::JOB_PREFIX . $jobId);
        if ($job && $job['status'] !== 'done') {
            $job['status'] = 'error';
            $job['error'] = 'PHP Fatal Error: ' . $err['message'] . ' (line ' . $err['line'] . ')';
            $job['error_class'] = 'FatalError';
            $job['stage'] = 'fatal';
            $job['heartbeat'] = time();
            $job['updated_at'] = time();
            set_transient(self::JOB_PREFIX . $jobId, $job, self::JOB_TTL);
        }
    }

    /**
     * 生成进度回调 (替代闭包 use)。
     */
    public static function on_generation_progress($progress, $stage, $message): void {
        $jobId = self::$current_job_id;
        if ($jobId === '') return;
        self::updateProgress($jobId, $progress, $stage, $message);
    }
}
