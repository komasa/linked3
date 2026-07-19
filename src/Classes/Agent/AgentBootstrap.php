<?php

declare(strict_types=1);
/**
 * Linked3 Agent Bootstrap — v5.5.0 启动
 *
 * @package Linked3\Agent
 * @since 5.5.0
 */
namespace Linked3\Classes\Agent;

if (!defined('ABSPATH')) exit;

class AgentBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();

        // 注册编排器
        $container->set('agent.orchestrator', fn() => AgentOrchestrator::instance());

        // 注册质量门控
        $container->set('agent.quality_gate', function() {
            $gate = new \Linked3\Classes\Agent\Quality\AgentQualityGate();
            // 注册默认质量检查
            $gate->registerCheck('content_length', fn($c) => min(100, strlen($c['content'] ?? '') / 10), 50);
            $gate->registerCheck('seo_score', fn($c) => $c['seo_score'] ?? 60, 60);
            return $gate;
        });

        // 注册调度器
        $container->set('agent.scheduler', fn() => AgentScheduler::instance());

        // 注册内容管线工作流
        $orchestrator = $container->get('agent.orchestrator');
        $orchestrator->register('content_pipeline', new \Linked3\Classes\Agent\Workflow\AgentContentPipeline());

        linked3_dispatch('linked3.agent.boot', ['version' => LINKED3_VERSION]);
    }
}
