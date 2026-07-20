<?php

declare(strict_types=1);
/**
 * AutoRollback — extracted from E2eTestRunner.php during PSR-4 migration.
 *
 * @package Linked3\Classes\E2E
 */

namespace Linked3\Classes\E2E;

if (!defined('ABSPATH')) exit;

class AutoRollback {
    private static ?AutoRollback $instance = null;
    private int $failureThreshold = 3; // 超过3个系统失败触发回滚

    public static function instance(): AutoRollback {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 检查健康并决定是否回滚。
     */
    public function evaluate(): array {
        $health = (new HealthMonitor())->check();
        $failed = array_keys(array_filter($health, fn($v) => $v === false));
        $shouldRollback = count($failed) > $this->failureThreshold;

        if ($shouldRollback) {
            $this->triggerRollback($failed);
        }

        return [
            'should_rollback' => $shouldRollback,
            'failed_count' => count($failed),
            'failed_systems' => $failed,
        ];
    }

    private function triggerRollback(array $failed): void {
        update_option('linked3_rollback_required', true);
        update_option('linked3_rollback_failed_systems', $failed);
        linked3_dispatch('linked3.system.rollback', [
            'failed' => $failed,
            'version' => LINKED3_VERSION,
        ]);
        if (function_exists('error_log')) {
            error_log('[linked3] AUTO ROLLBACK TRIGGERED: ' . implode(', ', $failed));
        }
    }

}

// =================================================================
// v6.0.0.10: 最终发布 Bootstrap
// =================================================================
