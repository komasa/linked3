<?php
/**
 * Linked3 Key Pool — v5.6.0.4
 *
 * 功能:
 *   - 每个 Provider 支持多 Key 轮转 (突破单 Key 速率限制)
 *   - Key 限流自动切换下一个 Key
 *   - Key 健康追踪 (可用/限流/失效)
 *   - 事件驱动: key.exhausted / key.rotated
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.4
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class Linked3_Key_Pool {
    private static ?Linked3_Key_Pool $instance = null;
    private array $keys = [];       // ['siliconflow' => ['sk-xxx', 'sk-yyy', ...]]
    private array $keyStatus = [];  // ['sk-xxx' => ['status'=>'active', 'rate_limited_until'=>0, 'calls'=>0]]
    private int $rotationIndex = 0;

    public static function instance(): Linked3_Key_Pool {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->loadKeys();
    }

    /**
     * 从 option 加载所有 Provider 的 Key 池。
     */
    private function loadKeys(): void {
        $providerKeys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        foreach ($providerKeys as $provider => $raw) {
            $lines = array_filter(array_map('trim', explode("\n", $raw)));
            $this->keys[$provider] = $lines;
            foreach ($lines as $key) {
                if (!isset($this->keyStatus[$key])) {
                    $this->keyStatus[$key] = [
                        'status' => 'active',
                        'rate_limited_until' => 0,
                        'calls' => 0,
                        'errors' => 0,
                    ];
                }
            }
        }
    }

    /**
     * 获取一个可用的 Key (轮转 + 跳过限流)。
     */
    public function getKey(string $provider): string {
        $pool = $this->keys[$provider] ?? [];
        if (empty($pool)) {
            throw new RuntimeException("No API keys configured for: {$provider}");
        }

        $now = time();
        foreach ($pool as $i => $key) {
            $status = $this->keyStatus[$key] ?? ['status' => 'active', 'rate_limited_until' => 0];
            // 限流过期自动恢复
            if ($status['status'] === 'rate_limited' && $now >= $status['rate_limited_until']) {
                $this->keyStatus[$key]['status'] = 'active';
                $this->keyStatus[$key]['rate_limited_until'] = 0;
            }
            if ($status['status'] === 'active') {
                $this->rotationIndex = $i;
                $this->keyStatus[$key]['calls']++;
                return $key;
            }
        }

        // 所有 Key 都限流, 找最快恢复的
        $soonest = PHP_INT_MAX;
        $bestKey = $pool[0];
        foreach ($pool as $key) {
            $until = $this->keyStatus[$key]['rate_limited_until'] ?? 0;
            if ($until < $soonest) {
                $soonest = $until;
                $bestKey = $key;
            }
        }
        $waitSec = max(0, $soonest - $now);
        linked3_dispatch('linked3.ai.key.exhausted', [
            'provider' => $provider,
            'wait_seconds' => $waitSec,
        ]);
        throw new RuntimeException("All keys rate limited for {$provider}. Wait {$waitSec}s.");
    }

    /**
     * 标记 Key 被限流。
     */
    public function markRateLimited(string $key, int $retryAfter = 60): void {
        if (!isset($this->keyStatus[$key])) return;
        $this->keyStatus[$key]['status'] = 'rate_limited';
        $this->keyStatus[$key]['rate_limited_until'] = time() + $retryAfter;
        linked3_dispatch('linked3.ai.key.rotated', ['key' => substr($key, 0, 10) . '***', 'retry_after' => $retryAfter]);
    }

    /**
     * 标记 Key 失效 (永久)。
     */
    public function markInvalid(string $key): void {
        if (!isset($this->keyStatus[$key])) return;
        $this->keyStatus[$key]['status'] = 'invalid';
    }

    /**
     * 标记 Key 调用出错。
     */
    public function markError(string $key): void {
        if (!isset($this->keyStatus[$key])) return;
        $this->keyStatus[$key]['errors']++;
        if ($this->keyStatus[$key]['errors'] >= 10) {
            $this->markInvalid($key);
        }
    }

    /**
     * 获取 Key 池状态。
     */
    public function getPoolStatus(string $provider): array {
        $pool = $this->keys[$provider] ?? [];
        $result = [];
        foreach ($pool as $key) {
            $status = $this->keyStatus[$key] ?? ['status' => 'unknown'];
            $result[] = [
                'key' => substr($key, 0, 10) . '***',
                'status' => $status['status'],
                'calls' => $status['calls'] ?? 0,
                'errors' => $status['errors'] ?? 0,
            ];
        }
        return $result;
    }

    /**
     * 获取活跃 Key 数量。
     */
    public function getActiveKeyCount(string $provider): int {
        $pool = $this->keys[$provider] ?? [];
        $count = 0;
        foreach ($pool as $key) {
            $status = $this->keyStatus[$key]['status'] ?? 'active';
            if ($status === 'active') $count++;
        }
        return $count;
    }
}
