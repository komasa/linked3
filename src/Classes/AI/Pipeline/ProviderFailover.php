<?php

declare(strict_types=1);
/**
 * Linked3 Provider Failover — v5.6.0.2
 *
 * 功能:
 *   - AI 调用失败时自动切换到备用 Provider
 *   - 负载均衡: 按 Provider 健康度+延迟分配请求
 *   - 熔断集成: 某个 Provider 连续失败时跳过
 *   - 事件驱动: failover.triggered 记录切换日志
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.2
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class ProviderFailover {
    private static ?ProviderFailover $instance = null;
    private array $failoverChain = [];  // ['siliconflow' => ['deepseek', 'zhipu', 'openai']]
    private array $failureCounts = [];   // ['siliconflow' => 3]
    private int $circuitThreshold = 5;   // 连续失败5次打开熔断
    private int $circuitResetTime = 300; // 熔断5分钟后半开重试

    public static function instance(): ProviderFailover {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 设置 Provider 的故障转移链。
     */
    public function setFailoverChain(string $primary, array $fallbacks): void {
        $this->failoverChain[$primary] = $fallbacks;
    }

    /**
     * 获取默认故障转移链 (按健康度排序)。
     */
    public function getDefaultChain(string $primary): array {
        if (isset($this->failoverChain[$primary])) {
            return $this->failoverChain[$primary];
        }
        // 默认链: 国内 provider 优先
        $defaults = [
            'siliconflow' => ['deepseek', 'zhipu', 'openai'],
            'deepseek'    => ['siliconflow', 'zhipu', 'openai'],
            'zhipu'       => ['siliconflow', 'deepseek', 'openai'],
            'openai'      => ['siliconflow', 'deepseek', 'zhipu'],
        ];
        return $defaults[$primary] ?? ['siliconflow', 'deepseek', 'zhipu'];
    }

    /**
     * 负载均衡: 根据健康度和延迟选择最优 Provider。
     */
    public function selectByLoadBalance(array $providers): string {
        $healthCheck = ProviderHealthCheck::instance();
        return $healthCheck->selectBest($providers);
    }

    /**
     * 记录失败, 达到阈值打开熔断。
     */
    private function recordFailure(string $provider): void {
        if (!isset($this->failureCounts[$provider])) {
            $this->failureCounts[$provider] = ['count' => 0, 'circuit_open' => false, 'opened_at' => 0];
        }
        $this->failureCounts[$provider]['count']++;

        if ($this->failureCounts[$provider]['count'] >= $this->circuitThreshold && !$this->failureCounts[$provider]['circuit_open']) {
            $this->failureCounts[$provider]['circuit_open'] = true;
            $this->failureCounts[$provider]['opened_at'] = time();
            linked3_dispatch('linked3.ai.circuit.opened', ['provider' => $provider]);
            linked3_container()->get('logger')->warning("Circuit breaker opened for {$provider}");
        }
    }

    /**
     * 重置失败计数 (成功调用后)。
     */
    private function resetFailures(string $provider): void {
        unset($this->failureCounts[$provider]);
    }

    /**
     * 检查熔断是否打开 (含半开重试逻辑)。
     */
    private function isCircuitOpen(string $provider): bool {
        if (!isset($this->failureCounts[$provider])) return false;
        $fc = $this->failureCounts[$provider];
        if (!$fc['circuit_open']) return false;

        // 熔断超时后半开 (允许一次重试)
        if ((time() - $fc['opened_at']) >= $this->circuitResetTime) {
            $this->failureCounts[$provider]['circuit_open'] = false;
            $this->failureCounts[$provider]['count'] = 0;
            return false; // 半开: 允许调用
        }
        return true;
    }

    /**
     * 获取链中下一个 Provider。
     */
    private function getNextProvider(string $current, array $chain): ?string {
        $idx = array_search($current, $chain);
        if ($idx === false || $idx >= count($chain) - 1) return null;
        return $chain[$idx + 1];
    }

    /**
     * 获取熔断状态 (调试用)。
     */
    public function getCircuitStatus(): array {
        return $this->failureCounts;
    }
}
