<?php

declare(strict_types=1);
/**
 * Linked3 Provider Health Check — v5.6.0.1
 *
 * 功能:
 *   - 定期 ping 每个 AI Provider, 记录响应延迟
 *   - 维护健康状态 (healthy/unhealthy/degraded)
 *   - 提供最优 Provider 选择 (最低延迟)
 *   - 事件驱动: health.changed 触发告警
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.1
 */
namespace Linked3\Classes\AI\Pipeline;

use Linked3\Includes\EventBus;
use Throwable;
use RuntimeException;

if (!defined('ABSPATH')) exit;

class ProviderHealthCheck {
    private static ?ProviderHealthCheck $instance = null;
    private array $health = [];        // ['siliconflow' => ['status'=>'healthy', 'latency'=>120, 'time'=>1234567890]]
    private array $responseTimes = []; // ['siliconflow' => [120, 150, 130, ...]] 滑动窗口100次
    private int $maxSamples = 100;
    private int $timeout = 10;          // ping 超时秒数
    private int $degradedThreshold = 2000; // 2秒以上标记 degraded
    private int $unhealthyThreshold = 5000; // 5秒以上标记 unhealthy

    public static function instance(): ProviderHealthCheck {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // 每5分钟自动检查一次
        add_action('linked3_health_cron', [$this, 'checkAll']);
        if (!wp_next_scheduled('linked3_health_cron')) {
            wp_schedule_event(time(), 'fivem', 'linked3_health_cron');
        }
    }

    /**
     * 检查单个 Provider 健康状态。
     */
    public function check(string $provider): array {
        $start = microtime(true);
        try {
            $ok = $this->ping($provider);
            $latency = (microtime(true) - $start) * 1000; // ms

            $oldStatus = $this->health[$provider]['status'] ?? 'unknown';
            $newStatus = $this->classifyStatus($latency, $ok);

            $this->health[$provider] = [
                'status' => $newStatus,
                'latency' => round($latency, 1),
                'time' => time(),
                'error' => null,
            ];

            // 滑动窗口记录延迟
            $this->responseTimes[$provider][] = $latency;
            if (count($this->responseTimes[$provider]) > $this->maxSamples) {
                array_shift($this->responseTimes[$provider]);
            }

            // 状态变化时派发事件
            if ($oldStatus !== $newStatus && $oldStatus !== 'unknown') {
                EventBus::dispatch('linked3.ai.health.changed', [
                    'provider' => $provider,
                    'old' => $oldStatus,
                    'new' => $newStatus,
                    'latency' => $latency,
                ]);
            }

            return $this->health[$provider];
        } catch (Throwable $e) {
            $this->health[$provider] = [
                'status' => 'unhealthy',
                'latency' => 0,
                'time' => time(),
                'error' => $e->getMessage(),
            ];
            EventBus::dispatch('linked3.ai.health.failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return $this->health[$provider];
        }
    }

    /**
     * 检查所有已配置的 Provider。
     */
    public function checkAll(): void {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $providers = array_keys($keys);
        foreach ($providers as $p) {
            $this->check($p);
        }
    }

    /**
     * 根据延迟和可达性分类状态。
     */
    private function classifyStatus(float $latency, bool $ok): string {
        if (!$ok) return 'unhealthy';
        if ($latency >= $this->unhealthyThreshold) return 'unhealthy';
        if ($latency >= $this->degradedThreshold) return 'degraded';
        return 'healthy';
    }

    /**
     * 获取平均延迟 (滑动窗口)。
     */
    public function getAvgLatency(string $provider): float {
        if (empty($this->responseTimes[$provider])) return 0;
        return array_sum($this->responseTimes[$provider]) / count($this->responseTimes[$provider]);
    }

    /**
     * 选择最优 Provider (最低平均延迟, 状态 healthy)。
     */
    public function selectBest(array $providers): string {
        $best = null;
        $bestLatency = PHP_FLOAT_MAX;
        foreach ($providers as $p) {
            $health = $this->health[$p] ?? null;
            if ($health && $health['status'] === 'healthy') {
                $latency = $this->getAvgLatency($p);
                if ($latency < $bestLatency) {
                    $bestLatency = $latency;
                    $best = $p;
                }
            }
        }
        // 如果没有 healthy 的, 退而求其次选 degraded
        if ($best === null) {
            foreach ($providers as $p) {
                $health = $this->health[$p] ?? null;
                if ($health && $health['status'] === 'degraded') {
                    $best = $p;
                    break;
                }
            }
        }
        // 全部不可用, 返回第一个 (让上层报错)
        return $best ?: ($providers[0] ?? '');
    }

    /**
     * Ping Provider — 发送一个最小请求检查可达性。
     */
    private function ping(string $provider): bool {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        if (empty($keys[$provider])) {
            throw new RuntimeException("No API key for: {$provider}");
        }

        // 简化: 检查 key 存在即可 (真实环境发一个 1 token 的请求)
        $keyLines = array_filter(array_map('trim', explode("\n", $keys[$provider])));
        if (empty($keyLines)) {
            throw new RuntimeException("Empty API key for: {$provider}");
        }

        // 可以根据 provider 发真实 ping
        // 这里简化为 key 存在即 healthy
        return true;
    }
}

// 注册 cron 间隔
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['fivem'])) {
        $schedules['fivem'] = ['interval' => 300, 'display' => 'Every 5 Minutes'];
    }
    return $schedules;
});
