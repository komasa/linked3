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
    public static function ajax_genesis_v9_stage1(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $l1_type = sanitize_text_field($_POST['l1_type'] ?? 'auto');
        $l2_column = sanitize_text_field($_POST['l2_column'] ?? 'auto');
        $l3_soul = sanitize_text_field($_POST['l3_soul'] ?? 'auto');
        $genMode = sanitize_text_field($_POST['gen_mode'] ?? 'local');

        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);
        }

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        $prev_er = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        $prev_de = @ini_set('display_errors', '0');
        if (function_exists('ob_start')) {
            ob_start();
        }
        if (($l1_type === 'auto' || $l2_column === 'auto' || $l3_soul === 'auto')
            && class_exists('\SceneAxis')
            && method_exists('\Linked3\Classes\Dashboard\SceneAxis', 'auto_detect_from_script')) {
            try {
                $detected = \SceneAxis::auto_detect_from_script($script);
                if ($l1_type === 'auto') $l1_type = $detected['l1'];
                if ($l2_column === 'auto') $l2_column = $detected['l2'];
                if ($l3_soul === 'auto') $l3_soul = $detected['l3'];
            } catch (\Throwable $e) {
                if ($l1_type === 'auto') $l1_type = 'city_life';
                if ($l2_column === 'auto') $l2_column = 'documentary';
                if ($l3_soul === 'auto') $l3_soul = 'none';
            }
        }

        $scriptTrimmed = mb_substr($script, 0, 4000);

        try {
            $useAi = ($genMode === 'ai' || $genMode === 'hybrid');
            $storyData = null;
            $storySource = 'none';
            if (class_exists('\StoryPipeline')) {
                try {
                    $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => $useAi]);
                    $storySource = $useAi ? 'ai' : 'local';
                } catch (\Throwable $eL) {
                    try {
                        $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => false]);
                        $storySource = $useAi ? 'local_fallback' : 'local';
                    } catch (\Throwable $eL2) {
                        $storyData = null;
                        $storySource = 'failed';
                    }
                }
            }
            $beats = $storyData['beats'] ?? [];
            $characters = $storyData['characters'] ?? [];
            $theme = $storyData['theme'] ?? '';

            if (count($beats) < 2) {
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
                        'id' => $i + 1,
                        'text' => mb_substr($s, 0, 200),
                        'emotion' => 'neutral',
                        'arc_position' => $i < 3 ? '开场' : ($i >= count($unique) - 2 ? '收尾' : '发展'),
                    ];
                }
                $storySource = 'sentence_split';
            }

            $maxBeats = 15;
            if (count($beats) > $maxBeats) {
                $beats = array_slice($beats, 0, $maxBeats);
            }

            $skeletonId = 'documentary_photo';
            if (class_exists('\SceneAxis')) {
                try {
                    $skeletonId = \SceneAxis::route_skeleton($l1_type, $l2_column, $l3_soul);
                } catch (\Throwable $e) {}
            }

            $autoSeeds = [];
            $autoSeedRefs = [];
            if (class_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT') && method_exists('\Linked3\Classes\Dashboard\GenesisSeedCPT', 'create')) {
                if (!empty($characters) && is_array($characters)) {
                    foreach ($characters as $idx => $char) {
                        $charName = is_array($char) ? ($char['name'] ?? $char['id'] ?? '') : (string)$char;
                        if (empty($charName)) continue;

                        if (mb_strlen($charName) < 2) continue;
                        if (preg_match('/[的了是在和与把被将让给向到从为对按据依由这那之其每各又且]/u', $charName)) continue;
                        if (preg_match('/^\d+$/', $charName)) continue;

                        $seedId = 'C' . ($idx + 1) . '_' . mb_substr($charName, 0, 4, 'UTF-8') . '_v1';
                        $existing = null;
                        try {
                            $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                        } catch (\Throwable $e) {}

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
                                    'personality'     => $char['personality'] ?? '',
                                    'speech_pattern'  => $char['speech_pattern'] ?? '',
                                    'emotion_range'   => $char['emotion_range'] ?? '',
                                ];

                                $postData = [
                                    'title'          => $charName . ' (自动生成)',
                                    'seed_id'        => $seedId,
                                    'seed_type'      => 'fixed',
                                    'seed_category'  => 'char',
                                    'visual_dna'     => $visualDna,
                                    'personality_dna'=> $personalityDna,
                                    'priority'       => ['critical' => [], 'important' => [], 'flexible' => []],
                                    'lock'           => [],
                                    'ai_adapter'     => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                                    'parent_seed'    => '',
                                    'project_ref'    => '',
                                ];

                                $postId = \GenesisSeedCPT::create($postData);
                                if (!is_wp_error($postId)) {
                                    $autoSeeds[] = [
                                        'seed_id'   => $seedId,
                                        'name'      => $charName,
                                        'category'  => 'char',
                                        'post_id'   => $postId,
                                        'source'    => 'auto_from_story_parser',
                                    ];
                                    $autoSeedRefs[] = $seedId;
                                }
                            } catch (\Throwable $e) {
                                if (function_exists('error_log')) {
                                    error_log('[linked3 v9 stage1] Auto Seed creation failed for ' . $charName . ': ' . $e->getMessage());
                                }
                            }
                        } else {
                            $autoSeeds[] = [
                                'seed_id'   => $seedId,
                                'name'      => $charName,
                                'category'  => 'char',
                                'post_id'   => $existing['post_id'] ?? 0,
                                'source'    => 'existing',
                            ];
                            $autoSeedRefs[] = $seedId;
                        }
                    }
                }

                if (!empty($theme)) {
                    $sceneSeedId = 'S1_' . mb_substr($theme, 0, 4, 'UTF-8') . '_v1';
                    $existingScene = null;
                    try {
                        $existingScene = \GenesisSeedCPT::get_by_seed_id($sceneSeedId);
                    } catch (\Throwable $e) {}

                    if (!$existingScene) {
                        try {
                            $postData = [
                                'title'          => $theme . ' (场景自动生成)',
                                'seed_id'        => $sceneSeedId,
                                'seed_type'      => 'variable',
                                'seed_category'  => 'scene',
                                'visual_dna'     => ['atmosphere' => $theme, 'location' => '', 'time' => ''],
                                'personality_dna'=> [],
                                'priority'       => ['critical' => [], 'important' => [], 'flexible' => []],
                                'lock'           => [],
                                'ai_adapter'     => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                                'parent_seed'    => '',
                                'project_ref'    => '',
                            ];
                            $postId = \GenesisSeedCPT::create($postData);
                            if (!is_wp_error($postId)) {
                                $autoSeeds[] = [
                                    'seed_id'   => $sceneSeedId,
                                    'name'      => $theme,
                                    'category'  => 'scene',
                                    'post_id'   => $postId,
                                    'source'    => 'auto_from_story_parser',
                                ];
                                $autoSeedRefs[] = $sceneSeedId;
                            }
                        } catch (\Throwable $e) {}
                    } else {
                        $autoSeeds[] = [
                            'seed_id'   => $sceneSeedId,
                            'name'      => $theme,
                            'category'  => 'scene',
                            'post_id'   => $existingScene['post_id'] ?? 0,
                            'source'    => 'existing',
                        ];
                        $autoSeedRefs[] = $sceneSeedId;
                    }
                }
            }

            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }

            wp_send_json_success([
                'beats'        => $beats,
                'characters'   => $characters,
                'theme'        => $theme,
                'skeleton_id'  => $skeletonId,
                'l1_type'      => $l1_type,
                'l2_column'    => $l2_column,
                'l3_soul'      => $l3_soul,
                'story_source' => $storySource,
                'beat_count'   => count($beats),
                'auto_seeds'   => $autoSeeds,
                'auto_seed_refs' => $autoSeedRefs,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }
            wp_send_json_error([
                'message' => __('Stage 1 失败: ', 'linked3-ai') . $e->getMessage(),
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($prev_er);
            if ($prev_de !== false) @ini_set('display_errors', $prev_de);
        }
    }

    public static function ajax_genesis_v9_stage2(): void {
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
        if (!is_array($beats) || empty($beats)) {
            wp_send_json_error(['message' => __('beats 数据为空', 'linked3-ai')]);
        }
        $characters = json_decode($charactersJson, true);
        if (!is_array($characters)) $characters = [];

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        $prev_er = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        $prev_de = @ini_set('display_errors', '0');
        if (function_exists('ob_start')) {
            ob_start();
        }

        try {
            $fpUseAi = ($genMode2 === 'ai');
            $fpExtractor = class_exists('\FPExtractor') ? new \FPExtractor() : null;
            $assembler = class_exists('\PromptAssembler') ? new \PromptAssembler() : null;

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
                            $fpCore = $fpExtractor->extract($beatText, ['use_ai' => $fpUseAi, 'style_name' => $styleId]);
                        } catch (\Throwable $eFP) {
                            try {
                                $fpCore = $fpExtractor->extract($beatText, ['use_ai' => false, 'style_name' => $styleId]);
                            } catch (\Throwable $eFP2) {
                                $fpCore = ['action_en' => 'a scene depicting daily life', 'emotion' => $emotion, 'who' => '', 'what' => '', 'where' => '', 'when' => '', 'theme' => '', 'raw' => $beatText];
                            }
                        }
                    } else {
                        $fpCore = ['action_en' => 'a scene depicting daily life', 'emotion' => $emotion, 'raw' => $beatText];
                    }

                    $color = '';
                    if (class_exists('\StoryPipeline')) {
                        try { $color = \StoryPipeline::emotion_to_color($emotion); } catch (\Throwable $e) {}
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
                        'dialogue'      => $beat['dialogue'] ?? '',
                        'subject'       => $fpCore['who'] ?? '',
                        'endpoint'      => $skeletonId,
                        'footer'        => 'Linked3 AI',
                        'followup'      => '',
                        'cognitive_level' => 'understand',
                        'diagram_type'  => 'photo',
                        'density'       => 'mid',
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

                    if (empty($assembled['meta'])) {
                        $assembled['meta'] = [
                            'color' => $color,
                            'signature' => 'Linked3 AI',
                            'endpoint' => $skeletonId,
                            'character_seeds' => !empty($seedRefs) ? [['seed_id' => $seedRefs[0] ?? '']] : [],
                            'mood' => $emotion,
                            'cognitive_level' => 'understand',
                            'density' => 'mid',
                            'diagram_type' => 'photo',
                            'footer' => 'Linked3 AI',
                        ];
                    }
                    if (empty($assembled['script'])) {
                        $assembled['script'] = [
                            'arc_position' => $arcPosition,
                            'dialogue' => $beat['dialogue'] ?? '',
                            'emotion' => $emotion,
                            'transition' => 'cut',
                            'pacing' => 'medium',
                            'followup' => '',
                        ];
                    }
                    if (empty($assembled['validation'])) {
                        $assembled['validation'] = [
                            'visual_consistency' => true,
                            'narrative_completeness' => true,
                        ];
                    }

                    $shotData['meta'] = $assembled['meta'];
                    $shotData['script'] = $assembled['script'];
                    $shotData['validation'] = $assembled['validation'];
                    $shotData['prompt'] = $assembled['prompt'];

                    $pqs = ['passed_count' => 0, 'total' => 13];
                    if (class_exists('\QualityLoop')) {
                        try { $pqs = \QualityLoop::pqs_check($shotData); } catch (\Throwable $e) {}
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
                    $beatErrors[] = ['beat_index' => $i, 'error' => $eBeat->getMessage()];
                    if (function_exists('error_log')) {
                        error_log('[linked3 v9 stage2] Beat #' . ($i + 1) . ' failed, skipped: ' . $eBeat->getMessage());
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
            if (class_exists('\QualityLoop') && count($results) > 1) {
                try { $batchReport = \QualityLoop::batch_consistency_check($results); } catch (\Throwable $e) {}
            }

            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }

            wp_send_json_success([
                'panels'          => $results,
                'total_panels'    => count($results),
                'total_scenes'    => count(array_unique(array_column($results, 'scene_id'))),
                'style'           => $styleId,
                'platform'        => $platform,
                'mode'            => 'v9_integrated',
                'skeleton_id'     => $skeletonId,
                'seed_refs'       => $seedRefs,
                'theme'           => $theme,
                'characters'      => $characters,
                'pqs_avg'         => count($pqsScores) ? round(array_sum($pqsScores) / count($pqsScores), 1) : 0,
                'batch_report'    => $batchReport,
                'beat_errors'     => $beatErrors,
                'beats_requested' => count($beats),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) {
                @ob_end_clean();
            }
            wp_send_json_error([
                'message' => __('Stage 2 失败: ', 'linked3-ai') . $e->getMessage(),
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($prev_er);
            if ($prev_de !== false) @ini_set('display_errors', $prev_de);
        }
    }

}
