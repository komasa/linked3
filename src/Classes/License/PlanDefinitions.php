<?php

declare(strict_types=1);
/**
 * Plan definitions — central, filterable, hot-updatable.
 *
 * EX 部门商业寻优入口:跑完 spec 后,plan_definitions 可被 A/B 测试
 * 引擎动态调整定价/配额/功能,无需发版。
 *
 * @package Linked3
 * @subpackage Classes\License
 */

namespace Linked3\Classes\License;

if (!defined('ABSPATH')) {
    exit;
}

final class PlanDefinitions
{
    /**
     * @return array<string,array> Plan slug → definition.
     */
    public static function all() : mixed {
        $defaults = [
            'free' => [
                'name'             => __('免费', 'linked3'),
                'tokens_daily'     => 50000,
                'price_monthly'    => 0,
                'price_yearly'     => 0,
                'currency'         => 'CNY',
                'max_sites'        => 1,
                'max_publish_targets' => 1,
                'max_agents'       => 1,
                'batch_per_run'    => 10,
                'providers'        => ['openai', 'deepseek'], // limited
                'modules'          => ['content_writer' => 'limited', 'chat' => 'guest_only', 'seo' => 'limited'],
                'rest_api'         => false,
                'tts_stt'          => false,
                'priority_queue'   => false,
                'sla'              => 'best-effort',
            ],
            'pro' => [
                'name'             => __('Pro', 'linked3'),
                'tokens_daily'     => 3000000,
                'price_monthly'    => 99,
                'price_yearly'     => 999,
                'currency'         => 'CNY',
                'max_sites'        => 5,
                'max_publish_targets' => 5,
                'max_agents'       => 5,
                'batch_per_run'    => 100,
                'providers'        => ['openai', 'deepseek', 'kimi', 'qwen', 'doubao', 'custom'],
                'modules'          => ['content_writer' => 'full', 'chat' => 'full', 'seo' => 'full', 'autogpt' => 'limited'],
                'rest_api'         => true,
                'tts_stt'          => false,
                'priority_queue'   => false,
                'sla'              => '24h',
            ],
            'premium' => [
                'name'             => __('Premium', 'linked3'),
                'tokens_daily'     => 50000000,
                'price_monthly'    => 299,
                'price_yearly'     => 2999,
                'currency'         => 'CNY',
                'max_sites'        => 99,
                'max_publish_targets' => -1, // unlimited
                'max_agents'       => -1,
                'batch_per_run'    => 1000,
                'providers'        => ['*'], // all incl. future
                'modules'          => ['*'],
                'rest_api'         => true,
                'tts_stt'          => true,
                'priority_queue'   => true,
                'sla'              => '2h',
            ],
        ];

        /**
         * EX 部门商业寻优:A/B 测试 + 增长黑客在此动态调整。
         * 例如:大促期间临时翻倍 Pro 配额,或降低 yearly 价格提升转化。
         */
        return (array) apply_filters('linked3/plan_definitions', $defaults);
    }

    /**
     * @param string $plan
     * @return array|null
     */
    public static function get($plan) : mixed     {
        $all = self::all();
        return $all[$plan] ?? null;
    }

    /**
     * @param string $plan
     * @param string $feature
     * @return mixed
     */
    public static function feature($plan, $feature) : mixed {
        $def = self::get($plan);
        if (!$def) {
            return null;
        }
        return $def[$feature] ?? null;
    }

    /**
     * @param string $plan
     * @param string $module
     * @return bool|string false | 'limited' | 'full'
     */
    public static function module_access($plan, $module) : mixed     {
        $def = self::get($plan);
        if (!$def) {
            return false;
        }
        $modules = $def['modules'] ?? [];
        if ($modules === ['*']) {
            return 'full';
        }
        return $modules[$module] ?? false;
    }
}
