<?php
/**
 * Linked3 Token Meter — v5.6.0.3
 *
 * 功能:
 *   - 精确计量每次 AI 调用的 token 用量
 *   - 按 provider/model/user/module 维度统计
 *   - 滑动窗口: 实时/小时/天/月
 *   - 事件驱动: token.consumed 记录到审计
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.3
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class Linked3_Token_Meter {
    private static ?Linked3_Token_Meter $instance = null;
    private array $usage = []; // 内存缓存, 持久化到 option

    public static function instance(): Linked3_Token_Meter {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->usage = get_option(LINKED3_OPTION_PREFIX . 'token_usage', []);
    }

    /**
     * 记录一次 token 消耗。
     */
    public function record(int $userId, string $provider, string $model, string $module, array $usage): void {
        $date = current_time('Y-m-d');
        $hour = current_time('Y-m-d-H');
        $month = current_time('Y-m');

        $entry = [
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'module' => $module,
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens' => $usage['total_tokens'] ?? 0,
            'time' => time(),
        ];

        // 多维度索引
        $this->usage['daily'][$date][] = $entry;
        $this->usage['hourly'][$hour][] = $entry;
        $this->usage['monthly'][$month][] = $entry;
        $this->usage['by_user'][$userId][] = $entry;
        $this->usage['by_provider'][$provider][] = $entry;

        // 滑动窗口: 每天只保留最近30天
        $this->pruneOld('daily', 30);
        $this->pruneOld('hourly', 72);   // 72小时
        $this->pruneOld('monthly', 12);  // 12月

        // 持久化 (异步, 避免每次写)
        if (count($this->usage['daily'][$date] ?? []) % 10 === 0) {
            update_option(LINKED3_OPTION_PREFIX . 'token_usage', $this->usage, false);
        }

        linked3_dispatch('linked3.ai.token.consumed', $entry);
    }

    /**
     * 获取用户今日 token 用量。
     */
    public function getTodayUsage(int $userId): int {
        $date = current_time('Y-m-d');
        $total = 0;
        foreach ($this->usage['daily'][$date] ?? [] as $e) {
            if ($e['user_id'] === $userId) {
                $total += $e['total_tokens'];
            }
        }
        return $total;
    }

    /**
     * 获取用户月度用量。
     */
    public function getMonthlyUsage(int $userId): int {
        $month = current_time('Y-m');
        $total = 0;
        foreach ($this->usage['monthly'][$month] ?? [] as $e) {
            if ($e['user_id'] === $userId) {
                $total += $e['total_tokens'];
            }
        }
        return $total;
    }

    /**
     * 获取按 Provider 统计。
     */
    public function getByProvider(string $provider, string $period = 'daily'): int {
        $key = $period;
        $date = $period === 'daily' ? current_time('Y-m-d') : current_time('Y-m');
        $total = 0;
        foreach ($this->usage[$key][$date] ?? [] as $e) {
            if ($e['provider'] === $provider) {
                $total += $e['total_tokens'];
            }
        }
        return $total;
    }

    /**
     * 获取成本估算 (按 provider 单价)。
     */
    public function estimateCost(int $userId, string $period = 'monthly'): float {
        $prices = [  // 每 1K token 价格 (USD)
            'gpt-4o' => 0.005,
            'gpt-4o-mini' => 0.00015,
            'deepseek' => 0.001,
            'siliconflow' => 0.0005,
            'zhipu' => 0.002,
        ];

        $date = $period === 'monthly' ? current_time('Y-m') : current_time('Y-m-d');
        $cost = 0;
        foreach ($this->usage[$period][$date] ?? [] as $e) {
            if ($e['user_id'] !== $userId) continue;
            $price = $prices[$e['provider']] ?? 0.001;
            $cost += ($e['total_tokens'] / 1000) * $price;
        }
        return round($cost, 4);
    }

    /**
     * 清理过期数据。
     */
    private function pruneOld(string $type, int $keepCount): void {
        if (!isset($this->usage[$type])) return;
        $keys = array_keys($this->usage[$type]);
        sort($keys);
        while (count($keys) > $keepCount) {
            $old = array_shift($keys);
            unset($this->usage[$type][$old]);
        }
    }

    /**
     * 强制持久化。
     */
    public function flush(): void {
        update_option(LINKED3_OPTION_PREFIX . 'token_usage', $this->usage, false);
    }
}
