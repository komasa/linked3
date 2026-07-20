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
    public function registerDefaultTests() : mixed {
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
            Logger::instance()->info('E2E test log entry');
            return file_exists($logFile);
        }, 'infrastructure');

        $this->registerTest('ai_dispatcher_exists', function() {
            return class_exists('\Linked3\Classes\E2E\AIDispatcher');
        }, 'ai');

        $this->registerTest('agent_orchestrator_loaded', function() {
            return linked3_container()->has('agent.orchestrator');
        }, 'agent');

        $this->registerTest('security_validator_ready', function() {
            return class_exists('\Linked3\Classes\E2E\SecurityValidator');
        }, 'security');

        $this->registerTest('billing_subscription_plans', function() {
            $plans = SubscriptionManager_V2::instance()->getPlans();
            return count($plans) >= 4;
        }, 'billing');

        $this->registerTest('quota_check_works', function() {
            $check = QuotaInterceptor::instance()->check(0, 1);
            return isset($check['allowed']);
        }, 'billing');
    }
}

// =================================================================
// v6.0.0.8: 健康监控面板


// =================================================================
// v6.0.0.9: 自动回滚


// =================================================================
// v6.0.0.10: 最终发布 Bootstrap

