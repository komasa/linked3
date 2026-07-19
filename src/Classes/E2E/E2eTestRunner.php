<?php

declare(strict_types=1);
/**
 * Linked3 v6.0.0 — 商业生产级最终集成
 *
 * 原子版本:
 *   v6.0.0.1: E2E 测试框架
 *   v6.0.0.2: 压力测试
 *   v6.0.0.3: 安全审计扫描
 *   v6.0.0.4~0.7: 文档生成 + 灰度发布 + 降级策略
 *   v6.0.0.8: 健康监控面板
 *   v6.0.0.9: 自动回滚
 *   v6.0.0.10: 最终发布 Bootstrap
 *
 * @package Linked3\E2E
 * @since 6.0.0
 */
namespace Linked3\Classes\E2E;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.0.0.1: E2E 测试框架
// =================================================================

class E2eTestRunner {
    private static ?E2eTestRunner $instance = null;
    private array $tests = [];
    private array $results = [];

    /** @var bool E2E 事件总线测试触发标志 (避免闭包 use) */
    private static $e2e_triggered = false;

    public static function instance(): E2eTestRunner {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 注册测试用例。
     */
    public function registerTest(string $name, callable $test, string $category = 'general'): void {
        $this->tests[$name] = ['test' => $test, 'category' => $category];
    }

    /**
     * 运行所有测试。
     */
    public function runAll(): array {
        $this->results = [];
        foreach ($this->tests as $name => $t) {
            $start = microtime(true);
            try {
                $result = ($t['test'])();
                $this->results[$name] = [
                    'status' => 'passed',
                    'category' => $t['category'],
                    'duration' => round((microtime(true) - $start) * 1000, 1),
                    'result' => $result,
                ];
            } catch (Throwable $e) {
                $this->results[$name] = [
                    'status' => 'failed',
                    'category' => $t['category'],
                    'duration' => round((microtime(true) - $start) * 1000, 1),
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $this->getReport();
    }

    /**
     * 获取测试报告。
     */
    public function getReport(): array {
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        return [
            'total' => count($this->results),
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => count($this->results) > 0 ? round($passed / count($this->results) * 100, 1) : 0,
            'results' => $this->results,
        ];
    }

    /**
     * 注册默认 E2E 测试。
     */
    public function registerDefaultTests(): void {
        $this->registerTest('container_loaded', function() {
            return linked3_container()->has('logger');
        }, 'infrastructure');

        $this->registerTest('event_bus_working', function() {
            self::$e2e_triggered = false;
            linked3_subscribe('e2e_test_event', function() {
                self::$e2e_triggered = true;
            });
            linked3_dispatch('e2e_test_event', []);
            return self::$e2e_triggered;
        }, 'infrastructure');

        $this->registerTest('logger_writes', function() {
            $logFile = trailingslashit(WP_CONTENT_DIR) . 'linked3-logs/linked3-' . current_time('Y-m-d') . '.log';
            Linked3_Logger::instance()->info('E2E test log entry');
            return file_exists($logFile);
        }, 'infrastructure');

        $this->registerTest('ai_dispatcher_exists', function() {
            return class_exists('\Linked3\Classes\E2E\Linked3_AI_Dispatcher');
        }, 'ai');

        $this->registerTest('agent_orchestrator_loaded', function() {
            return linked3_container()->has('agent.orchestrator');
        }, 'agent');

        $this->registerTest('security_validator_ready', function() {
            return class_exists('\Linked3\Classes\E2E\Linked3_Security_Validator');
        }, 'security');

        $this->registerTest('billing_subscription_plans', function() {
            $plans = Linked3_Subscription_Manager_V2::instance()->getPlans();
            return count($plans) >= 4;
        }, 'billing');

        $this->registerTest('quota_check_works', function() {
            $check = Linked3_Quota_Interceptor::instance()->check(0, 1);
            return isset($check['allowed']);
        }, 'billing');
    }
}

// =================================================================
// v6.0.0.8: 健康监控面板
// =================================================================

class Linked3_Health_Monitor {
    public function check(): array {
        $container = linked3_container();
        return [
            // 基础设施
            'container'       => $container->has('logger'),
            'event_bus'       => class_exists('\Linked3\Classes\E2E\Linked3_Event_Bus'),
            'logger'          => class_exists('\Linked3\Classes\E2E\Linked3_Logger'),
            'error_handler'   => class_exists('\Linked3\Classes\E2E\Linked3_Error_Handler'),
            // Agent
            'agent_orchestrator' => $container->has('agent.orchestrator'),
            'quality_gate'      => $container->has('agent.quality_gate'),
            'scheduler'         => $container->has('agent.scheduler'),
            // AI 管线
            'provider_health' => class_exists('\Linked3\Classes\E2E\Linked3_Provider_Health_Check'),
            'failover'        => class_exists('\Linked3\Classes\E2E\Linked3_Provider_Failover'),
            'token_meter'     => class_exists('\Linked3\Classes\E2E\Linked3_Token_Meter'),
            'key_pool'        => class_exists('\Linked3\Classes\E2E\Linked3_Key_Pool'),
            'prompt_engine'   => class_exists('\Linked3\Classes\E2E\Linked3_Prompt_Engine'),
            'content_scorer'  => class_exists('\Linked3\Classes\E2E\Linked3_Content_Quality_Scorer'),
            'stream_output'   => class_exists('\Linked3\Classes\E2E\Linked3_Stream_Output'),
            'cost_reporter'   => class_exists('\Linked3\Classes\E2E\Linked3_Cost_Reporter'),
            'prompt_cache'    => class_exists('\Linked3\Classes\E2E\Linked3_Prompt_Cache'),
            // 安全
            'security_validator' => class_exists('\Linked3\Classes\E2E\Linked3_Security_Validator'),
            'rate_limiter'       => class_exists('\Linked3\Classes\E2E\Linked3_Rate_Limiter_V2'),
            'async_queue'        => class_exists('\Linked3\Classes\E2E\Linked3_Async_Queue'),
            'audit_logger'       => class_exists('\Linked3\Classes\E2E\Linked3_Audit_Logger'),
            // 商业
            'payment_manager'    => class_exists('\Linked3\Classes\E2E\Linked3_Payment_Manager'),
            'subscription'       => class_exists('\Linked3\Classes\E2E\Linked3_Subscription_Manager_V2'),
            'quota_interceptor'  => class_exists('\Linked3\Classes\E2E\Linked3_Quota_Interceptor'),
            'invoice_manager'    => class_exists('\Linked3\Classes\E2E\Linked3_Invoice_Manager'),
            'referral_manager'   => class_exists('\Linked3\Classes\E2E\Linked3_Referral_Manager'),
            // 规模
            'vector_incremental' => class_exists('\Linked3\Classes\E2E\VectorIncremental'),
            'i18n_manager'       => class_exists('\Linked3\Classes\E2E\Linked3_i18n_Manager'),
            'multisite_publisher' => class_exists('\Linked3\Classes\E2E\Linked3_MultiSite_Publisher'),
            'batch_engine'       => class_exists('\Linked3\Classes\E2E\Linked3_Batch_Engine'),
            'performance_cache'  => class_exists('\Linked3\Classes\E2E\Linked3_Performance_Cache'),
        ];
    }

    public function getReport(): string {
        $health = $this->check();
        $passed = count(array_filter($health));
        $total = count($health);
        $lines = ["=== Linked3 v6.0.0 健康检查 ===", "通过: {$passed}/{$total}", ""];

        foreach ($health as $key => $ok) {
            $lines[] = ($ok ? '✅' : '❌') . " {$key}";
        }

        if ($passed === $total) {
            $lines[] = "\n✅ 所有系统就绪 — v6.0.0 商业生产级";
        } else {
            $lines[] = "\n⚠️  " . ($total - $passed) . " 个系统需要关注";
        }
        return implode("\n", $lines);
    }

    public function getJsonReport(): array {
        $health = $this->check();
        return [
            'version' => LINKED3_VERSION,
            'passed' => count(array_filter($health)),
            'total' => count($health),
            'health' => $health,
            'time' => current_time('mysql'),
        ];
    }
}

// =================================================================
// v6.0.0.9: 自动回滚
// =================================================================

class Linked3_Auto_Rollback {
    private static ?Linked3_Auto_Rollback $instance = null;
    private int $failureThreshold = 3; // 超过3个系统失败触发回滚

    public static function instance(): Linked3_Auto_Rollback {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 检查健康并决定是否回滚。
     */
    public function evaluate(): array {
        $health = (new Linked3_Health_Monitor())->check();
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

    public function isRollbackRequired(): bool {
        return (bool) get_option('linked3_rollback_required', false);
    }

    public function clearRollbackFlag(): void {
        delete_option('linked3_rollback_required');
        delete_option('linked3_rollback_failed_systems');
    }
}

// =================================================================
// v6.0.0.10: 最终发布 Bootstrap
// =================================================================

class Linked3_Final_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        // Phase 1: 核心 (v5.4.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Bootstrap_V54')) {
            Linked3_Bootstrap_V54::boot();
        }

        // Phase 2: Agent (v5.5.0)
        if (class_exists('\Linked3\Classes\E2E\Linked3_Agent_Bootstrap')) {
            Linked3_Agent_Bootstrap::boot();
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
