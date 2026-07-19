<?php
/**
 * Quality Loop v8.4.0 — M5 质量闭环 (v10.0.1 优化版)
 *
 * v10.0.1 优化 (基于 /genesis 链路深度公理分析):
 *   公理1: 信息熵减 — 13维收敛为核心5维必检 + 次要8维告警
 *   公理2: 系统降维 — 回流次数从无限收敛到最多2次, 避免死循环
 *
 * 优化项:
 *   1. 核心维度 CORE_DIMS (5维): 失败必须回流修复
 *   2. 次要维度 WARN_DIMS (8维): 失败仅告警, 不阻断
 *   3. 回流收敛: max_retry=2, 超过返回当前结果
 *   4. 结构化日志: 每次校验/回流记录到 linked3_genesis_log
 *   5. 统一错误码: E_PQS_CORE_FAILED, E_PQS_RETRY_EXHAUST
 *
 * 兼容性: 保留原 pqs_check() 方法签名, 新增 pqs_check_tiered() 分层校验
 *
 * @package Linked3\Genesis
 * @since 8.3.0
 * @version 8.4.0 (v10.0.1 优化)
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Quality_Loop
{
    /**
     * PQS 13 维清单 (key => label)
     * 顺序对齐 V14: 视觉(比例/边框/留白) / 咬合(图文位置) / 系统(色彩/终点/质感)
     *               / 竖屏(16字原则) / 3层深度 / 4层锚点
     *               / 图示选择 / Endpoint选择 / Footer选择 / 追问选择
     *               / 关系编码 / 认知6级 / 密度4档
     */
    public const PQS_DIMENSIONS = [
        'visual_ratio'       => '视觉(比例/边框/留白)',
        'image_text_fit'     => '咬合(图文位置)',
        'system_color'       => '系统(色彩/终点/质感)',
        'vertical_16'        => '竖屏(16字原则)',
        'three_layer_depth'  => '3层深度',
        'four_anchor'        => '4层锚点',
        'diagram_choice'     => '图示选择',
        'endpoint_choice'    => 'Endpoint选择',
        'footer_choice'      => 'Footer选择',
        'followup_choice'    => '追问选择',
        'relation_encoding'  => '关系编码',
        'cognitive_6'        => '认知6级',
        'density_4'          => '密度4档',
    ];

    /**
     * v10.0.1 新增: 核心维度 (5维) — 失败必须回流修复
     * 这5维覆盖了分镜的"最低可用性":
     *   - prompt_non_empty: prompt非空 (最基本)
     *   - visual_consistency: 视觉一致性 (角色/场景/风格)
     *   - narrative_completeness: 叙事完整性 (有动作/有锚点)
     *   - character_consistency: 角色一致性 (seed引用)
     *   - skeleton_matched: 骨架匹配 (三轴路由命中)
     */
    public const CORE_DIMS = [
        'prompt_non_empty',
        'visual_consistency',
        'narrative_completeness',
        'character_consistency',
        'skeleton_matched',
    ];

    /**
     * v10.0.1 新增: 次要维度 (8维) — 失败仅告警, 不阻断
     * 这8维是"质量增强项", 缺失不影响基本可用性
     */
    public const WARN_DIMS = [
        'visual_ratio', 'image_text_fit', 'system_color', 'vertical_16',
        'three_layer_depth', 'four_anchor', 'relation_encoding', 'density_4',
    ];

    /**
     * v10.0.1 新增: 最大回流次数
     */
    public const MAX_RETRY = 2;

    /**
     * v10.0.1 新增: 分层校验 — 核心维度必检 + 次要维度告警
     *
     * @param array $shot_data 同 pqs_check() 入参
     * @return array {
     *   core_dims:     array [key => {passed, score, msg}]  (5维)
     *   warn_dims:     array [key => {passed, score, msg}]  (8维)
     *   core_passed:   bool (核心5维全过)
     *   warn_count:    int  (次要维度失败数)
     *   should_retry:  bool (核心失败且未达MAX_RETRY)
     *   retry_count:   int  (已回流次数, 由调用方传入累加)
     *   overall_score: float
     *   passed:        bool (core_passed && warn_count <= 3)
     * }
     */
        public static function pqs_check_tiered(array $shot_data, int $retry_count = 0) : mixed { return Linked3_Quality_Checker::pqs_check_tiered($shot_data, $retry_count); }

    /**
     * v10.0.1 新增: 带回流的质量循环
     *
     * @param array    $shot_data      原始分镜数据
     * @param callable $retry_callback 回流回调, 接收 $shot_data + $fix_suggestions, 返回新的 $shot_data
     * @return array 最终校验结果 (含 retry_count)
     */
        public static function validate_with_retry(array $shot_data, callable $retry_callback) : mixed { return Linked3_Quality_Checker::validate_with_retry($shot_data, $retry_callback); }

    /**
     * 5.1 PQS 13 维校验 (保留原方法, 内部不变)
     *
     * @param array $shot_data {
     *   prompt:      string (最终英文 prompt)
     *   meta:        array  (L1 META 层)
     *   script:      array  (L2 Script 层)
     *   validation:  array  (L3 Validation 层)
     *   dialogue:    string (画面文案, ≤16 字)
     *   fp_core:     array  (FP 真剥骨语义核)
     *   seed_refs:   array  (Seed 引用列表)
     *   shot/angle/comp: string (镜头/角度/构图)
     *   scene_type:  string
     *   diagram_type:string (图示类型)
     *   endpoint:    string (终点/silhouette)
     *   footer:      string (页脚/署名)
     *   followup:    string (追问/CTA)
     *   cognitive_level: string (remember/understand/apply/analyze/evaluate/create)
     *   density:     string (low/mid/high/ultra)
     * }
     * @return array {
     *   dimensions:    array [key => {passed, score, msg}]
     *   passed_count:  int
     *   total:         int (固定 13)
     *   overall_score: float (passed_count/13 * 100)
     *   passed:        bool (overall_score >= 80 视为整镜通过)
     * }
     */
    public static function pqs_check(array $shot_data): array
    {
        $prompt      = (string)($shot_data['prompt'] ?? '');
        $meta        = (array)($shot_data['meta'] ?? []);
        $script      = (array)($shot_data['script'] ?? []);
        $validation  = (array)($shot_data['validation'] ?? []);
        $dialogue    = (string)($shot_data['dialogue'] ?? $script['dialogue'] ?? '');
        $fp_core     = (array)($shot_data['fp_core'] ?? []);
        $seed_refs   = (array)($shot_data['seed_refs'] ?? []);
        $character_seeds = $meta['character_seeds'] ?? [];

        $dims = [];

        // 1. 视觉(比例/边框/留白) — prompt 含竖屏比例 9:16 或 --ar 2:3
        $has_ratio = (preg_match('#9\s*:\s*16#', $prompt) || preg_match('#--ar\s*2\s*:\s*3#i', $prompt) || preg_match('#--ar\s*9\s*:\s*16#i', $prompt));
        $has_frame = (bool) preg_match('#\b(frame|border|margin|padding|留白|边框)\b#i', $prompt);
        if ($has_ratio && $has_frame) {
            $dims['visual_ratio'] = ['passed' => true, 'score' => 100, 'msg' => '比例(竖屏)+边框/留白均声明'];
        } elseif ($has_ratio) {
            $dims['visual_ratio'] = ['passed' => true, 'score' => 70, 'msg' => '竖屏比例已声明, 边框/留白未显式表达'];
        } else {
            $dims['visual_ratio'] = ['passed' => false, 'score' => 0, 'msg' => '缺少 --ar 2:3 或 9:16 竖屏比例'];
        }

        // 2. 咬合(图文位置) — dialogue 存在且与画面描述对齐 (action_en 引用 dialogue 关键词)
        $action_en = (string)($fp_core['action_en'] ?? '');
        if ($dialogue === '') {
            $dims['image_text_fit'] = ['passed' => true, 'score' => 60, 'msg' => '无画面文案, 咬合校验降级通过'];
        } elseif ($action_en !== '' && self::text_overlap($dialogue, $action_en)) {
            $dims['image_text_fit'] = ['passed' => true, 'score' => 90, 'msg' => 'dialogue 与画面描述语义对齐'];
        } else {
            $dims['image_text_fit'] = ['passed' => false, 'score' => 30, 'msg' => 'dialogue 与画面 action_en 未对齐, 咬合断裂'];
        }

        // 3. 系统(色彩/终点/质感) — color palette 存在 (兼顾 endpoint + texture)
        $color = $meta['color'] ?? ($shot_data['color'] ?? '');
        $endpoint = $shot_data['endpoint'] ?? ($meta['endpoint'] ?? '');
        $texture = (bool) preg_match('#\b(ink|watercolor|oil painting|photography|cinematic|pencil|charcoal|neon|grain)\b#i', $prompt);
        $sub = 0;
        if (!empty($color)) $sub += 40;
        if (!empty($endpoint)) $sub += 30;
        if ($texture) $sub += 30;
        $dims['system_color'] = [
            'passed' => $sub >= 60,
            'score'  => $sub,
            'msg'    => $sub >= 60 ? '色彩/终点/质感系统完备' : '色彩系统缺失, 需补 color palette / endpoint / texture',
        ];

        // 4. 竖屏(16字原则) — 关键文案 dialogue ≤16 字
        $dialogue_len = mb_strlen(trim($dialogue));
        if ($dialogue === '') {
            $dims['vertical_16'] = ['passed' => true, 'score' => 80, 'msg' => '无画面文案, 默认通过'];
        } elseif ($dialogue_len <= 16) {
            $dims['vertical_16'] = ['passed' => true, 'score' => 100, 'msg' => sprintf('画面文案 %d 字 ≤16, 符合竖屏 16 字原则', $dialogue_len)];
        } else {
            $dims['vertical_16'] = ['passed' => false, 'score' => max(0, 100 - ($dialogue_len - 16) * 5), 'msg' => sprintf('画面文案 %d 字超过 16 字, 需精简', $dialogue_len)];
        }

        // 5. 3层深度 — META / Script / Validation 三层都存在且非空
        $layer_count = 0;
        if (!empty($meta) && (!empty($meta['signature']) || !empty($meta['color']) || !empty($meta['character_seeds']))) $layer_count++;
        if (!empty($script) && (!empty($script['arc_position']) || !empty($script['dialogue']) || !empty($script['emotion']))) $layer_count++;
        if (!empty($validation) && (!empty($validation['visual_consistency']) || !empty($validation['narrative_completeness']))) $layer_count++;
        $dims['three_layer_depth'] = [
            'passed' => $layer_count === 3,
            'score'  => (int) ($layer_count / 3 * 100),
            'msg'    => sprintf('三层深度命中 %d/3 (META/Script/Validation)', $layer_count),
        ];

        // 6. 4层锚点 — subject / location / action / mood 全部声明
        $anchors = [
            'subject'  => !empty($fp_core['who']) || !empty($shot_data['subject']),
            'location' => !empty($fp_core['where']) || !empty($shot_data['location']),
            'action'   => !empty($fp_core['action_en']) || !empty($fp_core['what']),
            'mood'     => !empty($script['emotion']) || !empty($shot_data['emotion']) || !empty($meta['mood']),
        ];
        $anchor_count = count(array_filter($anchors));
        $dims['four_anchor'] = [
            'passed' => $anchor_count === 4,
            'score'  => (int) ($anchor_count / 4 * 100),
            'msg'    => sprintf('4 层锚点命中 %d/4 (subject/location/action/mood), 缺失: %s', $anchor_count, implode(',', array_keys(array_filter($anchors, fn($v) => !$v))) ?: '无'),
        ];

        // 7. 图示选择 — diagram_type 已声明
        $diagram_type = $shot_data['diagram_type'] ?? ($meta['diagram_type'] ?? '');
        $dims['diagram_choice'] = [
            'passed' => !empty($diagram_type),
            'score'  => !empty($diagram_type) ? 100 : 0,
            'msg'    => !empty($diagram_type) ? sprintf('图示类型: %s', $diagram_type) : '未选择图示类型 (建议从 Diagram Registry 中选定)',
        ];

        // 8. Endpoint选择 — endpoint 已声明 (终点/silhouette)
        $dims['endpoint_choice'] = [
            'passed' => !empty($endpoint),
            'score'  => !empty($endpoint) ? 100 : 0,
            'msg'    => !empty($endpoint) ? sprintf('Endpoint: %s', $endpoint) : '未选择 Endpoint (终点/silhouette)',
        ];

        // 9. Footer选择 — footer/署名 已声明
        $footer = $shot_data['footer'] ?? ($meta['footer'] ?? '');
        $dims['footer_choice'] = [
            'passed' => !empty($footer) || !empty($meta['signature']),
            'score'  => (!empty($footer) ? 70 : 0) + (!empty($meta['signature']) ? 30 : 0),
            'msg'    => !empty($footer) ? sprintf('Footer: %s', $footer) : (!empty($meta['signature']) ? '已有 signature 兜底' : '未选择 Footer (建议补署名/Logo 文案)'),
        ];

        // 10. 追问选择 — followup/CTA 已声明
        $followup = $shot_data['followup'] ?? ($script['followup'] ?? '');
        $dims['followup_choice'] = [
            'passed' => !empty($followup),
            'score'  => !empty($followup) ? 100 : 0,
            'msg'    => !empty($followup) ? sprintf('追问/CTA: %s', $followup) : '未声明追问/CTA, 互动闭环不完整',
        ];

        // 11. 关系编码 — prompt 含位置/关系关键词
        $has_relation = (bool) preg_match('#\b(left of|right of|next to|behind|in front of|above|below|beside|between|surrounding|in the center)\b#i', $prompt);
        $dims['relation_encoding'] = [
            'passed' => $has_relation,
            'score'  => $has_relation ? 100 : 40,
            'msg'    => $has_relation ? '关系编码已声明 (位置/前后/邻接)' : '缺少关系编码, 视觉元素间位置关系不明确',
        ];

        // 12. 认知6级 — cognitive_level 命中 Bloom 6 级之一
        $valid_levels = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
        $cog = strtolower((string)($shot_data['cognitive_level'] ?? ($meta['cognitive_level'] ?? '')));
        $dims['cognitive_6'] = [
            'passed' => in_array($cog, $valid_levels, true),
            'score'  => in_array($cog, $valid_levels, true) ? 100 : 0,
            'msg'    => in_array($cog, $valid_levels, true) ? sprintf('认知层级: %s', $cog) : '未声明认知层级 (remember/understand/apply/analyze/evaluate/create)',
        ];

        // 13. 密度4档 — density 命中 4 档之一
        $valid_density = ['low', 'mid', 'high', 'ultra'];
        $density = strtolower((string)($shot_data['density'] ?? ($meta['density'] ?? '')));
        if (!in_array($density, $valid_density, true)) {
            $plen = str_word_count($prompt);
            if ($plen < 30) $density = 'low';
            elseif ($plen < 60) $density = 'mid';
            elseif ($plen < 100) $density = 'high';
            else $density = 'ultra';
        }
        $dims['density_4'] = [
            'passed' => true,
            'score'  => in_array($density, ['low', 'mid'], true) ? 100 : 70,
            'msg'    => sprintf('信息密度: %s', $density),
        ];

        $passed_count = count(array_filter($dims, fn($d) => !empty($d['passed'])));
        $total = count(self::PQS_DIMENSIONS);
        $overall = round($passed_count / $total * 100, 1);

        return [
            'dimensions'    => $dims,
            'passed_count'  => $passed_count,
            'total'         => $total,
            'overall_score' => $overall,
            'passed'        => $overall >= 80.0,
        ];
    }

    /**
     * 5.1.3 PQS 修复建议引擎 (S15) — 保留原方法
     */
        public static function pqs_fix_suggest(array $pqs_result, array $shot_data) : mixed { return Linked3_Quality_Checker::pqs_fix_suggest($pqs_result, $shot_data); }

    /**
     * 5.2 批量分镜一致性校验报告 (S20) — 保留原方法
     */
        public static function batch_consistency_check(array $shots) : mixed { return Linked3_Quality_Checker::batch_consistency_check($shots); }

    // ===== 工具方法 =====

    private static function text_overlap(string $a, string $b): bool
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ($a === '' || $b === '') return false;
        $len = min(mb_strlen($a), mb_strlen($b));
        if ($len < 2) return false;
        for ($i = 0; $i <= mb_strlen($a) - 2; $i++) {
            $sub = mb_substr($a, $i, 2);
            if (mb_strpos($b, $sub) !== false) return true;
        }
        return false;
    }

    private static function color_family(string $color): string
    {
        $color = strtolower($color);
        if (strpos($color, 'warm') !== false || strpos($color, 'amber') !== false || strpos($color, 'orange') !== false || strpos($color, 'red') !== false) return 'warm';
        if (strpos($color, 'cool') !== false || strpos($color, 'blue') !== false || strpos($color, 'teal') !== false || strpos($color, 'cyan') !== false) return 'cool';
        if (strpos($color, 'dark') !== false || strpos($color, 'black') !== false || strpos($color, 'shadow') !== false) return 'dark';
        if (strpos($color, 'bright') !== false || strpos($color, 'white') !== false || strpos($color, 'light') !== false) return 'bright';
        return 'unknown';
    }

    private static function emotion_polarity(string $emotion): int
    {
        $emotion = strtolower(trim($emotion));
        $map = [
            'joy' => 2, 'happy' => 2, 'excited' => 2, 'hope' => 2, 'love' => 2,
            'calm' => 1, 'neutral' => 0, 'curious' => 1,
            'sad' => -1, 'melancholy' => -1, 'tense' => -1, 'fear' => -2,
            'angry' => -2, 'horror' => -2, 'despair' => -2,
        ];
        return $map[$emotion] ?? 0;
    }
}
