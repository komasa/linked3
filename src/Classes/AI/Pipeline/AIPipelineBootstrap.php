<?php

declare(strict_types=1);
/**
 * Linked3_AI_Pipeline_Bootstrap — extracted from PromptCache.php during PSR-4 migration.
 *
 * @package Linked3\Classes\AI\Pipeline

namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

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
