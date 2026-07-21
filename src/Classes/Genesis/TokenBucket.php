<?php

declare(strict_types=1);
/**
 * Linked3 令牌桶限流器 v10.0.1
 *
 * 公理2实现: 系统降维 — 将无限并发请求降维为有限令牌桶控制
 * 解决熵增点E2: AI并发无限制导致429雪崩
 *
 * 算法: 经典令牌桶
 *   - 桶容量 capacity: 最大突发请求数
 *   - 补充速率 rate: 每秒补充令牌数
 *   - acquire(n, timeout): 获取n个令牌, timeout秒内拿不到返回false
 *
 * 存储: WP transient (跨请求共享)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @version 10.0.1
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class TokenBucket {

    /** @var string 桶标识 */
    private $key;

    /** @var int 桶容量 */
    private $capacity;

    /** @var float 补充速率(令牌/秒) */
    private $rate;

    /** @var string transient键 */
    private $transient_key;

    /**
     * @param string $key       桶标识 (如 'lk3_fp_ai')
     * @param int    $capacity  桶容量 (默认3)
     * @param float  $rate      补充速率 (默认1.0/秒)
     */
    public function __construct(string $key, int $capacity = 3, float $rate = 1.0) {
        $this->key = $key;
        $this->capacity = max(1, $capacity);
        $this->rate = max(0.1, $rate);
        $this->transient_key = 'lk3_tb_' . $key;
    }

    /**
     * 获取令牌
     *
     * @param int   $tokens  需要的令牌数 (默认1)
     * @param float $timeout 超时秒数 (默认0=不等待, 立即返回)
     * @return bool true=获取成功, false=失败
     */
    public function acquire(int $tokens = 1, float $timeout = 0): bool {
        $tokens = max(1, $tokens);
        if ($tokens > $this->capacity) {
            // 需要的令牌超过桶容量, 永远拿不到
            return false;
        }

        $start = microtime(true);
        $waited = 0;

        while (true) {
            $state = $this->get_state();
            $now = microtime(true);

            // 补充令牌 (按时间差)
            $elapsed = $now - $state['last_refill'];
            $refilled = $elapsed * $this->rate;
            $state['tokens'] = min($this->capacity, $state['tokens'] + $refilled);
            $state['last_refill'] = $now;

            if ($state['tokens'] >= $tokens) {
                // 令牌足够, 扣减
                $state['tokens'] -= $tokens;
                $this->save_state($state);
                return true;
            }

            // 令牌不足
            if ($timeout <= 0) {
                // 不等待, 立即返回失败
                $this->save_state($state);
                return false;
            }

            $waited = microtime(true) - $start;
            if ($waited >= $timeout) {
                // 超时
                $this->save_state($state);
                return false;
            }

            // 计算需要等待的时间
            $needed = $tokens - $state['tokens'];
            $wait_time = $needed / $this->rate;
            // 先保存当前状态(含补充)
            $this->save_state($state);
            // 短睡 (10ms粒度, 避免CPU空转)
            usleep(min(100000, intval($wait_time * 1000000)));
        }
    }

    /**
     * 获取当前可用令牌数
     */
    public function available(): float {
        $state = $this->get_state();
        $now = microtime(true);
        $elapsed = $now - $state['last_refill'];
        $refilled = $elapsed * $this->rate;
        return min($this->capacity, $state['tokens'] + $refilled);
    }

    /**
     * 重置桶 (管理操作)
     */
    public function reset() : void {
        delete_transient($this->transient_key);
    }

    /**
     * 获取桶状态
     */
    private function get_state(): array {
        $state = get_transient($this->transient_key);
        if (!is_array($state)) {
            // 初始化: 满桶
            return [
                'tokens' => $this->capacity,
                'last_refill' => microtime(true),
            ];
        }
        return $state;
    }

    /**
     * 保存桶状态
     */
    private function save_state(array $state): bool {
        // transient 保留1小时 (超过自动清理)
        return set_transient($this->transient_key, $state, 3600);
    }
}
