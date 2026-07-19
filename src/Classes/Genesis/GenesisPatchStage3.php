<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisPatchStage3
{
    public static function ajax_seed_generate_full() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $seedName = sanitize_text_field($_POST['seed_name'] ?? '未命名 Seed');
        $scriptType = sanitize_text_field($_POST['script_type'] ?? 'comic');

        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            $dna = ['characters' => [], 'scenes' => [], 'color_palette' => [], 'style_fingerprint' => ''];
            if (class_exists('\Linked3\Classes\Genesis\GenesisSeedDNA')) {
                $styleConfig = class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')
                    ? \GenesisStyleEngine::load($styleId) : [];
                $styleName = $styleConfig['name_cn'] ?? $styleId;
                try {
                    $dna = \GenesisSeedDNA::generate($script, $styleId, $styleName);
                } catch (\Throwable $e) {
                    if (function_exists('error_log')) {
                        error_log('[linked3 v10.1.0] SeedDNA::generate failed: ' . $e->getMessage());
                    }
                }
            }

            if (empty($dna['characters'])) {
                $dna['characters'] = self::local_extract_characters($script);
            }
            if (empty($dna['scenes'])) {
                $dna['scenes'] = self::local_extract_scenes($script);
            }
            if (empty($dna['color_palette'])) {
                $styleConfig = class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')
                    ? \GenesisStyleEngine::load($styleId) : [];
                $dna['color_palette'] = [
                    'primary' => $styleConfig['color_palette']['primary'][0]['hex'] ?? '#808080',
                    'secondary' => $styleConfig['color_palette']['secondary'][0]['hex'] ?? '#A0A0A0',
                    'accent' => $styleConfig['color_palette']['accent'][0] ?? '#D4A574',
                ];
            }

            $created = ['characters' => [], 'scenes' => [], 'props' => [], 'style' => null, 'palette' => null, 'brand' => null, 'total' => 0];

            if (!class_exists('\Linked3\Classes\Genesis\GenesisSeedCPT') || !method_exists('\Linked3\Classes\Genesis\GenesisSeedCPT', 'create')) {
                wp_send_json_error(['message' => __('Seed CPT 不可用', 'linked3-ai')]);
            }

            if (!empty($dna['characters'])) {
                foreach ($dna['characters'] as $idx => $char) {
                    $charName = is_array($char) ? ($char['name'] ?? '角色' . ($idx + 1)) : (string)$char;
                    if (empty($charName)) continue;
                    $seedId = 'CHAR_' . date('md') . '_' . $idx . '_' . mb_substr(md5($charName), 0, 6);
                    $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                    if ($existing) { $created['characters'][] = ['seed_id' => $seedId, 'name' => $charName, 'post_id' => $existing['post_id'] ?? 0]; continue; }

                    $appearance = is_array($char) ? ($char['appearance'] ?? '') : '';
                    $clothing = is_array($char) ? ($char['clothing'] ?? '') : '';
                    $features = is_array($char) ? ($char['distinctive_features'] ?? '') : '';

                    $postId = \GenesisSeedCPT::create([
                        'title' => $charName . ' (角色)',
                        'seed_id' => $seedId,
                        'seed_type' => 'fixed',
                        'seed_category' => 'char',
                        'visual_dna' => ['appearance' => $appearance, 'clothing' => $clothing, 'distinctive_features' => $features],
                        'personality_dna' => [],
                        'priority' => ['critical' => ['appearance', 'distinctive_features'], 'important' => ['clothing'], 'flexible' => []],
                        'lock' => ['appearance', 'distinctive_features'],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '',
                        'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['characters'][] = ['seed_id' => $seedId, 'name' => $charName, 'post_id' => $postId]; $created['total']++; }
                }
            }

            if (!empty($dna['scenes'])) {
                foreach ($dna['scenes'] as $idx => $scene) {
                    $sceneName = is_array($scene) ? ($scene['name'] ?? '场景' . ($idx + 1)) : (string)$scene;
                    if (empty($sceneName)) continue;
                    $seedId = 'SCENE_' . date('md') . '_' . $idx . '_' . mb_substr(md5($sceneName), 0, 6);
                    $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                    if ($existing) { $created['scenes'][] = ['seed_id' => $seedId, 'name' => $sceneName, 'post_id' => $existing['post_id'] ?? 0]; continue; }

                    $description = is_array($scene) ? ($scene['description'] ?? '') : '';
                    $lighting = is_array($scene) ? ($scene['lighting'] ?? '') : '';
                    $atmosphere = is_array($scene) ? ($scene['atmosphere'] ?? '') : '';

                    $postId = \GenesisSeedCPT::create([
                        'title' => $sceneName . ' (场景)',
                        'seed_id' => $seedId,
                        'seed_type' => 'variable',
                        'seed_category' => 'scene',
                        'visual_dna' => ['description' => $description, 'lighting' => $lighting, 'atmosphere' => $atmosphere],
                        'personality_dna' => [],
                        'priority' => ['critical' => [], 'important' => ['lighting', 'atmosphere'], 'flexible' => ['description']],
                        'lock' => [],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '',
                        'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['scenes'][] = ['seed_id' => $seedId, 'name' => $sceneName, 'post_id' => $postId]; $created['total']++; }
                }
            }

            $propKeywords = self::extract_props_from_script($script);
            if (!empty($propKeywords)) {
                foreach ($propKeywords as $idx => $propName) {
                    $seedId = 'PROP_' . date('md') . '_' . $idx . '_' . mb_substr(md5($propName), 0, 6);
                    $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                    if ($existing) continue;
                    $postId = \GenesisSeedCPT::create([
                        'title' => $propName . ' (道具)',
                        'seed_id' => $seedId, 'seed_type' => 'fixed', 'seed_category' => 'prop',
                        'visual_dna' => ['name' => $propName, 'description' => '从剧本提取的关键道具'],
                        'personality_dna' => [],
                        'priority' => ['critical' => ['name'], 'important' => [], 'flexible' => []],
                        'lock' => ['name'],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '', 'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['props'][] = ['seed_id' => $seedId, 'name' => $propName, 'post_id' => $postId]; $created['total']++; }
                }
            }

            if (class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
                $styleConfig = \GenesisStyleEngine::load($styleId);
                $styleNameCn = $styleConfig['name_cn'] ?? $styleId;
                $seedId = 'STYLE_' . $styleId . '_v1';
                $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                if (!$existing) {
                    $postId = \GenesisSeedCPT::create([
                        'title' => $styleNameCn . ' (风格)',
                        'seed_id' => $seedId, 'seed_type' => 'fixed', 'seed_category' => 'style',
                        'visual_dna' => [
                            'prompt_keywords' => $styleConfig['meta_prompt'] ?? '',
                            'lighting' => $styleConfig['lighting'] ?? '',
                            'render' => $styleConfig['render'] ?? '',
                            'atmosphere' => implode(', ', $styleConfig['atmosphere'] ?? []),
                        ],
                        'personality_dna' => [],
                        'priority' => ['critical' => ['prompt_keywords'], 'important' => ['lighting', 'render'], 'flexible' => []],
                        'lock' => ['prompt_keywords'],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '', 'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['style'] = ['seed_id' => $seedId, 'name' => $styleNameCn, 'post_id' => $postId]; $created['total']++; }
                } else {
                    $created['style'] = ['seed_id' => $seedId, 'name' => $styleNameCn, 'post_id' => $existing['post_id'] ?? 0];
                }
            }

            if (!empty($dna['color_palette'])) {
                $seedId = 'PALETTE_' . date('md') . '_' . mb_substr(md5($seedName), 0, 6);
                $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                if (!$existing) {
                    $postId = \GenesisSeedCPT::create([
                        'title' => $seedName . ' (色板)',
                        'seed_id' => $seedId, 'seed_type' => 'fixed', 'seed_category' => 'palette',
                        'visual_dna' => ['color_palette' => $dna['color_palette']],
                        'personality_dna' => [],
                        'priority' => ['critical' => ['color_palette'], 'important' => [], 'flexible' => []],
                        'lock' => ['color_palette'],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '', 'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['palette'] = ['seed_id' => $seedId, 'name' => $seedName, 'post_id' => $postId]; $created['total']++; }
                }
            }

            $brandName = self::extract_brand_from_script($script);
            if ($brandName) {
                $seedId = 'BRAND_' . date('md') . '_' . mb_substr(md5($brandName), 0, 6);
                $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
                if (!$existing) {
                    $postId = \GenesisSeedCPT::create([
                        'title' => $brandName . ' (品牌)',
                        'seed_id' => $seedId, 'seed_type' => 'fixed', 'seed_category' => 'brand',
                        'visual_dna' => ['name' => $brandName, 'description' => '从剧本提取的品牌/IP元素'],
                        'personality_dna' => [],
                        'priority' => ['critical' => ['name'], 'important' => [], 'flexible' => []],
                        'lock' => ['name'],
                        'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                        'parent_seed' => '', 'project_ref' => $seedName,
                    ]);
                    if (!is_wp_error($postId)) { $created['brand'] = ['seed_id' => $seedId, 'name' => $brandName, 'post_id' => $postId]; $created['total']++; }
                }
            }

            wp_send_json_success([
                'seed_id' => $seedName,
                'dna' => $dna,
                'created' => $created,
                'message' => __('SEED 库生成成功, 共 ', 'linked3-ai') . $created['total'] . ' 个 SEED',
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('SEED 生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    public static function ajax_v9_stage1_fixed() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $l1_type = sanitize_text_field($_POST['l1_type'] ?? 'auto');
        $l2_column = sanitize_text_field($_POST['l2_column'] ?? 'auto');
        $l3_soul = sanitize_text_field($_POST['l3_soul'] ?? 'auto');
        $genMode = sanitize_text_field($_POST['gen_mode'] ?? 'local');
        $panelCount = intval($_POST['panel_count'] ?? 8);
        $panelCount = max(1, min(50, $panelCount));
        $splitMode = sanitize_text_field($_POST['split_mode'] ?? 'auto');
        $panelLayout = sanitize_text_field($_POST['panel_layout'] ?? 'auto');
        $aspectRatio = sanitize_text_field($_POST['aspect_ratio'] ?? '3:4');
        $renderingTech = sanitize_text_field($_POST['rendering_tech'] ?? 'auto');

        if ($styleId === 'auto') {
            $styleId = self::auto_detect_style($script);
        }

        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('display_errors', '0');
        $prev_er_v1006 = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        if (function_exists('ob_start')) ob_start();

        try {
            $script = self::filter_web_noise($script);

            if (($l1_type === 'auto' || $l2_column === 'auto' || $l3_soul === 'auto')
                && class_exists('\Linked3\Classes\Genesis\SceneAxis')
                && method_exists('\Linked3\Classes\Genesis\SceneAxis', 'auto_detect_from_script')) {
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
            $useAi = ($genMode === 'ai' || $genMode === 'hybrid');
            $beats = [];
            $characters = [];
            $theme = '';
            $storySource = 'none';

            if ($useAi && class_exists('\Linked3\Classes\Genesis\StoryPipeline') && method_exists('\Linked3\Classes\Genesis\StoryPipeline', 'parse')) {
                try {
                    $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => true]);
                    $storySource = 'story_pipeline_ai';
                    $beats = $storyData['beats'] ?? [];
                    $characters = $storyData['characters'] ?? [];
                    $theme = $storyData['theme'] ?? '';
                } catch (\Throwable $e) {}
            }

            if (empty($beats)) {
                $storySource = 'sentence_split';
                $text = preg_replace('/[\n\r]+/u', ' ', $scriptTrimmed);
                $sentences = preg_split('/[。！？!?；;\.]+/u', $text);
                $sentences = array_filter($sentences, fn($s) => mb_strlen(trim($s)) >= 15);
                $sentences = array_values($sentences);

                $seen = [];
                $unique = [];
                foreach ($sentences as $s) {
                    $key = mb_substr(trim($s), 0, 30);
                    if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = trim($s); }
                }

                if ($splitMode === 'fixed') {
                    $targetCount = $panelCount;
                } elseif ($splitMode === 'sentence') {
                    $targetCount = count($unique);
                } else {
                    $targetCount = min(15, max($panelCount, count($unique)));
                }

                if (count($unique) > $targetCount) {
                    $step = count($unique) / $targetCount;
                    $sampled = [];
                    for ($i = 0; $i < $targetCount; $i++) {
                        $idx = (int)($i * $step);
                        if ($idx < count($unique)) $sampled[] = $unique[$idx];
                    }
                    $unique = $sampled;
                }

                $beats = [];
                foreach ($unique as $i => $s) {
                    $beats[] = [
                        'id' => $i + 1,
                        'text' => mb_substr($s, 0, 200),
                        'emotion' => 'neutral',
                        'arc_position' => $i < 3 ? '开场' : ($i >= count($unique) - 2 ? '收尾' : '发展'),
                    ];
                }
            }

            if ($splitMode === 'fixed' && count($beats) > $panelCount) {
                $beats = array_slice($beats, 0, $panelCount);
            }

            $skeletonId = 'documentary_photo';
            if (class_exists('\Linked3\Classes\Genesis\SceneAxis')) {
                try { $skeletonId = \SceneAxis::route_skeleton($l1_type, $l2_column, $l3_soul); } catch (\Throwable $e) {}
            }

            if (function_exists('ob_end_clean')) @ob_end_clean();

            wp_send_json_success([
                'beats' => $beats,
                'characters' => $characters,
                'theme' => $theme,
                'skeleton_id' => $skeletonId,
                'l1_type' => $l1_type, 'l2_column' => $l2_column, 'l3_soul' => $l3_soul,
                'story_source' => $storySource,
                'beat_count' => count($beats),
                'panel_count' => $panelCount,
                'split_mode' => $splitMode,
                'panel_layout' => $panelLayout,
                'aspect_ratio' => $aspectRatio,
                'rendering_tech' => $renderingTech,
                'auto_seeds' => [], 'auto_seed_refs' => [],
            ]);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) @ob_end_clean();
            wp_send_json_error(['message' => __('Stage 1 失败: ', 'linked3-ai') . $e->getMessage(), 'file' => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '']);
        } finally {
            error_reporting($prev_er_v1006);
        }
    }

}
