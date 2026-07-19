<?php
/**
 * Linked3 Async Queue — v5.7.0.3
 *
 * 功能:
 *   - 后台异步任务队列 (不阻塞用户请求)
 *   - WP-Cron 驱动 + DB 持久化
 *   - 优先级 + 重试 + 超时
 *   - 事件驱动: task.completed / task.failed
 *
 * @package Linked3\Security
 * @since 5.7.0.3
 */
namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class Linked3_Async_Queue {
    private static ?Linked3_Async_Queue $instance = null;
    private int $maxRetries = 3;
    private int $timeout = 300; // 5分钟

    public static function instance(): Linked3_Async_Queue {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->ensureTable();
        add_action('linked3_async_process', [$this, 'process']);
        if (!wp_next_scheduled('linked3_async_process')) {
            wp_schedule_event(time(), 'every_minute', 'linked3_async_process');
        }
    }

    /**
     * 入队异步任务。
     */
    public function enqueue(string $handler, array $payload = [], int $priority = 10): string {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        $taskId = uniqid('task_');

        $wpdb->insert($table, [
            'task_id' => $taskId,
            'handler' => $handler,
            'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'priority' => $priority,
            'status' => 'pending',
            'attempts' => 0,
            'max_retries' => $this->maxRetries,
            'created_at' => current_time('mysql'),
        ]);

        linked3_dispatch('linked3.async.enqueued', ['task_id' => $taskId, 'handler' => $handler]);
        return $taskId;
    }

    /**
     * 处理队列 (每次最多 N 个)。
     */
    public function process(int $batchSize = 5): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';

        // 取出最高优先级的 pending 任务
        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND attempts < max_retries
             ORDER BY priority ASC, created_at ASC
             LIMIT %d",
            $batchSize
        ), ARRAY_A);

        foreach ($tasks as $task) {
            $this->executeTask($task);
        }
    }

    /**
     * 执行单个任务。
     */
    private function executeTask(array $task): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';

        // 标记为 processing
        $wpdb->update($table, [
            'status' => 'processing',
            'attempts' => $task['attempts'] + 1,
            'started_at' => current_time('mysql'),
        ], ['id' => $task['id']]);

        $handler = $task['handler'];
        $payload = json_decode($task['payload'], true) ?? [];

        try {
            // 执行 handler (支持 callable 或 class::method)
            if (is_callable($handler)) {
                $result = $handler($payload);
            } elseif (class_exists($handler)) {
                $instance = new $handler();
                $result = $instance->execute($payload);
            } else {
                throw new RuntimeException("Handler not found: {$handler}");
            }

            // 成功
            $wpdb->update($table, [
                'status' => 'completed',
                'result' => wp_json_encode($result, JSON_UNESCAPED_UNICODE),
                'completed_at' => current_time('mysql'),
            ], ['id' => $task['id']]);

            linked3_dispatch('linked3.async.task.completed', [
                'task_id' => $task['task_id'],
                'handler' => $handler,
            ]);
        } catch (Throwable $e) {
            // 失败: 检查是否还能重试
            $attempts = $task['attempts'] + 1;
            $status = $attempts >= $task['max_retries'] ? 'failed' : 'pending';

            $wpdb->update($table, [
                'status' => $status,
                'attempts' => $attempts,
                'error' => $e->getMessage(),
            ], ['id' => $task['id']]);

            linked3_dispatch('linked3.async.task.failed', [
                'task_id' => $task['task_id'],
                'handler' => $handler,
                'error' => $e->getMessage(),
                'attempts' => $attempts,
            ]);
        }
    }

    /**
     * 获取队列状态。
     */
    public function getStatus(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        $stats = [];
        foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
            $stats[$status] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status
            ));
        }
        return $stats;
    }

    /**
     * 清理已完成/失败任务 (保留最近7天)。
     */
    public function cleanup(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status IN ('completed', 'failed') AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
    }

    private function ensureTable(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_async_queue';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id VARCHAR(64) NOT NULL,
                handler VARCHAR(255) NOT NULL,
                payload LONGTEXT,
                priority INT DEFAULT 10,
                status VARCHAR(20) DEFAULT 'pending',
                attempts INT DEFAULT 0,
                max_retries INT DEFAULT 3,
                result LONGTEXT,
                error TEXT,
                created_at DATETIME,
                started_at DATETIME,
                completed_at DATETIME,
                INDEX idx_status (status),
                INDEX idx_priority (priority, created_at),
                UNIQUE KEY uniq_task (task_id)
            ) {$charset};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }
}
