<?php

declare(strict_types=1);
/**
 * Linked3 AI Pipeline Bootstrap — v5.6.0.8~0.9
 *
 * v5.6.0.8: Prompt 缓存 (相同 prompt+model 短期内复用结果)
 * v5.6.0.9: AI 管线统一注册到容器
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.9
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

/**
 * Linked3 Prompt Cache — v5.6.0.8
 * 缓存 AI 响应 (相同 prompt+model 在 TTL 内复用)
 */
class PromptCache {
    private static ?PromptCache $instance = null;
    private int $ttl = 3600; // 1小时
    private int $maxItems = 500;

    public static function instance(): PromptCache {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function get(string $prompt, string $model): ?string {
        $key = $this->key($prompt, $model);
        $cache = get_option(LINKED3_OPTION_PREFIX . 'prompt_cache', []);
        if (isset($cache[$key])) {
            if ((time() - $cache[$key]['time']) < $this->ttl) {
                return $cache[$key]['content'];
            }
            unset($cache[$key]);
        }
        return null;
    }

    public function set(string $prompt, string $model, string $content): void {
        $key = $this->key($prompt, $model);
        $cache = get_option(LINKED3_OPTION_PREFIX . 'prompt_cache', []);
        $cache[$key] = ['content' => $content, 'time' => time()];

        // LRU 清理
        if (count($cache) > $this->maxItems) {
            uasort($cache, fn($a, $b) => $a['time'] <=> $b['time']);
            $cache = array_slice($cache, -$this->maxItems, null, true);
        }
        update_option(LINKED3_OPTION_PREFIX . 'prompt_cache', $cache, false);
    }

    private function key(string $prompt, string $model): string {
        return md5($model . '|' . $prompt);
    }
}


