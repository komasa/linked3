<?php
/**
 * Linked3 Neng-Suo Structure v12.1.0
 *
 * 李善友2026大课「能所结构」融入 — 能知约束字段引擎
 *
 * 来源: V18 道篇2.4「李善友能所结构融入」
 *       李善友2026大课完整笔记 — 能所结构（能知×所知×能所合一）
 *
 * 能所结构原义 (李善友):
 *   能知 = 知道内容的能力/主体 (看一朵花时"看"的能力)
 *   所知 = 被知的内容/对象 (花的颜色、形状、香味)
 *   能所合一 = 能知与所知同时生灭 (没有"花"就没有"看花的人")
 *
 * 视觉系统映射 (V18):
 *   能知 = 看图的人 (读者/设计师/AI) → Script层 (阅读路径/情绪曲线/转场节奏)
 *   所知 = 图的内容 (Prompt/画面/信息) → META层 (Brand/Signature/Color/Density)
 *   能所合一 = 图与人同时被定义 → Validation层 (8维度+13维校验)
 *
 * 核心能力:
 *   1. build_neng_constraint(): 构建能知约束字段 (Reader-State/Mode/Expectation)
 *   2. validate_neng_suo(): 校验能所匹配度 (能知约束 vs 所知内容)
 *   3. inject_into_prompt(): 将能知约束注入Prompt (META层后/Script层前)
 *
 * @package Linked3\Classes\OS
 * @since 12.1.0
 * @version 12.1.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Capability Lock (能锁结构)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/class-linked3-neng-suo-structure.php
 * Original class: Linked3_Neng_Suo_Structure
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Capability_Lock {

    /**
     * 能知状态枚举 (Reader-State)
     * 来源: V18 道篇2.4
     */
    const READER_STATES = [
        'curious'    => ['label' => '好奇', 'desc' => '用户带着问题来，想找到答案', 'best_for' => 'T1法律科普'],
        'anxious'    => ['label' => '焦虑', 'desc' => '用户遇到问题，担心后果', 'best_for' => 'T3法律警示'],
        'confused'   => ['label' => '困惑', 'desc' => '用户不懂术语，需要大白话', 'best_for' => 'T2案例分析'],
        'seeking'    => ['label' => '求助', 'desc' => '用户需要具体解决方案', 'best_for' => 'T4共情答疑'],
        'comparing'  => ['label' => '比较', 'desc' => '用户在多个选项中抉择', 'best_for' => 'T5避坑提示'],
        'preparing'  => ['label' => '准备', 'desc' => '用户即将行动，需要清单', 'best_for' => 'T6法庭出庭'],
        'connecting' => ['label' => '连接', 'desc' => '用户想了解创作者', 'best_for' => 'T7品牌互动'],
    ];

    /**
     * 能知模式枚举 (Reader-Mode)
     */
    const READER_MODES = [
        'skimming'   => ['label' => '浏览', 'desc' => '3秒扫一眼，只看标题和图', 'attention_span' => 3],
        'scanning'   => ['label' => '扫读', 'desc' => '15秒翻9页，找关键信息', 'attention_span' => 15],
        'reading'    => ['label' => '精读', 'desc' => '1分钟读全文，理解逻辑', 'attention_span' => 60],
        'studying'   => ['label' => '研读', 'desc' => '5分钟深读，收藏备用', 'attention_span' => 300],
        'acting'     => ['label' => '行动', 'desc' => '看完即行动，私信咨询', 'attention_span' => 600],
    ];

    /**
     * 能知期望枚举 (Reader-Expectation)
     */
    const READER_EXPECTATIONS = [
        'answer'     => ['label' => '求答案', 'desc' => '想得到一个明确的结论'],
        'method'     => ['label' => '求方法', 'desc' => '想得到可执行的步骤'],
        'case'       => ['label' => '求案例', 'desc' => '想看到真实的判例'],
        'template'   => ['label' => '求模板', 'desc' => '想下载可复用的模板'],
        'emotion'    => ['label' => '求共鸣', 'desc' => '想获得情绪上的认同'],
        'warning'    => ['label' => '求警示', 'desc' => '想避免踩坑'],
    ];

    /**
     * 构建能知约束字段
     *
     * @param string $reader_state 能知状态 (curious/anxious/confused/...)
     * @param string $reader_mode 能知模式 (skimming/scanning/reading/...)
     * @param string $reader_expectation 能知期望 (answer/method/case/...)
     * @return array 能知约束字段
     */
    public static function build_neng_constraint(
        string $reader_state = 'curious',
        string $reader_mode = 'scanning',
        string $reader_expectation = 'answer'
    ): array {
        $state = self::READER_STATES[$reader_state] ?? self::READER_STATES['curious'];
        $mode = self::READER_MODES[$reader_mode] ?? self::READER_MODES['scanning'];
        $expectation = self::READER_EXPECTATIONS[$reader_expectation] ?? self::READER_EXPECTATIONS['answer'];

        return [
            'reader_state' => $reader_state,
            'reader_state_label' => $state['label'],
            'reader_state_desc' => $state['desc'],
            'reader_mode' => $reader_mode,
            'reader_mode_label' => $mode['label'],
            'reader_mode_desc' => $mode['desc'],
            'attention_span_sec' => $mode['attention_span'],
            'reader_expectation' => $reader_expectation,
            'reader_expectation_label' => $expectation['label'],
            'reader_expectation_desc' => $expectation['desc'],
            'best_for_content_type' => $state['best_for'],
        ];
    }

    /**
     * 将能知约束注入Prompt (META层后/Script层前)
     *
     * @param string $prompt 原始Prompt
     * @param array $neng_constraint 能知约束字段
     * @return string 注入后的Prompt
     */
    public static function inject_into_prompt(string $prompt, array $neng_constraint): string {
        if (empty($neng_constraint)) return $prompt;

        $constraint_block = "\n# 能知约束（V18李善友能所结构）\n";
        $constraint_block .= "Reader-State: {$neng_constraint['reader_state']} ({$neng_constraint['reader_state_label']} - {$neng_constraint['reader_state_desc']})\n";
        $constraint_block .= "Reader-Mode: {$neng_constraint['reader_mode']} ({$neng_constraint['reader_mode_label']} - 注意力{$neng_constraint['attention_span_sec']}秒)\n";
        $constraint_block .= "Reader-Expectation: {$neng_constraint['reader_expectation']} ({$neng_constraint['reader_expectation_label']} - {$neng_constraint['reader_expectation_desc']})\n";
        $constraint_block .= "Best-For: {$neng_constraint['best_for_content_type']}\n\n";

        // 在 # Script: 之前插入能知约束
        if (preg_match('/(# Script:)/', $prompt)) {
            return preg_replace('/(# Script:)/', $constraint_block . '$1', $prompt, 1);
        }

        // 如果没有Script标记，追加到末尾
        return $prompt . $constraint_block;
    }

    /**
     * 校验能所匹配度
     * 能知约束 vs 所知内容 是否匹配
     *
     * @param array $neng_constraint 能知约束
     * @param array $suo_content 所知内容 (META+Script)
     * @return array 校验结果 {matched, score, issues}
     */
    public static function validate_neng_suo(array $neng_constraint, array $suo_content): array {
        $issues = [];
        $score = 100;

        // 检查1: 内容类型是否匹配能知状态
        $content_type = $suo_content['content_type'] ?? '';
        $best_for = $neng_constraint['best_for_content_type'] ?? '';
        if (!empty($content_type) && !empty($best_for) && $content_type !== $best_for) {
            $issues[] = "内容类型({$content_type})与能知状态最佳匹配({$best_for})不一致";
            $score -= 20;
        }

        // 检查2: 注意力时长 vs 信息密度
        $attention_span = $neng_constraint['attention_span_sec'] ?? 15;
        $density = $suo_content['density_level'] ?? '标准版';
        if ($attention_span <= 3 && $density === '超密集版') {
            $issues[] = "注意力{$attention_span}秒但信息密度超密集，用户无法消化";
            $score -= 25;
        }
        if ($attention_span >= 300 && $density === '稀疏版') {
            $issues[] = "注意力{$attention_span}秒但信息密度稀疏，用户觉得没价值";
            $score -= 15;
        }

        // 检查3: 能知期望 vs Endpoint类型
        $expectation = $neng_constraint['reader_expectation'] ?? 'answer';
        $endpoint = $suo_content['endpoint_type'] ?? 'E1';
        $expectation_endpoint_map = [
            'answer' => ['E2', 'E5'],      // 求答案 → 决策型/追问型
            'method' => ['E1', 'E2'],      // 求方法 → 实战型/决策型
            'case' => ['E3', 'E4'],        // 求案例 → 诊断型/共鸣型
            'template' => ['E1'],          // 求模板 → 实战型
            'emotion' => ['E4', 'E6'],     // 求共鸣 → 共鸣型/觉察型
            'warning' => ['E3', 'E1'],     // 求警示 → 诊断型/实战型
        ];
        $expected_endpoints = $expectation_endpoint_map[$expectation] ?? ['E1'];
        if (!in_array($endpoint, $expected_endpoints)) {
            $issues[] = "能知期望({$expectation})建议Endpoint({$expected_endpoints[0]})，实际为({$endpoint})";
            $score -= 15;
        }

        $score = max(0, $score);
        return [
            'matched' => $score >= 60,
            'score' => $score,
            'issues' => $issues,
            'neng_suo_unity' => $score >= 80 ? '能所合一' : ($score >= 60 ? '部分匹配' : '能所割裂'),
        ];
    }

    /**
     * 从内容类型自动推导能知约束
     *
     * @param string $content_type T1-T7内容类型
     * @return array 能知约束
     */
    public static function derive_from_content_type(string $content_type): array {
        $map = [
            'T1' => ['state' => 'curious', 'mode' => 'reading', 'expectation' => 'answer'],
            'T2' => ['state' => 'confused', 'mode' => 'reading', 'expectation' => 'case'],
            'T3' => ['state' => 'anxious', 'mode' => 'scanning', 'expectation' => 'warning'],
            'T4' => ['state' => 'seeking', 'mode' => 'reading', 'expectation' => 'method'],
            'T5' => ['state' => 'comparing', 'mode' => 'scanning', 'expectation' => 'warning'],
            'T6' => ['state' => 'preparing', 'mode' => 'studying', 'expectation' => 'template'],
            'T7' => ['state' => 'connecting', 'mode' => 'skimming', 'expectation' => 'emotion'],
        ];
        $config = $map[$content_type] ?? $map['T1'];
        return self::build_neng_constraint($config['state'], $config['mode'], $config['expectation']);
    }

    /**
     * 获取所有选项 (供前端渲染)
     */
    public static function get_all_options(): array {
        return [
            'reader_states' => self::READER_STATES,
            'reader_modes' => self::READER_MODES,
            'reader_expectations' => self::READER_EXPECTATIONS,
        ];
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.1.0',
            'reader_states_count' => count(self::READER_STATES),
            'reader_modes_count' => count(self::READER_MODES),
            'reader_expectations_count' => count(self::READER_EXPECTATIONS),
            'source' => 'V18道篇2.4 + 李善友2026大课能所结构',
        ];
    }
}
