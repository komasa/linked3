<?php

declare(strict_types=1);
/**
 * HealthMonitor — extracted from E2eTestRunner.php during PSR-4 migration.
 *
 * @package Linked3\Classes\E2E

namespace Linked3\Classes\E2E;

if (!defined('ABSPATH')) exit;

class HealthMonitor {
    public function check(): array {
        $container = linked3_container();
        return [
            // 基础设施
            'container'       => $container->has('logger'),
            'event_bus'       => class_exists('\Linked3\Includes\EventBus'),
            'logger'          => class_exists('\Linked3\Classes\E2E\Logger'),
            'error_handler'   => class_exists('\Linked3\Classes\E2E\ErrorHandler'),
            // Agent
            'agent_orchestrator' => $container->has('agent.orchestrator'),
            'quality_gate'      => $container->has('agent.quality_gate'),
            'scheduler'         => $container->has('agent.scheduler'),
            // AI 管线
            'provider_health' => class_exists('\Linked3\Classes\E2E\ProviderHealthCheck'),
            'failover'        => class_exists('\Linked3\Classes\E2E\ProviderFailover'),
            'token_meter'     => class_exists('\Linked3\Classes\E2E\TokenMeter'),
            'key_pool'        => class_exists('\Linked3\Classes\E2E\KeyPool'),
            'prompt_engine'   => class_exists('\Linked3\Classes\E2E\PromptEngine'),
            'content_scorer'  => class_exists('\Linked3\Classes\AI\Pipeline\ContentQualityScorer'),
            'stream_output'   => class_exists('\Linked3\Classes\E2E\StreamOutput'),
            'cost_reporter'   => class_exists('\Linked3\Classes\AI\Pipeline\CostReporter'),
            'prompt_cache'    => class_exists('\Linked3\Classes\E2E\PromptCache'),
            // 安全
            'security_validator' => class_exists('\Linked3\Classes\E2E\SecurityValidator'),
            'rate_limiter'       => class_exists('\Linked3\Classes\E2E\RateLimiterV2'),
            'async_queue'        => class_exists('\Linked3\Classes\E2E\AsyncQueue'),
            'audit_logger'       => class_exists('\Linked3\Classes\Security\AuditLogger'),
            // 商业
            'payment_manager'    => class_exists('\Linked3\Classes\Billing\PaymentManager'),
            'subscription'       => class_exists('\Linked3\Classes\E2E\SubscriptionManager_V2'),
            'quota_interceptor'  => class_exists('\Linked3\Classes\Billing\QuotaInterceptor'),
            'invoice_manager'    => class_exists('\Linked3\Classes\Billing\InvoiceManager'),
            'referral_manager'   => class_exists('\Linked3\Classes\Billing\ReferralManager'),
            // 规模
            'vector_incremental' => class_exists('\Linked3\Classes\E2E\VectorIncremental'),
            'i18n_manager'       => class_exists('\Linked3\Classes\Scale\I18nManager'),
            'multisite_publisher' => class_exists('\Linked3\Classes\Scale\MultiSitePublisher'),
            'batch_engine'       => class_exists('\Linked3\Classes\Scale\BatchEngine'),
            'performance_cache'  => class_exists('\Linked3\Classes\Scale\PerformanceCache'),
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
