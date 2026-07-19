<?php

namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\Core\Linked3_AI_Dispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Genesis_V9_Processor
{
    public static function ajax_genesis_generate_v9()
    : array {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $platform = sanitize_text_field($_POST['platform'] ?? 'midjourney');
        $l1_type = sanitize_text_field($_POST['l1_type'] ?? 'auto');
        $l2_column = sanitize_text_field($_POST['l2_column'] ?? 'auto');
        $l3_soul = sanitize_text_field($_POST['l3_soul'] ?? 'auto');
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));

        if (($l1_type === 'auto' || $l2_column === 'auto' || $l3_soul === 'auto')
            && class_exists('\Linked3_Scene_Axis')
            && method_exists('\Linked3\Classes\Dashboard\Linked3_Scene_Axis', 'auto_detect_from_script')) {
            try {
                $detected = \Linked3_Scene_Axis::auto_detect_from_script($script);
                if ($l1_type === 'auto') $l1_type = $detected['l1'];
                if ($l2_column === 'auto') $l2_column = $detected['l2'];
                if ($l3_soul === 'auto') $l3_soul = $detected['l3'];
            } catch (\Throwable $e) {
                if ($l1_type === 'auto') $l1_type = 'city_life';
                if ($l2_column === 'auto') $l2_column = 'documentary';
                if ($l3_soul === 'auto') $l3_soul = 'none';
            }
        }

        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);
        }

        @set_time_limit(900);
        @ini_set('memory_limit', '768M');
        $prev_er = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        $prev_de = @ini_set('display_errors', '0');

        try {
            $scriptTrimmed = mb_substr($script, 0, 4000);

            $storyData = null;
            $storySource = 'none';
            if (class_exists('\Linked3_Story_Pipeline')) {
                try {
                    $storyData = \Linked3_Story_Pipeline::parse($scriptTrimmed, ['use_ai' => true]);
                    $storySource = 'ai';
                } catch (\Throwable $eA) {
                    if (function_exists('error_log')) {
                        error_log('[linked3 v9] Story Parser AI failed, fallback to local: ' . $eA->getMessage());
                    }
                    try {
                        $storyData = \Linked3_Story_Pipeline::parse($scriptTrimmed, ['use_ai' => false]);
                        $storySource = 'local_fallback';
                    } catch (\Throwable $eL) {
                        $storyData = null;
                        $storySource = 'failed';
                    }
                }
            }
            $beats = $storyData['beats'] ?? [];
            $characters = $storyData['characters'] ?? [];
            $theme = $storyData['theme'] ?? '';

            if (count($beats) < 2) {
                try {
                    $nodes = Linked3_Genesis_Processor::genesisFPExtractCores($scriptTrimmed, 10, $styleId, true);
                    $beats = array_map(function($n) {
                        return ['id' => $n['node_id'] ?? 1, 'text' => $n['action'] ?? '', 'emotion' => $n['mood'] ?? 'neutral', 'arc_position' => 'development'];
                    }, $nodes);
                    $storySource = $storySource === 'failed' ? 'fp_fallback' : $storySource;
                } catch (\Throwable $eFP) {
                    $sentences = preg_split('/[。！？\n.!?]+/u', $scriptTrimmed);
                    $sentences = array_filter(array_map('trim', $sentences));
                    $beats = array_map(function($s, $i) {
                        return ['id' => $i + 1, 'text' => $s, 'emotion' => 'neutral', 'arc_position' => 'development'];
                    }, array_slice($sentences, 0, 15), array_keys(array_slice($sentences, 0, 15)));
                    $storySource = 'sentence_split';
                }
            }

            $maxBeats = 20;
            if (count($beats) > $maxBeats) {
                $beats = array_slice($beats, 0, $maxBeats);
            }

            $skeletonId = 'documentary_photo';
            if (class_exists('\Linked3_Scene_Axis')) {
                try {
                    $skeletonId = \Linked3_Scene_Axis::route_skeleton($l1_type, $l2_column, $l3_soul);
                } catch (\Throwable $e) {
                }
            }

            $fpExtractor = class_exists('\Linked3_FP_Extractor') ? new \Linked3_FP_Extractor() : null;
            $assembler = class_exists('\Linked3_Prompt_Assembler') ? new \Linked3_Prompt_Assembler() : null;

            $results = [];
            $pqsScores = [];
            $beatErrors = [];

            foreach ($beats as $i => $beat) {
                try {
                    $beatText = $beat['text'] ?? $beat['action'] ?? '';
                    $emotion = $beat['emotion'] ?? 'neutral';
                    $arcPosition = $beat['arc_position'] ?? 'development';

                    $fpCore = null;
                    if ($fpExtractor) {
                        try {
                            $fpCore = $fpExtractor->extract($beatText, ['use_ai' => true, 'style_name' => $styleId]);
                        } catch (\Throwable $eFP) {
                            try {
                                $fpCore = $fpExtractor->extract($beatText, ['use_ai' => false, 'style_name' => $styleId]);
                            } catch (\Throwable $eFP2) {
                                $fpCore = ['action_en' => $beatText, 'emotion' => $emotion];
                            }
                        }
                    } else {
                        $fpCore = ['action_en' => $beatText, 'emotion' => $emotion];
                    }

                    $color = '';
                    if (class_exists('\Linked3_Story_Pipeline')) {
                        try { $color = \Linked3_Story_Pipeline::emotion_to_color($emotion); } catch (\Throwable $e) {}
                    }

                    $shotData = [
                        'scene_id'      => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                        'scene_type'    => $skeletonId,
                        'seed_refs'     => $seedRefs,
                        'arc_position'  => $arcPosition,
                        'emotion'       => $emotion,
                        'color'         => $color,
                        'platform'      => $platform,
                        'fp_core'       => $fpCore,
                        'shot'          => $beat['shot'] ?? '中景',
                        'angle'         => $beat['angle'] ?? '平视',
                        'comp'          => $beat['comp'] ?? '三分法',
                        'location'      => $fpCore['where'] ?? '',
                    ];

                    $assembled = null;
                    if ($assembler) {
                        try {
                            $assembled = $assembler->assemble($shotData);
                        } catch (\Throwable $eAsm) {
                            $assembled = ['prompt' => $fpCore['action_en'] ?? $beatText, 'meta' => [], 'script' => [], 'validation' => []];
                        }
                    } else {
                        $assembled = ['prompt' => $fpCore['action_en'] ?? $beatText, 'meta' => [], 'script' => [], 'validation' => []];
                    }

                    $pqs = ['passed_count' => 0, 'total' => 13];
                    if (class_exists('\Linked3_Quality_Loop')) {
                        try { $pqs = \Linked3_Quality_Loop::pqs_check($shotData); } catch (\Throwable $e) {}
                    }

                    $pqsScores[] = $pqs['passed_count'] ?? 0;

                    $results[] = [
                        'panel_id'   => 'P' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
                        'scene_id'   => $shotData['scene_id'],
                        'location'   => $shotData['location'],
                        'action'     => $beatText,
                        'mood'       => $emotion,
                        'shot'       => $shotData['shot'],
                        'angle'      => $shotData['angle'],
                        'comp'       => $shotData['comp'],
                        'characters' => $characters,
                        'prompt_en'  => $assembled['prompt'],
                        'prompt_with_params' => $assembled['prompt'],
                        'style'      => $styleId,
                        'style_name' => $skeletonId,
                        'platform'   => $platform,
                        'skeleton_id'=> $skeletonId,
                        'pqs'        => ['passed' => $pqs['passed_count'] ?? 0, 'total' => 13, 'pass_rate' => (($pqs['passed_count'] ?? 0) . '/13')],
                        'fp_core'    => $fpCore,
                        'meta'       => $assembled['meta'] ?? [],
                        'script'     => $assembled['script'] ?? [],
                        'validation' => $assembled['validation'] ?? [],
                    'prompt_source' => 'v9_three_layer',
                ];
                } catch (\Throwable $eBeat) {
                    $beatErrors[] = [
                        'beat_index' => $i,
                        'error'      => $eBeat->getMessage(),
                    ];
                    if (function_exists('error_log')) {
                        error_log('[linked3 v9] Beat #' . ($i + 1) . ' failed, skipped: ' . $eBeat->getMessage());
                    }
                }
            }

            if (empty($results) && !empty($beatErrors)) {
                wp_send_json_error([
                    'message' => __('所有分镜生成失败: ', 'linked3-ai') . $beatErrors[0]['error'],
                    'beat_errors' => $beatErrors,
                ]);
            }

            $batchReport = null;
            if (class_exists('\Linked3_Quality_Loop') && count($results) > 1) {
                try { $batchReport = \Linked3_Quality_Loop::batch_consistency_check($results); } catch (\Throwable $e) {}
            }

            wp_send_json_success([
                'panels'          => $results,
                'total_panels'    => count($results),
                'total_scenes'    => count(array_unique(array_column($results, 'scene_id'))),
                'style'           => $styleId,
                'platform'        => $platform,
                'mode'            => 'v9_integrated',
                'skeleton_id'     => $skeletonId,
                'l1_type'         => $l1_type,
                'l2_column'       => $l2_column,
                'l3_soul'         => $l3_soul,
                'seed_refs'       => $seedRefs,
                'theme'           => $theme,
                'characters'      => $characters,
                'pqs_avg'         => count($pqsScores) ? round(array_sum($pqsScores) / count($pqsScores), 1) : 0,
                'batch_report'    => $batchReport,
                'story_source'    => $storySource,       // v9.1.2: 标记 Story Parser 来源
                'beat_errors'     => $beatErrors,         // v9.1.2: 部分失败信息
                'beats_requested' => count($beats),       // v9.1.2: 实际处理的 beat 数
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($prev_er);
            if ($prev_de !== false) @ini_set('display_errors', $prev_de);
        }
    }

        public static function ajax_genesis_v9_stage1() : mixed { return Linked3_Genesis_V9_Stages::ajax_genesis_v9_stage1(); }

        public static function ajax_genesis_v9_stage2() : mixed { return Linked3_Genesis_V9_Stages::ajax_genesis_v9_stage2(); }
}
