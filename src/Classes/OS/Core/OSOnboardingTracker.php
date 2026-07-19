<?php

declare(strict_types=1);
/**
 * Linked3 Ru Liu Tracker v12.4.0
 *
 * 入流四状态100天追踪器
 *
 * 来源: V18道篇2.7 + 李善友2026大课入流四状态
 *
 * 入流四状态 (李善友):
 *   看见 → 相信 → 承担 → 放大
 *
 * @package Linked3\Classes\OS
 * @since 12.4.0
 * @version 12.4.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Onboarding Tracker (入流追踪)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/RuLiuTracker.php
 * Original class: Linked3_Ru_Liu_Tracker
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSOnboardingTracker {

    /**
     * 入流四状态定义
     */
    const RU_LIU_STATES = [
        'seeing' => [
            'key' => 'seeing',
            'label' => '看见',
            'label_en' => 'seeing',
            'desc' => '看见时代背后的结构性变化',
            'day_range' => [1, 7],
            'core_action' => '刷对标5账号',
        ],
        'believing' => [
            'key' => 'believing',
            'label' => '相信',
            'label_en' => 'believing',
            'desc' => '相信这个变化不是短期热点',
            'day_range' => [8, 33],
            'core_action' => '定6类SEED+30篇验证',
        ],
        'carrying' => [
            'key' => 'carrying',
            'label' => '承担',
            'label_en' => 'carrying',
            'desc' => '愿意用自己的现实行动承接它',
            'day_range' => [34, 66],
            'core_action' => '30篇爆款+私域',
        ],
        'amplifying' => [
            'key' => 'amplifying',
            'label' => '放大',
            'label_en' => 'amplifying',
            'desc' => '让更多人因为他的存在而看见',
            'day_range' => [67, 100],
            'core_action' => '5级变现+4平台分发',
        ],
    ];

    /**
     * 状态-天数映射
     */
    const STATE_DAY_MAP = [
        1 => 'seeing', 7 => 'seeing',
        8 => 'believing', 33 => 'believing',
        34 => 'carrying', 66 => 'carrying',
        67 => 'amplifying', 100 => 'amplifying',
    ];

    /**
     * 状态-动作映射
     */
    const STATE_ACTIONS = [
        'seeing' => ['刷对标5账号', '注册4平台', '账号五件套', 'CharacterSeed定义', 'IDSeed定义'],
        'believing' => ['30篇内容', 'Day33校验', '收录检测', '首单成交', '私域50人'],
        'carrying' => ['30篇爆款', '私域200人', '成交30单', 'Day66校验', '月收入2000'],
        'amplifying' => ['5级变现', '私域500人', '成交100单', 'Day100校验', '月收入5000+'],
    ];

    /**
     * 获取入流四状态
     */
    public static function get_ru_liu_states(): array {
        return self::RU_LIU_STATES;
    }

    /**
     * 根据天数获取当前状态
     */
    public static function get_current_state(int $day): array {
        foreach (self::RU_LIU_STATES as $key => $state) {
            if ($day >= $state['day_range'][0] && $day <= $state['day_range'][1]) {
                return ['state' => $key] + $state;
            }
        }
        return ['state' => 'unknown', 'label' => '未知', 'desc' => ''];
    }

    /**
     * 获取状态对应动作
     */
    public static function get_state_actions(string $state): array {
        return self::STATE_ACTIONS[$state] ?? [];
    }

    /**
     * 计算状态进度
     */
    public static function calculate_state_progress(int $day): array {
        $current = self::get_current_state($day);
        $range = $current['day_range'] ?? [1, 1];
        $total = $range[1] - $range[0] + 1;
        $passed = $day - $range[0] + 1;
        return [
            'current_state' => $current['state'],
            'state_label' => $current['label'],
            'day_in_state' => $passed,
            'state_total_days' => $total,
            'state_progress_pct' => round($passed / $total * 100, 1),
            'overall_day' => $day,
            'overall_progress_pct' => round($day / 100 * 100, 1),
        ];
    }

    /**
     * 获取100天计划
     */
    public static function get_100day_plan(): array {
        $plan = [];
        foreach (self::RU_LIU_STATES as $key => $state) {
            $plan[] = [
                'state' => $key,
                'label' => $state['label'],
                'day_range' => $state['day_range'],
                'core_action' => $state['core_action'],
                'actions' => self::STATE_ACTIONS[$key] ?? [],
            ];
        }
        return $plan;
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.4.0',
            'states_count' => count(self::RU_LIU_STATES),
            'total_days' => 100,
            'source' => 'V18道篇2.7 + 李善友2026大课入流四状态',
        ];
    }

    /**
     * v18复审修复E1/S1 [公理α: H↓ 消除"计划与我不符"不确定性]
     * 根据用户输入(职业/赛道/起号目标)生成定制化100天计划
     *
     * @param array $input {profession, track, goal, platform, current_day}
     * @return array 定制化计划 (含每周排期 + 个性化动作)
     */
    public static function generate_personalized_plan(array $input): array {
        $profession = sanitize_text_field($input['profession'] ?? '');
        $track = sanitize_text_field($input['track'] ?? '');
        $goal = sanitize_text_field($input['goal'] ?? '');
        $platform = sanitize_text_field($input['platform'] ?? '公众号');
        $current_day = max(1, min(100, intval($input['current_day'] ?? 1)));

        // 基础计划
        $base_plan = self::get_100day_plan();

        // 个性化注入: 每个状态的动作前缀加上用户职业/赛道
        $context = '';
        if ($profession) $context .= $profession . '·';
        if ($track) $context .= $track . '·';
        $context = rtrim($context, '·');

        $personalized = [];
        foreach ($base_plan as $phase) {
            $state_key = $phase['state'];
            $actions = $phase['actions'];

            // 按状态个性化动作描述
            $personalized_actions = [];
            foreach ($actions as $action) {
                $personalized_actions[] = $context
                    ? str_replace(
                        ['对标5账号', '30篇内容', '30篇爆款', '5级变现'],
                        [$profession ?: '对标' . '5个' . $track . '账号', '30篇' . $track . '内容', '30篇' . $track . '爆款', '5级' . $track . '变现'],
                        $action
                    )
                    : $action;
            }

            // 每周排期 (按状态天数范围生成周计划)
            $day_start = $phase['day_range'][0];
            $day_end = $phase['day_range'][1];
            $weeks = [];
            $week_num = 1;
            for ($d = $day_start; $d <= $day_end; $d += 7) {
                $week_end = min($d + 6, $day_end);
                $weeks[] = [
                    'week' => $week_num,
                    'day_range' => [$d, $week_end],
                    'focus' => $personalized_actions[min($week_num - 1, count($personalized_actions) - 1)] ?? $phase['core_action'],
                    'platform' => $platform,
                ];
                $week_num++;
            }

            $personalized[] = [
                'state' => $state_key,
                'label' => $phase['label'],
                'day_range' => $phase['day_range'],
                'desc' => self::RU_LIU_STATES[$state_key]['desc'] ?? '',
                'core_action' => $phase['core_action'],
                'actions' => $personalized_actions,
                'weeks' => $weeks,
                'is_current' => ($current_day >= $day_start && $current_day <= $day_end),
            ];
        }

        // 当前进度
        $progress = self::calculate_state_progress($current_day);

        return [
            'user_input' => [
                'profession' => $profession,
                'track' => $track,
                'goal' => $goal,
                'platform' => $platform,
                'current_day' => $current_day,
            ],
            'current_state' => $progress,
            'phases' => $personalized,
            'total_weeks' => 15,
            'goal' => $goal,
            'generated_at' => current_time('mysql'),
        ];
    }
}
