<?php

declare(strict_types=1);
/**
 * Linked3 Rate Limiter V2 — v5.7.0.4
 *
 * 功能:
 *   - 多维度速率限制 (IP/User/API/AJAX)
 *   - 滑动窗口算法
 *   - 429 响应 + Retry-After 头
 *   - 事件驱动: rate_limited.exceeded
 *
 * @package Linked3\Security
 * @since 5.7.0.4
 */
namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class RateLimiterV2 {
    private static ?RateLimiterV2 $instance = null;
    private array $buckets = [];
    private array $limits = [
        'ip_minute' => ['max' => 60, 'window' => 60],       // IP 每分钟60次
        'user_hour' => ['max' => 100, 'window' => 3600],    // 用户每小时100次
        'ai_minute' => ['max' => 20, 'window' => 60],       // AI 每分钟20次
        'ajax_minute' => ['max' => 30, 'window' => 60],     // AJAX 每分钟30次
    ];

    public static function instance(): RateLimiterV2 {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 检查是否允许 (滑动窗口)。
     */
    public function check(string $key, string $limitType = 'ip_minute'): bool {
        $limit = $this->limits[$limitType] ?? ['max' => 60, 'window' => 60];
        $now = time();

        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [];
        }
        // 清理过期
        $this->buckets[$key] = array_filter(
            $this->buckets[$key],
            fn($t) => ($now - $t) < $limit['window']
        );

        if (count($this->buckets[$key]) >= $limit['max']) {
            linked3_dispatch('linked3.rate_limited.exceeded', [
                'key' => $key,
                'type' => $limitType,
                'max' => $limit['max'],
                'window' => $limit['window'],
            ]);
            return false;
        }
        $this->buckets[$key][] = $now;
        return true;
    }

}


