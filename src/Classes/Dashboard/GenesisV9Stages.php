<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
/**
 * GenesisV9Stages — G8 extraction.
 * @since 27.13.0
 */
class GenesisV9Stages
{
    public static function ajax_genesis_v9_stage1()
    : void {
        // Phase 1: Security & input validation
        $input = self::validate_stage1_request();

        // Phase 2: Auto-detect scene axes if needed
        $axes = self::auto_detect_scene_axes($input['script'], $input['l1_type'], $input['l2_column'], $input['l3_soul']);

        // Phase 3: Setup environment
        $env = self::setup_stage2_environment();

        try {
            // Phase 4: Parse story
            $story = self::parse_story($input['script'], $input['gen_mode']);
            $beats = $story['beats'];
            $characters = $story['characters'];
            $theme = $story['theme'];
            $storySource = $story['source'];

            // Phase 5: Fallback to sentence split if beats too few
            if (count($beats) < 2) {
                $split = self::split_script_to_beats($input['script']);
                $beats = $split['beats'];
                $storySource = 'sentence_split';
            }

            // Phase 6: Cap beats at 15
            $beats = array_slice($beats, 0, 15);

            // Phase 7: Route skeleton
            $skeletonId = self::route_skeleton($axes['l1_type'], $axes['l2_column'], $axes['l3_soul']);

            // Phase 8: Auto-create seeds from characters
            $seedData = self::auto_create_seeds($characters);

            // Phase 9: Send response
            if (function_exists('ob_end_clean')) @ob_end_clean();
            wp_send_json_success([
                'beats'          => $beats,
                'characters'     => $characters,
                'theme'          => $theme,
                'skeleton_id'    => $skeletonId,
                'l1_type'        => $axes['l1_type'],
                'l2_column'      => $axes['l2_column'],
                'l3_soul'        => $axes['l3_soul'],
                'story_source'   => $storySource,
                'beat_count'     => count($beats),
                'auto_seeds'     => $seedData['seeds'],
                'auto_seed_refs' => $seedData['refs'],
            ]);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) @ob_end_clean();
            wp_send_json_error([
                'message' => __('Stage 1 失败: ', 'linked3-ai') . $e->getMessage(),
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($env['prev_er']);
            if ($env['prev_de'] !== false) @ini_set('display_errors', $env['prev_de']);
        }
    }

    /**
     * Validate Stage 1 request: security + input sanitization.
     */
    private static function validate_stage1_request() : array {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);
        }
        return [
            'script'    => $script,
            'style'     => sanitize_text_field($_POST['style'] ?? 'documentary_photo'),
            'l1_type'   => sanitize_text_field($_POST['l1_type'] ?? 'auto'),
            'l2_column' => sanitize_text_field($_POST['l2_column'] ?? 'auto'),
            'l3_soul'   => sanitize_text_field($_POST['l3_soul'] ?? 'auto'),
            'gen_mode'  => sanitize_text_field($_POST['gen_mode'] ?? 'local'),
        ];
    }

    /**
     * Auto-detect scene axes (L1/L2/L3) from script if set to 'auto'.
     */
    private static function auto_detect_scene_axes(string $script, string $l1_type, string $l2_column, string $l3_soul) : array {
        if (($l1_type !== 'auto' && $l2_column !== 'auto' && $l3_soul !== 'auto') || !class_exists('\\SceneAxis')) {
            return ['l1_type' => $l1_type, 'l2_column' => $l2_column, 'l3_soul' => $l3_soul];
        }
        try {
            $detected = \SceneAxis::auto_detect_from_script($script);
            if ($l1_type === 'auto') $l1_type = $detected['l1'] ?? 'city_life';
            if ($l2_column === 'auto') $l2_column = $detected['l2'] ?? 'documentary';
            if ($l3_soul === 'auto') $l3_soul = $detected['l3'] ?? 'none';
        } catch (\Throwable $e) {
            if ($l1_type === 'auto') $l1_type = 'city_life';
            if ($l2_column === 'auto') $l2_column = 'documentary';
            if ($l3_soul === 'auto') $l3_soul = 'none';
        }
        return ['l1_type' => $l1_type, 'l2_column' => $l2_column, 'l3_soul' => $l3_soul];
    }

    /**
     * Parse story via StoryPipeline (with AI/local fallback).
     */
    private static function parse_story(string $script, string $genMode) : array {
        $useAi = ($genMode === 'ai' || $genMode === 'hybrid');
        $scriptTrimmed = mb_substr($script, 0, 4000);
        $storyData = null;
        $storySource = 'none';

        if (class_exists('\\StoryPipeline')) {
            try {
                $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => $useAi]);
                $storySource = $useAi ? 'ai' : 'local';
            } catch (\Throwable $eL) {
                try {
                    $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => false]);
                    $storySource = $useAi ? 'local_fallback' : 'local';
                } catch (\Throwable $eL2) {
                    $storySource = 'failed';
                }
            }
        }

        return [
            'beats'      => $storyData['beats'] ?? [],
            'characters' => $storyData['characters'] ?? [],
            'theme'      => $storyData['theme'] ?? '',
            'source'     => $storySource,
        ];
    }

    /**
     * Fallback: split script to beats by sentence boundaries.
     */
    private static function split_script_to_beats(string $script) : array {
        $scriptTrimmed = mb_substr($script, 0, 4000);
        $sentences = preg_split('/(?<=[。！？\n.!?])\s*/u', $scriptTrimmed);
        $sentences = array_filter($sentences, fn($s) => mb_strlen(trim($s)) >= 15);
        $sentences = array_values($sentences);

        $seen = [];
        $unique = [];
        foreach ($sentences as $s) {
            $key = mb_substr(trim($s), 0, 30);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = trim($s);
            }
        }

        $beats = [];
        foreach (array_slice($unique, 0, 15) as $i => $s) {
            $beats[] = [
                'id'           => $i + 1,
                'text'         => mb_substr($s, 0, 200),
                'emotion'      => 'neutral',
                'arc_position' => $i < 3 ? '开场' : ($i >= count($unique) - 2 ? '收尾' : '发展'),
            ];
        }
        return ['beats' => $beats];
    }

    /**
     * Route skeleton ID from scene axes.
     */
    private static function route_skeleton(string $l1_type, string $l2_column, string $l3_soul) : string {
        if (class_exists('\\SceneAxis')) {
            try {
                return \SceneAxis::route_skeleton($l1_type, $l2_column, $l3_soul);
            } catch (\Throwable $e) {}
        }
        return 'documentary_photo';
    }

    /**
     * Auto-create seed CPTs from extracted characters.
     */
    private static function auto_create_seeds(array $characters) : array {
        $autoSeeds = [];
        $autoSeedRefs = [];

        if (!class_exists('\\Linked3\\Classes\\Dashboard\\GenesisSeedCPT') || !method_exists('\\Linked3\\Classes\\Dashboard\\GenesisSeedCPT', 'create')) {
            return ['seeds' => [], 'refs' => []];
        }
        if (empty($characters) || !is_array($characters)) {
            return ['seeds' => [], 'refs' => []];
        }

        foreach ($characters as $idx => $char) {
            $charName = is_array($char) ? ($char['name'] ?? $char['id'] ?? '') : (string)$char;
            if (empty($charName) || mb_strlen($charName) < 2) continue;
            if (preg_match('/[的了是在和与把被将让给向到从为对按据依由这那之其每各又且]/u', $charName)) continue;
            if (preg_match('/^\d+$/', $charName)) continue;

            $seedId = 'C' . ($idx + 1) . '_' . mb_substr($charName, 0, 4, 'UTF-8') . '_v1';
            $existing = null;
            try { $existing = \GenesisSeedCPT::get_by_seed_id($seedId); } catch (\Throwable $e) {}

            if (!$existing) {
                try {
                    $visualDna = [
                        'face'       => $char['face'] ?? $char['appearance'] ?? '',
                        'body'       => $char['body'] ?? '',
                        'costume'    => $char['costume'] ?? $char['clothing'] ?? '',
                        'accessory'  => $char['accessory'] ?? '',
                        'proportion' => $char['proportion'] ?? '',
                    ];
                    $personalityDna = [
                        'personality'  => $char['personality'] ?? '',
                        'role'         => $char['role'] ?? '',
                        'speech_style' => $char['speech_style'] ?? '',
                    ];
                    \GenesisSeedCPT::create([
                        'seed_id'     => $seedId,
                        'name'        => $charName,
                        'visual_dna'  => $visualDna,
                        'personality' => $personalityDna,
                        'source'      => 'auto_extract_v9',
                    ]);
                    $autoSeeds[] = ['seed_id' => $seedId, 'name' => $charName];
                } catch (\Throwable $e) {}
            }
            $autoSeedRefs[] = $seedId;
        }

        return ['seeds' => $autoSeeds, 'refs' => $autoSeedRefs];
    }


    public static function ajax_genesis_v9_stage2()
    : void {
        // Phase 1: Security & input validation
        $input = self::validate_stage2_request();

        // Phase 2: Parse JSON inputs
        $parsed = self::parse_stage2_inputs($input);
        if ($parsed === null) return; // error already sent

        // Phase 3: Setup environment
        $env = self::setup_stage2_environment();

        try {
            // Phase 4: Initialize tools
            $tools = self::init_stage2_tools($parsed['gen_mode']);

            // Phase 5: Process beats
            $beatResults = self::process_all_beats(
                $parsed['beats'],
                $parsed['characters'],
                $parsed['seed_refs'],
                $parsed['skeleton_id'],
                $parsed['style_id'],
                $parsed['platform'],
                $tools['fp_extractor'],
                $tools['assembler']
            );

            if (empty($beatResults['results']) && !empty($beatResults['errors'])) {
                if (function_exists('ob_end_clean')) @ob_end_clean();
                wp_send_json_error([
                    'message' => __('所有分镜生成失败: ', 'linked3-ai') . $beatResults['errors'][0]['error'],
                    'beat_errors' => $beatResults['errors'],
                ]);
            }

            // Phase 6: Batch quality check
            $batchReport = null;
            if (class_exists('\QualityLoop') && count($beatResults['results']) > 1) {
                try { $batchReport = \QualityLoop::batch_consistency_check($beatResults['results']); } catch (\Throwable $e) {}
            }

            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }

            // Phase 7: Send response
            self::send_stage2_success(
                $beatResults['results'],
                $beatResults['pqs_scores'],
                $beatResults['errors'],
                $parsed,
                $batchReport
            );
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }
            wp_send_json_error([
                'message' => __('Stage 2 失败: ', 'linked3-ai') . $e->getMessage(),
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($env['prev_er']);
            if ($env['prev_de'] !== false) @ini_set('display_errors', $env['prev_de']);
        }
    }

    /**
     * Phase 1: Validate security and sanitize input parameters.
     *
     * @return array Sanitized input parameters
     */
    private static function validate_stage2_request() : array {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }

        return [
            'beats_json'     => wp_unslash($_POST['beats'] ?? '[]'),
            'characters_json'=> wp_unslash($_POST['characters'] ?? '[]'),
            'theme'          => sanitize_text_field($_POST['theme'] ?? ''),
            'skeleton_id'    => sanitize_text_field($_POST['skeleton_id'] ?? 'documentary_photo'),
            'style_id'       => sanitize_text_field($_POST['style'] ?? 'documentary_photo'),
            'platform'       => sanitize_text_field($_POST['platform'] ?? 'midjourney'),
            'seed_refs'      => array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? ''))),
            'gen_mode'       => sanitize_text_field($_POST['gen_mode'] ?? 'local'),
        ];
    }

    /**
     * Phase 2: Parse and validate JSON inputs.
     *
     * @param array $input Sanitized input from validate_stage2_request()
     * @return array|null Parsed data or null if error sent
     */
    private static function parse_stage2_inputs(array $input) : ?array {
        $beats = json_decode($input['beats_json'], true);
        if (!is_array($beats) || empty($beats)) {
            wp_send_json_error(['message' => __('beats 数据为空', 'linked3-ai')]);
            return null;
        }
        $characters = json_decode($input['characters_json'], true);
        if (!is_array($characters)) $characters = [];

        return [
            'beats'       => $beats,
            'characters'  => $characters,
            'theme'       => $input['theme'],
            'skeleton_id' => $input['skeleton_id'],
            'style_id'    => $input['style_id'],
            'platform'    => $input['platform'],
            'seed_refs'   => $input['seed_refs'],
            'gen_mode'    => $input['gen_mode'],
        ];
    }

    /**
     * Phase 3: Setup PHP environment (time limit, error reporting, output buffer).
     *
     * @return array Environment state for restoration
     */
    private static function setup_stage2_environment() : array {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        $prev_er = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        $prev_de = @ini_set('display_errors', '0');
        if (function_exists('ob_start')) {
            ob_start();
        }
        return ['prev_er' => $prev_er, 'prev_de' => $prev_de];
    }

    /**
     * Phase 4: Initialize FPExtractor and PromptAssembler tools.
     *
     * @param string $genMode Generation mode (local/ai)
     * @return array{fp_extractor: ?object, assembler: ?object, fp_use_ai: bool}
     */
    private static function init_stage2_tools(string $genMode) : array {
        return [
            'fp_use_ai'    => ($genMode === 'ai'),
            'fp_extractor' => class_exists('\FPExtractor') ? new \FPExtractor() : null,
            'assembler'    => class_exists('\PromptAssembler') ? new \PromptAssembler() : null,
        ];
    }

    /**
     * Phase 5: Process all beats through the FP→Assembler→PQS pipeline.
     *
     * @param array  $beats        Beat array from Stage 1
     * @param array  $characters   Character list
     * @param array  $seedRefs     Seed reference IDs
     * @param string $skeletonId   Skeleton identifier
     * @param string $styleId      Style identifier
     * @param string $platform     Target platform
     * @param ?object $fpExtractor FP extractor instance
     * @param ?object $assembler   Prompt assembler instance
     * @return array{results: array, pqs_scores: array, errors: array}
     */
    private static function process_all_beats(
        array $beats,
        array $characters,
        array $seedRefs,
        string $skeletonId,
        string $styleId,
        string $platform,
        ?object $fpExtractor,
        ?object $assembler
    ) : array {
        $fpUseAi = ($fpExtractor !== null); // determined by caller via init_stage2_tools
        $results = [];
        $pqsScores = [];
        $beatErrors = [];

        foreach ($beats as $i => $beat) {
            try {
                $shotData = self::build_shot_data(
                    $i, $beat, $skeletonId, $platform, $seedRefs,
                    $fpExtractor, $styleId
                );

                $assembled = self::assemble_prompt($assembler, $shotData, $beat);
                $emotion = $shotData['_emotion'] ?? ($beat['emotion'] ?? 'neutral');
                $shotData = self::merge_assembled_into_shot($shotData, $assembled, $skeletonId, $beat, $emotion, $seedRefs);

                $pqs = self::run_pqs_check($shotData);
                $pqsScores[] = $pqs['passed_count'] ?? 0;

                $results[] = self::build_panel_result(
                    $i, $shotData, $assembled, $beat, $characters,
                    $skeletonId, $styleId, $platform, $pqs
                );
            } catch (\Throwable $eBeat) {
                $beatErrors[] = ['beat_index' => $i, 'error' => $eBeat->getMessage()];
                if (function_exists('error_log')) {
                    error_log('[linked3 v9 stage2] Beat #' . ($i + 1) . ' failed, skipped: ' . $eBeat->getMessage());
                }
            }
        }

        return ['results' => $results, 'pqs_scores' => $pqsScores, 'errors' => $beatErrors];
    }

    /**
     * Extract FP core from beat text using FPExtractor (with fallback).
     *
     * @param string $beatText    Beat text content
     * @param string $emotion     Emotion label
     * @param ?object $fpExtractor FP extractor instance
     * @param bool   $fpUseAi     Whether to use AI mode
     * @param string $styleId     Style identifier
     * @return array FP core data
     */
    private static function extract_fp_core(string $beatText, string $emotion, ?object $fpExtractor, bool $fpUseAi, string $styleId) : array {
        $fallback = ['action_en' => 'a scene depicting daily life', 'emotion' => $emotion, 'raw' => $beatText];
        if (!$fpExtractor) return $fallback;

        try {
            return $fpExtractor->extract($beatText, ['use_ai' => $fpUseAi, 'style_name' => $styleId]);
        } catch (\Throwable $eFP) {
            try {
                return $fpExtractor->extract($beatText, ['use_ai' => false, 'style_name' => $styleId]);
            } catch (\Throwable $eFP2) {
                return array_merge($fallback, ['who' => '', 'what' => '', 'where' => '', 'when' => '', 'theme' => '']);
            }
        }
    }

    /**
     * Build shot data array from beat info.
     */
    private static function build_shot_data(
        int $i,
        array $beat,
        string $skeletonId,
        string $platform,
        array $seedRefs,
        ?object $fpExtractor,
        string $styleId
    ) : array {
        $beatText = $beat['text'] ?? $beat['action'] ?? '';
        $emotion = $beat['emotion'] ?? 'neutral';
        $arcPosition = $beat['arc_position'] ?? 'development';

        $fpUseAi = ($fpExtractor !== null) && ($styleId !== '');
        $fpCore = self::extract_fp_core($beatText, $emotion, $fpExtractor, $fpUseAi, $styleId);

        $color = '';
        if (class_exists('\StoryPipeline')) {
            try { $color = \StoryPipeline::emotion_to_color($emotion); } catch (\Throwable $e) {}
        }

        return [
            'scene_id'        => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'scene_type'      => $skeletonId,
            'seed_refs'       => $seedRefs,
            'arc_position'    => $arcPosition,
            'emotion'         => $emotion,
            'color'           => $color,
            'platform'        => $platform,
            'fp_core'         => $fpCore,
            'shot'            => $beat['shot'] ?? '中景',
            'angle'           => $beat['angle'] ?? '平视',
            'comp'            => $beat['comp'] ?? '三分法',
            'location'        => $fpCore['where'] ?? '',
            'dialogue'        => $beat['dialogue'] ?? '',
            'subject'         => $fpCore['who'] ?? '',
            'endpoint'        => $skeletonId,
            'footer'          => 'Linked3 AI',
            'followup'        => '',
            'cognitive_level' => 'understand',
            'diagram_type'    => 'photo',
            'density'         => 'mid',
            '_beat_text'      => $beatText,
            '_emotion'        => $emotion,
        ];
    }

    /**
     * Assemble prompt using PromptAssembler (with fallback).
     */
    private static function assemble_prompt(?object $assembler, array $shotData, array $beat) : array {
        $fallback = ['prompt' => $shotData['fp_core']['action_en'] ?? $shotData['_beat_text'], 'meta' => [], 'script' => [], 'validation' => []];
        if (!$assembler) return $fallback;

        try {
            return $assembler->assemble($shotData);
        } catch (\Throwable $eAsm) {
            return $fallback;
        }
    }

    /**
     * Merge assembled data into shot data, filling defaults for empty sections.
     */
    private static function merge_assembled_into_shot(
        array $shotData,
        array $assembled,
        string $skeletonId,
        array $beat,
        string $emotion,
        array $seedRefs
    ) : array {
        $color = $shotData['color'] ?? '';

        if (empty($assembled['meta'])) {
            $assembled['meta'] = [
                'color'           => $color,
                'signature'       => 'Linked3 AI',
                'endpoint'        => $skeletonId,
                'character_seeds' => !empty($seedRefs) ? [['seed_id' => $seedRefs[0] ?? '']] : [],
                'mood'            => $emotion,
                'cognitive_level' => 'understand',
                'density'         => 'mid',
                'diagram_type'    => 'photo',
                'footer'          => 'Linked3 AI',
            ];
        }
        if (empty($assembled['script'])) {
            $assembled['script'] = [
                'arc_position' => $shotData['arc_position'] ?? 'development',
                'dialogue'     => $beat['dialogue'] ?? '',
                'emotion'      => $emotion,
                'transition'   => 'cut',
                'pacing'       => 'medium',
                'followup'     => '',
            ];
        }
        if (empty($assembled['validation'])) {
            $assembled['validation'] = [
                'visual_consistency'     => true,
                'narrative_completeness' => true,
            ];
        }

        $shotData['meta'] = $assembled['meta'];
        $shotData['script'] = $assembled['script'];
        $shotData['validation'] = $assembled['validation'];
        $shotData['prompt'] = $assembled['prompt'];

        return $shotData;
    }

    /**
     * Run PQS quality check on shot data.
     */
    private static function run_pqs_check(array $shotData) : array {
        $pqs = ['passed_count' => 0, 'total' => 13];
        if (class_exists('\QualityLoop')) {
            try { $pqs = \QualityLoop::pqs_check($shotData); } catch (\Throwable $e) {}
        }
        return $pqs;
    }

    /**
     * Build final panel result array for a single beat.
     */
    private static function build_panel_result(
        int $i,
        array $shotData,
        array $assembled,
        array $beat,
        array $characters,
        string $skeletonId,
        string $styleId,
        string $platform,
        array $pqs
    ) : array {
        $beatText = $shotData['_beat_text'] ?? ($beat['text'] ?? $beat['action'] ?? '');
        $emotion = $shotData['_emotion'] ?? ($beat['emotion'] ?? 'neutral');

        return [
            'panel_id'           => 'P' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
            'scene_id'           => $shotData['scene_id'],
            'location'           => $shotData['location'],
            'action'             => $beatText,
            'mood'               => $emotion,
            'shot'               => $shotData['shot'],
            'angle'              => $shotData['angle'],
            'comp'               => $shotData['comp'],
            'characters'         => $characters,
            'prompt_en'          => $assembled['prompt'],
            'prompt_with_params' => $assembled['prompt'],
            'style'              => $styleId,
            'style_name'         => $skeletonId,
            'platform'           => $platform,
            'skeleton_id'        => $skeletonId,
            'pqs'                => ['passed' => $pqs['passed_count'] ?? 0, 'total' => 13, 'pass_rate' => (($pqs['passed_count'] ?? 0) . '/13')],
            'fp_core'            => $shotData['fp_core'],
            'meta'               => $assembled['meta'] ?? [],
            'script'             => $assembled['script'] ?? [],
            'validation'         => $assembled['validation'] ?? [],
            'prompt_source'      => 'v9_three_layer',
        ];
    }

    /**
     * Phase 7: Send success JSON response with all results.
     */
    private static function send_stage2_success(
        array $results,
        array $pqsScores,
        array $beatErrors,
        array $parsed,
        ?array $batchReport
    ) : void {
        wp_send_json_success([
            'panels'          => $results,
            'total_panels'    => count($results),
            'total_scenes'    => count(array_unique(array_column($results, 'scene_id'))),
            'style'           => $parsed['style_id'],
            'platform'        => $parsed['platform'],
            'mode'            => 'v9_integrated',
            'skeleton_id'     => $parsed['skeleton_id'],
            'seed_refs'       => $parsed['seed_refs'],
            'theme'           => $parsed['theme'],
            'characters'      => $parsed['characters'],
            'pqs_avg'         => count($pqsScores) ? round(array_sum($pqsScores) / count($pqsScores), 1) : 0,
            'batch_report'    => $batchReport,
            'beat_errors'     => $beatErrors,
            'beats_requested' => count($parsed['beats']),
        ]);
    }

}
