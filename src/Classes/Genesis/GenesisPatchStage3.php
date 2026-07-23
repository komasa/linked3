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
            $dna = self::buildSeedDna($script, $styleId);

            $created = ['characters' => [], 'scenes' => [], 'props' => [], 'style' => null, 'palette' => null, 'brand' => null, 'total' => 0];

            if (!class_exists('\Linked3\Classes\Genesis\GenesisSeedCPT') || !method_exists('\Linked3\Classes\Genesis\GenesisSeedCPT', 'create')) {
                wp_send_json_error(['message' => __('Seed CPT 不可用', 'linked3-ai')]);
            }

            self::createCharacterSeeds($dna['characters'] ?? [], $seedName, $created);
            self::createSceneSeeds($dna['scenes'] ?? [], $seedName, $created);
            self::createPropSeeds(self::extract_props_from_script($script), $seedName, $created);
            self::createStyleSeed($styleId, $seedName, $created);
            self::createPaletteSeed($dna['color_palette'] ?? [], $seedName, $created);
            self::createBrandSeed(self::extract_brand_from_script($script), $seedName, $created);

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

    /**
     * 构建 SeedDNA (含本地 fallback).
     */
    private static function buildSeedDna(string $script, string $styleId): array
    {
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
        return $dna;
    }

    /**
     * 批量创建角色 SEED.
     */
    private static function createCharacterSeeds(array $characters, string $seedName, array &$created): void
    {
        if (empty($characters)) return;
        foreach ($characters as $idx => $char) {
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

    /**
     * 批量创建场景 SEED.
     */
    private static function createSceneSeeds(array $scenes, string $seedName, array &$created): void
    {
        if (empty($scenes)) return;
        foreach ($scenes as $idx => $scene) {
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

    /**
     * 批量创建道具 SEED.
     */
    private static function createPropSeeds(array $propKeywords, string $seedName, array &$created): void
    {
        if (empty($propKeywords)) return;
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

    /**
     * 创建风格 SEED (若不存在).
     */
    private static function createStyleSeed(string $styleId, string $seedName, array &$created): void
    {
        if (!class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) return;
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

    /**
     * 创建色板 SEED (若不存在).
     */
    private static function createPaletteSeed(array $palette, string $seedName, array &$created): void
    {
        if (empty($palette)) return;
        $seedId = 'PALETTE_' . date('md') . '_' . mb_substr(md5($seedName), 0, 6);
        $existing = \GenesisSeedCPT::get_by_seed_id($seedId);
        if (!$existing) {
            $postId = \GenesisSeedCPT::create([
                'title' => $seedName . ' (色板)',
                'seed_id' => $seedId, 'seed_type' => 'fixed', 'seed_category' => 'palette',
                'visual_dna' => ['color_palette' => $palette],
                'personality_dna' => [],
                'priority' => ['critical' => ['color_palette'], 'important' => [], 'flexible' => []],
                'lock' => ['color_palette'],
                'ai_adapter' => ['mj' => '', 'sd' => '', 'flux' => '', 'dalle' => ''],
                'parent_seed' => '', 'project_ref' => $seedName,
            ]);
            if (!is_wp_error($postId)) { $created['palette'] = ['seed_id' => $seedId, 'name' => $seedName, 'post_id' => $postId]; $created['total']++; }
        }
    }

    /**
     * 创建品牌 SEED (若不存在).
     */
    private static function createBrandSeed(string $brandName, string $seedName, array &$created): void
    {
        if (empty($brandName)) return;
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

    public static function ajax_v9_stage1_fixed() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $inputs = self::parseStage1Inputs();
        $script = $inputs['script'];
        $styleId = $inputs['styleId'];
        $l1_type = $inputs['l1_type'];
        $l2_column = $inputs['l2_column'];
        $l3_soul = $inputs['l3_soul'];
        $genMode = $inputs['genMode'];
        $panelCount = $inputs['panelCount'];
        $splitMode = $inputs['splitMode'];
        $panelLayout = $inputs['panelLayout'];
        $aspectRatio = $inputs['aspectRatio'];
        $renderingTech = $inputs['renderingTech'];

        if ($styleId === 'auto') {
            // v27.6.22-fix F-03: auto_detect_style is in GenesisPatchV1006, not self.
            // Was: self::auto_detect_style($script) → Call to undefined method.
            $styleId = GenesisPatchV1006::auto_detect_style($script);
        }

        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本或故事', 'linked3-ai')]);

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');
        @ini_set('display_errors', '0');
        $prev_er_v1006 = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        if (function_exists('ob_start')) ob_start();

        try {
            $script = self::filter_web_noise($script);

            // Auto-detect scene axis with fallback
            [$l1_type, $l2_column, $l3_soul] = self::autoDetectSceneAxis($script, $l1_type, $l2_column, $l3_soul);

            $scriptTrimmed = mb_substr($script, 0, 4000);
            $useAi = ($genMode === 'ai' || $genMode === 'hybrid');

            // Try StoryPipeline first
            [$beats, $characters, $theme, $storySource] = self::parseStoryPipeline($scriptTrimmed, $useAi);

            // Fallback: sentence splitting
            if (empty($beats)) {
                [$beats, $storySource] = self::splitSentencesToBeats($scriptTrimmed, $splitMode, $panelCount);
            }

            if ($splitMode === 'fixed' && count($beats) > $panelCount) {
                $beats = array_slice($beats, 0, $panelCount);
            }

            $skeletonId = 'documentary_photo';
            if (class_exists('\Linked3\Classes\Genesis\SceneAxis')) {
                try { $skeletonId = \SceneAxis::route_skeleton($l1_type, $l2_column, $l3_soul); } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
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

    /**
     * 解析 stage1 输入参数.
     */
    private static function parseStage1Inputs(): array
    {
        return [
            'script'        => wp_strip_all_tags(wp_unslash($_POST['script'] ?? '')),
            'styleId'       => sanitize_text_field($_POST['style'] ?? 'documentary_photo'),
            'l1_type'       => sanitize_text_field($_POST['l1_type'] ?? 'auto'),
            'l2_column'     => sanitize_text_field($_POST['l2_column'] ?? 'auto'),
            'l3_soul'       => sanitize_text_field($_POST['l3_soul'] ?? 'auto'),
            'genMode'       => sanitize_text_field($_POST['gen_mode'] ?? 'local'),
            'panelCount'    => max(1, min(50, intval($_POST['panel_count'] ?? 8))),
            'splitMode'     => sanitize_text_field($_POST['split_mode'] ?? 'auto'),
            'panelLayout'   => sanitize_text_field($_POST['panel_layout'] ?? 'auto'),
            'aspectRatio'   => sanitize_text_field($_POST['aspect_ratio'] ?? '3:4'),
            'renderingTech' => sanitize_text_field($_POST['rendering_tech'] ?? 'auto'),
        ];
    }

    /**
     * Auto-detect scene axis (l1/l2/l3) with fallback defaults.
     *
     * @return array{0:string,1:string,2:string}
     */
    private static function autoDetectSceneAxis(string $script, string $l1_type, string $l2_column, string $l3_soul): array
    {
        if ($l1_type !== 'auto' && $l2_column !== 'auto' && $l3_soul !== 'auto') {
            return [$l1_type, $l2_column, $l3_soul];
        }
        if (!class_exists('\Linked3\Classes\Genesis\SceneAxis') || !method_exists('\Linked3\Classes\Genesis\SceneAxis', 'auto_detect_from_script')) {
            if ($l1_type === 'auto') $l1_type = 'city_life';
            if ($l2_column === 'auto') $l2_column = 'documentary';
            if ($l3_soul === 'auto') $l3_soul = 'none';
            return [$l1_type, $l2_column, $l3_soul];
        }
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
        return [$l1_type, $l2_column, $l3_soul];
    }

    /**
     * 尝试 StoryPipeline.parse (AI 模式).
     *
     * @return array{0:array,1:array,2:string,3:string}
     */
    private static function parseStoryPipeline(string $scriptTrimmed, bool $useAi): array
    {
        $beats = []; $characters = []; $theme = ''; $storySource = 'none';
        if (!$useAi || !class_exists('\Linked3\Classes\Genesis\StoryPipeline') || !method_exists('\Linked3\Classes\Genesis\StoryPipeline', 'parse')) {
            return [$beats, $characters, $theme, $storySource];
        }
        try {
            $storyData = \StoryPipeline::parse($scriptTrimmed, ['use_ai' => true]);
            $storySource = 'story_pipeline_ai';
            $beats = $storyData['beats'] ?? [];
            $characters = $storyData['characters'] ?? [];
            $theme = $storyData['theme'] ?? '';
        } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        return [$beats, $characters, $theme, $storySource];
    }

    /**
     * Fallback: 句子拆分 + 采样 → beats.
     *
     * @return array{0:array,1:string}
     */
    private static function splitSentencesToBeats(string $scriptTrimmed, string $splitMode, int $panelCount): array
    {
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
        return [$beats, $storySource];
    }

}
