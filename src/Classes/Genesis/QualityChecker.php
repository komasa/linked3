<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * QualityChecker — G8 extraction.
 * @since 27.13.0
 */
class QualityChecker
{
    public static function pqs_check_tiered(array $shot_data, int $retry_count = 0): array
    {
        // 先跑完整13维
        $full = self::pqs_check($shot_data);
        $dims = $full['dimensions'];

        // 拆分核心/次要
        $core_dims = [];
        $warn_dims = [];

        // 核心维度1: prompt_non_empty (自定义, 不在原13维中)
        $prompt = (string)($shot_data['prompt'] ?? '');
        $core_dims['prompt_non_empty'] = [
            'passed' => strlen(trim($prompt)) > 0,
            'score'  => strlen(trim($prompt)) > 0 ? 100 : 0,
            'msg'    => strlen(trim($prompt)) > 0 ? 'prompt 非空' : 'prompt 为空, 无法生成图像',
        ];

        // 核心维度2: visual_consistency (从 system_color + visual_ratio 派生)
        $sys_passed = !empty($dims['system_color']['passed']);
        $vr_passed  = !empty($dims['visual_ratio']['passed']);
        $core_dims['visual_consistency'] = [
            'passed' => $sys_passed || $vr_passed,
            'score'  => ($sys_passed ? 50 : 0) + ($vr_passed ? 50 : 0),
            'msg'    => ($sys_passed && $vr_passed) ? '视觉系统完备' : '视觉系统不完整 (色彩或比例缺失)',
        ];

        // 核心维度3: narrative_completeness (从 four_anchor + image_text_fit 派生)
        $anchor_passed = !empty($dims['four_anchor']['passed']);
        $core_dims['narrative_completeness'] = [
            'passed' => $anchor_passed,
            'score'  => $dims['four_anchor']['score'] ?? 0,
            'msg'    => $anchor_passed ? '叙事锚点完整' : '叙事锚点缺失 (subject/location/action/mood)',
        ];

        // 核心维度4: character_consistency (从 seed_refs 派生)
        $seed_refs = $shot_data['seed_refs'] ?? [];
        $char_seeds = $shot_data['meta']['character_seeds'] ?? [];
        $has_char = !empty($seed_refs) || !empty($char_seeds);
        $core_dims['character_consistency'] = [
            'passed' => $has_char,
            'score'  => $has_char ? 100 : 0,
            'msg'    => $has_char ? '角色 SEED 已引用' : '未引用角色 SEED, 角色可能漂移',
        ];

        // 核心维度5: skeleton_matched (从 scene_type/skeleton 派生)
        $scene_type = $shot_data['scene_type'] ?? '';
        $skeleton = $shot_data['skeleton'] ?? '';
        $has_skeleton = !empty($scene_type) || !empty($skeleton);
        $core_dims['skeleton_matched'] = [
            'passed' => $has_skeleton,
            'score'  => $has_skeleton ? 100 : 0,
            'msg'    => $has_skeleton ? '骨架已匹配' : '骨架未匹配, 三轴路由可能失败',
        ];

        // 次要维度: 从原13维中取8维
        foreach (self::WARN_DIMS as $key) {
            if (isset($dims[$key])) {
                $warn_dims[$key] = $dims[$key];
            }
        }

        $core_passed = true;
        foreach ($core_dims as $d) {
            if (empty($d['passed'])) { $core_passed = false; break; }
        }
        $warn_failed = count(array_filter($warn_dims, fn($d) => empty($d['passed'])));
        $should_retry = !$core_passed && $retry_count < self::MAX_RETRY;

        // 综合分: 核心5维权重70%, 次要8维权重30%
        $core_score = 0;
        foreach ($core_dims as $d) $core_score += ($d['score'] ?? 0);
        $core_score = count($core_dims) > 0 ? $core_score / count($core_dims) : 0;

        $warn_score = 0;
        foreach ($warn_dims as $d) $warn_score += ($d['score'] ?? 0);
        $warn_score = count($warn_dims) > 0 ? $warn_score / count($warn_dims) : 0;

        $overall = round($core_score * 0.7 + $warn_score * 0.3, 1);

        // 结构化日志
        if (class_exists('\Linked3\Classes\Genesis\GenesisLogger')) {
            $panel_id = $shot_data['panel_id'] ?? ($shot_data['id'] ?? 'unknown');
            GenesisLogger::stage('pqs', sprintf(
                'PQS分层校验 panel=%s core=%s warn_failed=%d retry=%d/%d score=%.1f',
                $panel_id,
                $core_passed ? 'PASS' : 'FAIL',
                $warn_failed,
                $retry_count,
                self::MAX_RETRY,
                $overall
            ), [
                'panel_id' => $panel_id,
                'core_passed' => $core_passed,
                'warn_failed' => $warn_failed,
                'retry_count' => $retry_count,
                'should_retry' => $should_retry,
            ]);
        }

        return [
            'core_dims'     => $core_dims,
            'warn_dims'     => $warn_dims,
            'full_dims'     => $dims,  // 保留完整13维供兼容
            'core_passed'   => $core_passed,
            'warn_count'    => $warn_failed,
            'should_retry'  => $should_retry,
            'retry_count'   => $retry_count,
            'overall_score' => $overall,
            'passed'        => $core_passed && $warn_failed <= 3,
        ];
    }

    public static function validate_with_retry(array $shot_data, callable $retry_callback): array
    {
        $retry = 0;
        $current = $shot_data;

        while (true) {
            $result = self::pqs_check_tiered($current, $retry);

            if ($result['core_passed'] || !$result['should_retry']) {
                // 核心通过 OR 回流耗尽
                if (!$result['core_passed'] && $retry >= self::MAX_RETRY) {
                    $result['error_code'] = 'E_PQS_RETRY_EXHAUST';
                    $result['error_msg'] = sprintf('核心维度回流 %d 次仍未通过', self::MAX_RETRY);
                }
                return $result;
            }

            // 需要回流: 获取修复建议
            $fix_suggestions = self::pqs_fix_suggest($result, $current);
            $current = call_user_func($retry_callback, $current, $fix_suggestions);
            $retry++;
        }
    }

    public static function pqs_fix_suggest(array $pqs_result, array $shot_data): array
    {
        $dims = $pqs_result['dimensions'] ?? $pqs_result['full_dims'] ?? [];
        $suggestions = [];

        $layerMap = [
            'visual_ratio'      => 'META',
            'system_color'      => 'META',
            'endpoint_choice'   => 'META',
            'footer_choice'     => 'META',
            'diagram_choice'    => 'META',
            'cognitive_6'       => 'META',
            'density_4'         => 'META',
            'image_text_fit'    => 'Script',
            'vertical_16'       => 'Script',
            'followup_choice'   => 'Script',
            'relation_encoding' => 'Script',
            'three_layer_depth' => 'Validation',
            'four_anchor'       => 'Validation',
        ];

        $fixSnippets = [
            'visual_ratio'      => 'Append "--ar 2:3" (or "9:16") to the prompt; add "wide negative margin, framed border" to declare frame/breathing space.',
            'system_color'      => 'Inject color palette token, e.g. "color palette: warm amber + deep teal, low saturation, cinematic endpoint silhouette".',
            'endpoint_choice'   => 'Declare visual endpoint: "endpoint: a lone silhouette facing the rising sun on the horizon".',
            'footer_choice'     => 'Add footer/signature line: "footer: brand watermark bottom-right, no other text".',
            'diagram_choice'    => 'Select diagram type from Diagram Registry, e.g. "diagram_type: mind_map" or "flowchart".',
            'cognitive_6'       => 'Set cognitive_level: one of [remember|understand|apply|analyze|evaluate|create].',
            'density_4'         => 'Set density: one of [low|mid|high|ultra]. Default "mid" for narrative shots.',
            'image_text_fit'    => 'Align dialogue with action_en: ensure the on-screen caption is the literal subject of action_en. Regenerate fp_core if mismatched.',
            'vertical_16'       => 'Trim dialogue to ≤16 Chinese characters. Split long captions into 2 shots.',
            'followup_choice'   => 'Add followup/CTA field: "followup: 下期预告 / 评论区聊聊" to close the engagement loop.',
            'relation_encoding' => 'Inject spatial relations: "subject left-of center, secondary element behind, background gradient above horizon".',
            'three_layer_depth' => 'Rebuild via Prompt_Assembler::assemble() — META/Script/Validation all three layers must be non-empty.',
            'four_anchor'       => 'Fill fp_core{who/where/action_en} and script.emotion — all 4 anchors required.',
        ];

        foreach ($dims as $key => $d) {
            if (!empty($d['passed'])) continue;
            $layer = $layerMap[$key] ?? 'META';
            $suggestions[] = [
                'layer'         => $layer,
                'dimension'     => $key,
                'issue'         => $d['msg'] ?? '未通过',
                'fix_snippet'   => $fixSnippets[$key] ?? '请人工补充该维度所需字段',
                'apply_action'  => sprintf('Re-run %s layer build with the fix snippet, then re-invoke pqs_check().', $layer),
            ];
        }

        return $suggestions;
    }

    public static function batch_consistency_check(array $shots): array
    {
        $n = count($shots);
        $issues = [];

        // 角色跨镜一致性
        $charAppearances = [];
        foreach ($shots as $i => $s) {
            $cs_list = $s['meta']['character_seeds'] ?? [];
            foreach ($cs_list as $cs) {
                $sid = $cs['seed_id'] ?? '';
                if (!$sid) continue;
                $vd = $cs['visual_dna'] ?? [];
                $hash = md5(wp_json_encode($vd));
                $charAppearances[$sid][$i] = $hash;
            }
        }
        $char_issues = [];
        foreach ($charAppearances as $sid => $hashes) {
            $unique = array_unique(array_values($hashes));
            if (count($unique) > 1) {
                $char_issues[] = sprintf('角色 %s 在镜 %s 出现 VisualDNA 不一致 (%d 种变体)', $sid, implode(',', array_keys($hashes)), count($unique));
            }
        }
        $char_score = $n > 0 && empty($char_issues) ? 100 : (empty($charAppearances) ? 80 : max(0, 100 - count($char_issues) * 20));

        // 色调弧线连贯性
        $colors = [];
        foreach ($shots as $s) {
            $colors[] = strtolower((string)($s['meta']['color'] ?? $s['color'] ?? ''));
        }
        $color_issues = [];
        for ($i = 1; $i < $n; $i++) {
            $prev = self::color_family($colors[$i - 1]);
            $curr = self::color_family($colors[$i]);
            if ($prev !== $curr && $prev !== 'unknown' && $curr !== 'unknown') {
                $color_issues[] = sprintf('镜 %d→%d 色调弧线突变: %s → %s', $i, $i+1, $prev, $curr);
            }
        }
        $color_score = $n > 0 && empty($color_issues) ? 100 : max(0, 100 - count($color_issues) * 15);

        // 情绪弧线合理性
        $emotions = [];
        foreach ($shots as $s) {
            $emotions[] = strtolower((string)($s['script']['emotion'] ?? $s['emotion'] ?? ''));
        }
        $emotion_issues = [];
        for ($i = 1; $i < $n; $i++) {
            $prev = self::emotion_polarity($emotions[$i - 1]);
            $curr = self::emotion_polarity($emotions[$i]);
            if ($prev !== 'unknown' && $curr !== 'unknown' && abs($prev - $curr) >= 2) {
                $emotion_issues[] = sprintf('镜 %d→%d 情绪弧线跳跃: %s(%d) → %s(%d)', $i, $i+1, $emotions[$i-1], $prev, $emotions[$i], $curr);
            }
        }
        $emotion_score = $n > 0 && empty($emotion_issues) ? 100 : max(0, 100 - count($emotion_issues) * 10);

        // 签名图形出现率
        $sig_count = 0;
        foreach ($shots as $s) {
            $sig = $s['meta']['signature'] ?? '';
            if (!empty($sig)) $sig_count++;
        }
        $sig_rate = $n > 0 ? round($sig_count / $n * 100, 1) : 0;
        $sig_score = $sig_rate >= 80 ? 100 : (int) $sig_rate;

        $issues = array_merge($char_issues, $color_issues, $emotion_issues);

        return [
            'character_consistency' => ['score' => $char_score, 'issues' => $char_issues],
            'color_arc'             => ['score' => $color_score, 'issues' => $color_issues],
            'emotion_arc'           => ['score' => $emotion_score, 'issues' => $emotion_issues],
            'signature_rate'        => ['score' => $sig_score, 'occurrence' => $sig_count, 'total' => $n],
            'issues'                => $issues,
        ];
    }

}
