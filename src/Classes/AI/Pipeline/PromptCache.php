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

    public function clear(): void {
        delete_option(LINKED3_OPTION_PREFIX . 'prompt_cache');
    }

    private function key(string $prompt, string $model): string {
        return md5($model . '|' . $prompt);
    }
}

/**
 * Linked3 AI Pipeline Bootstrap — v5.6.0.9
 * 统一注册所有 AI 管线服务到 DI 容器
 */
class Linked3_AI_Pipeline_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();

        // 注册 AI 管线服务
        $container->set('ai.provider_health', fn() => ProviderHealthCheck::instance());
        $container->set('ai.failover', fn() => ProviderFailover::instance());
        $container->set('ai.token_meter', fn() => TokenMeter::instance());
        $container->set('ai.key_pool', fn() => KeyPool::instance());
        $container->set('ai.prompt_engine', fn() => PromptEngine::instance());
        $container->set('ai.content_scorer', fn() => new Linked3_Content_Quality_Scorer());
        $container->set('ai.stream', fn() => StreamOutput::instance());
        $container->set('ai.cost_reporter', fn() => new Linked3_Cost_Reporter());
        $container->set('ai.prompt_cache', fn() => PromptCache::instance());

        // 注册核心事件
        linked3_subscribe('linked3.ai.token.consumed', function(Linked3_Event $evt) {
            $container->get('logger')->debug('Token consumed', $evt->getPayload());
        });

        linked3_subscribe('linked3.ai.failover.triggered', function(Linked3_Event $evt) {
            $container->get('logger')->warning('Provider failover', $evt->getPayload());
        });

        linked3_dispatch('linked3.ai.pipeline.boot', ['version' => LINKED3_VERSION]);
    }
}
