<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisPatchStage2
{
        public static function ajax_seed_generate_full() : mixed { return GenesisPatchStage3::ajax_seed_generate_full(); }

        public static function ajax_v9_stage1_fixed() : mixed { return GenesisPatchStage3::ajax_v9_stage1_fixed(); }

    public static function ajax_v9_stage2_fixed() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $beatsJson = wp_unslash($_POST['beats'] ?? '[]');
        $charactersJson = wp_unslash($_POST['characters'] ?? '[]');
        $theme = sanitize_text_field($_POST['theme'] ?? '');
        $skeletonId = sanitize_text_field($_POST['skeleton_id'] ?? 'documentary_photo');
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));
        $genMode2 = sanitize_text_field($_POST['gen_mode'] ?? 'local');

        $beats = json_decode($beatsJson, true);
        if (!is_array($beats) || empty($beats)) wp_send_json_error(['message' => __('beats 数据为空', 'linked3-ai')]);
        $characters = json_decode($charactersJson, true);
        if (!is_array($characters)) $characters = [];

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        @ini_set('display_errors', '0');
        $prev_er_v1006 = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        if (function_exists('ob_start')) ob_start();

        try {
            [$styleKeywords, $styleNegative] = self::loadStyleConfig($styleId);

            $fpExtractor = class_exists('\Linked3\Classes\Genesis\FPExtractor') ? new \FPExtractor() : null;
            $assembler = class_exists('\Linked3\Classes\Genesis\PromptAssembler') ? new \PromptAssembler() : null;
            $fpUseAi = ($genMode2 === 'ai');

            $results = [];
            $pqsScores = [];
            $beatErrors = [];

            foreach ($beats as $i => $beat) {
                try {
                    $result = self::processBeat(
                        $beat, $i, $skeletonId, $seedRefs, $platform, $styleId,
                        $fpExtractor, $assembler, $fpUseAi,
                        $styleKeywords, $styleNegative
                    );
                    if (!empty($result['pqs_score'])) $pqsScores[] = $result['pqs_score'];
                    unset($result['pqs_score']);
                    $results[] = $result;
                } catch (\Throwable $eBeat) {
                    $beatErrors[] = ['beat_index' => $i, 'error' => $eBeat->getMessage()];
                }
            }

            $batchReport = null;
            if (class_exists('\Linked3\Classes\Genesis\QualityLoop') && method_exists('\Linked3\Classes\Genesis\QualityLoop', 'batch_consistency_check') && count($results) > 1) {
                try { $batchReport = \QualityLoop::batch_consistency_check($results); } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
            }

            if (function_exists('ob_end_clean')) @ob_end_clean();

            wp_send_json_success([
                'panels' => $results,
                'total_panels' => count($results),
                'total_scenes' => count(array_unique(array_column($results, 'scene_id'))),
                'style' => $styleId, 'platform' => $platform, 'mode' => 'v9_integrated',
                'skeleton_id' => $skeletonId, 'seed_refs' => $seedRefs, 'theme' => $theme,
                'characters' => $characters,
                'pqs_avg' => count($pqsScores) ? round(array_sum($pqsScores) / count($pqsScores), 1) : 0,
                'batch_report' => $batchReport, 'beat_errors' => $beatErrors,
                'beats_requested' => count($beats),
                'style_keywords_injected' => !empty($styleKeywords),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) @ob_end_clean();
            wp_send_json_error(['message' => __('Stage 2 失败: ', 'linked3-ai') . $e->getMessage(), 'file' => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '']);
        } finally {
            error_reporting($prev_er_v1006);
        }
    }

    /**
     * 加载画风配置 (keywords + negative).
     *
     * @return array{0:string,1:string}
     */
    private static function loadStyleConfig(string $styleId): array
    {
        $styleKeywords = '';
        $styleNegative = '';
        if (class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
            $styleConfig = \GenesisStyleEngine::load($styleId);
            $styleKeywords = $styleConfig['meta_prompt'] ?? ($styleConfig['prompt_keywords'] ?? '');
            $styleNegative = $styleConfig['negative_keywords'] ?? '';
        }
        return [$styleKeywords, $styleNegative];
    }

    /**
     * 处理单个 beat: FP 提取 → ShotData 组装 → 风格注入 → PQS 校验.
     *
     * @return array 结果数组 (含临时 pqs_score 字段)
     */
    private static function processBeat(
        array $beat, int $i, string $skeletonId, array $seedRefs, string $platform, string $styleId,
        ?object $fpExtractor, ?object $assembler, bool $fpUseAi,
        string $styleKeywords, string $styleNegative
    ): array {
        $beatText = $beat['text'] ?? $beat['action'] ?? '';
        $emotion = $beat['emotion'] ?? 'neutral';
        $arcPosition = $beat['arc_position'] ?? 'development';

        $beatText = self::filter_web_noise($beatText);

        // FP 语义核提取 (含 fallback)
        $fpCore = self::extractFpCore($fpExtractor, $beatText, $emotion, $fpUseAi, $styleId);

        // 情绪色彩
        $color = '';
        if (class_exists('\Linked3\Classes\Genesis\StoryPipeline')) {
            try { $color = \StoryPipeline::emotion_to_color($emotion); } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        }

        $shotData = self::buildShotData($beat, $skeletonId, $seedRefs, $arcPosition, $emotion, $platform, $fpCore);
        $assembled = self::assembleShot($assembler, $shotData, $fpCore, $beatText, $color, $arcPosition, $emotion, $skeletonId, $seedRefs);
        $shotData['meta'] = $assembled['meta'];
        $shotData['script'] = $assembled['script'];
        $shotData['validation'] = $assembled['validation'];

        $finalPrompt = self::applyStyleKeywords($assembled['prompt'], $styleKeywords, $styleNegative);

        // PQS 校验
        $pqsScore = 0;
        $pqsInfo = null;
        if (class_exists('\Linked3\Classes\Genesis\QualityLoop') && method_exists('\Linked3\Classes\Genesis\QualityLoop', 'pqs_check')) {
            try {
                $shotData['prompt'] = $finalPrompt;
                $pqs = \QualityLoop::pqs_check($shotData);
                if (!empty($pqs['overall_score'])) $pqsScore = $pqs['overall_score'];
                $pqsInfo = ['passed' => $pqs['passed_count'] ?? 0, 'total' => $pqs['total'] ?? 13];
            } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        }

        return [
            'panel_id' => 'P' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
            'scene_id' => $skeletonId,
            'location' => $fpCore['where'] ?? '',
            'action' => $fpCore['what'] ?? mb_substr($beatText, 0, 50),
            'mood' => $emotion,
            'shot' => $shotData['shot'], 'angle' => $shotData['angle'], 'comp' => $shotData['comp'],
            'prompt_en' => $finalPrompt,
            'prompt_with_params' => $finalPrompt,
            'prompt_source' => 'ai',
            'core_info' => $fpCore['who'] ?? '',
            'plot_point' => $fpCore['theme'] ?? '',
            'character_details' => [],
            'pqs' => $pqsInfo,
            'pqs_score' => $pqsScore,
        ];
    }

    /**
     * FP 语义核提取 (含 AI 失败降级 + 本地兜底).
     */
    private static function extractFpCore(?object $fpExtractor, string $beatText, string $emotion, bool $fpUseAi, string $styleId): ?array
    {
        $fpCore = null;
        if ($fpExtractor) {
            try {
                $fpCore = $fpExtractor->extract($beatText, ['use_ai' => $fpUseAi, 'style_name' => $styleId]);
            } catch (\Throwable $eFP) {
                try { $fpCore = $fpExtractor->extract($beatText, ['use_ai' => false, 'style_name' => $styleId]); }
                catch (\Throwable $eFP2) { $fpCore = null; }
            }
        }
        if (!$fpCore || empty($fpCore['action_en']) || $fpCore['action_en'] === 'a candid scene depicting daily life, natural atmosphere, authentic moment') {
            $fpCore = self::enhanced_local_extract($beatText, $emotion);
        }
        return $fpCore;
    }

    /**
     * 构建 shotData (镜头元信息).
     */
    private static function buildShotData(array $beat, string $skeletonId, array $seedRefs, string $arcPosition, string $emotion, string $platform, ?array $fpCore): array
    {
        return [
            'scene_type' => $skeletonId,
            'seed_refs' => $seedRefs,
            'arc_position' => $arcPosition,
            'dialogue' => $beat['dialogue'] ?? '',
            'emotion' => $emotion,
            'transition' => 'cut',
            'pacing' => 'medium',
            'fp_core' => $fpCore,
            'shot' => $beat['shot'] ?? '中景',
            'angle' => $beat['angle'] ?? '平视',
            'comp' => $beat['comp'] ?? '三分法',
            'platform' => $platform,
            'location' => $fpCore['where'] ?? '',
        ];
    }

    /**
     * PromptAssembler 组装 (含 fallback).
     */
    private static function assembleShot(?object $assembler, array $shotData, ?array $fpCore, string $beatText, string $color, string $arcPosition, string $emotion, string $skeletonId, array $seedRefs): array
    {
        $fallback = ['prompt' => $fpCore['action_en'] ?? $beatText, 'meta' => [], 'script' => [], 'validation' => []];
        if ($assembler) {
            try { return $assembler->assemble($shotData); }
            catch (\Throwable $eAsm) { return $fallback; }
        }
        // 填充 meta/script/validation defaults
        if (empty($fallback['meta'])) {
            $fallback['meta'] = [
                'color' => $color, 'signature' => 'Linked3 AI', 'endpoint' => $skeletonId,
                'character_seeds' => !empty($seedRefs) ? [['seed_id' => $seedRefs[0] ?? '']] : [],
                'mood' => $emotion, 'cognitive_level' => 'understand', 'density' => 'mid',
                'diagram_type' => 'photo', 'footer' => 'Linked3 AI',
            ];
        }
        if (empty($fallback['script'])) {
            $fallback['script'] = ['arc_position' => $arcPosition, 'dialogue' => '', 'emotion' => $emotion, 'transition' => 'cut', 'pacing' => 'medium', 'followup' => ''];
        }
        if (empty($fallback['validation'])) {
            $fallback['validation'] = ['visual_consistency' => true, 'narrative_completeness' => true];
        }
        return $fallback;
    }

    /**
     * 风格关键词 + 负面关键词注入.
     */
    private static function applyStyleKeywords(string $prompt, string $styleKeywords, string $styleNegative): string
    {
        if (!empty($styleKeywords) && stripos($prompt, $styleKeywords) === false) {
            $prompt .= '. ' . $styleKeywords . '.';
        }
        if (!empty($styleNegative) && stripos($prompt, '--no') === false) {
            $prompt .= ' --no ' . $styleNegative;
        }
        return $prompt;
    }

}
