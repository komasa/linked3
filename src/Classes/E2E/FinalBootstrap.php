<?php

declare(strict_types=1);
/**
 * Linked3_Final_Bootstrap — extracted from E2eTestRunner.php during PSR-4 migration.
 *
 * @package Linked3\Classes\E2E

namespace Linked3\Classes\E2E;

if (!defined('ABSPATH')) exit;

class FinalBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        // Phase 1: 核心 (v5.4.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Bootstrap_V54')) {
            Linked3_Bootstrap_V54::boot();
        }

        // Phase 2: Agent (v5.5.0)
        if (class_exists('\Linked3\Classes\E2E\AgentBootstrap')) {
            AgentBootstrap::boot();
        }

        // Phase 3: AI 增强 (v5.6.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_AI_Pipeline_Bootstrap')) {
            Linked3_AI_Pipeline_Bootstrap::boot();
        }

        // Phase 4: 安全 (v5.7.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Security_Bootstrap')) {
            Linked3_Security_Bootstrap::boot();
        }

        // Phase 5: 商业 (v5.8.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Billing_Bootstrap')) {
            Linked3_Billing_Bootstrap::boot();
        }

        // Phase 6: 规模 (v5.9.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Scale_Bootstrap')) {
            Linked3_Scale_Bootstrap::boot();
        }

        // Phase 7: 最终验证
        if (class_exists('\Linked3\Classes\E2E\Linked3_Health_Monitor')) {
            $monitor = new Linked3_Health_Monitor();
            $health = $monitor->check();
            $failedCount = count(array_filter($health, fn($v) => $v === false));

            // ── FIX v16.0.1: Guard linked3_container() call ──────────────
            if ($failedCount > 0 && function_exists('linked3_container')) {
                $c = linked3_container();
                if ($c->has('logger')) {
                    $c->get('logger')->warning('Health check issues on boot', [
                        'failed' => array_keys(array_filter($health, fn($v) => $v === false)),
                    ]);
                }
            }

            // 自动回滚评估
            if (class_exists('\Linked3\Classes\E2E\Linked3_Auto_Rollback')) {
                Linked3_Auto_Rollback::instance()->evaluate();
            }
        }

        // 注册 E2E 测试
        if (class_exists('\Linked3\Classes\E2E\E2eTestRunner')) {
            $runner = E2eTestRunner::instance();
            $runner->registerDefaultTests();
        }

        // 派发就绪事件
        // ── FIX v16.0.1: Guard linked3_dispatch() call ──────────────────
        if (function_exists('linked3_dispatch')) {
            linked3_dispatch('linked3.system.ready', [
                'version' => LINKED3_VERSION,
                'health' => $health ?? [],
                'timestamp' => time(),
            ]);
        }
    }
}
