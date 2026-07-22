<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class ScriptPatchHandlers
{
    public static function ajax_video_generate() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $inputs = self::parseVideoInputs();
        $script = $inputs['script'];
        $styleId = $inputs['styleId'];
        $groupCount = $inputs['groupCount'];
        $splitMode = $inputs['splitMode'];
        $motionAuto = $inputs['motionAuto'];
        $seedRefs = $inputs['seedRefs'];

        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);

        if ($styleId === 'auto' && class_exists('\Linked3\Classes\Genesis\GenesisPatchV1006')) {
            try {
                $detected = \Linked3\Classes\Genesis\GenesisPatchV1006::auto_detect_style($script);
                if (!empty($detected)) {
                    $styleId = $detected;
                    error_log("[Linked3] auto_detect_style: script → {$styleId}");
                } else {
                    $styleId = 'cinematic_still'; // safe fallback
                    error_log("[Linked3] auto_detect_style: empty result, fallback to cinematic_still");
                }
            } catch (\Throwable $e) {
                $styleId = 'cinematic_still'; // safe fallback
                error_log("[Linked3] auto_detect_style FAILED: " . $e->getMessage() . " — fallback to cinematic_still");
            }
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        try {
            $beats = self::split_script_to_beats($script, $groupCount, $splitMode);
            [$styleKeywords, $styleNegative] = self::loadStyleKeywords($styleId);
            $seedDna = self::load_seed_dna($seedRefs);

            $fpExtractor = class_exists('\Linked3\Classes\Genesis\FPExtractor') ? new \FPExtractor() : null;
            $groups = [];
            foreach ($beats as $i => $beat) {
                $groups[] = self::processVideoBeat(
                    $beat, $i, $fpExtractor, $motionAuto,
                    $styleKeywords, $styleNegative, $seedDna, $seedRefs
                );
            }

            wp_send_json_success([
                'groups' => $groups,
                'total_groups' => count($groups),
                'style' => $styleId,
                'seed_refs' => $seedRefs,
                'mode' => 'v10_video',
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('视频脚本生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    /**
     * 解析视频 AJAX 输入.
     */
    private static function parseVideoInputs(): array
    {
        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $groupCount = max(1, min(20, intval($_POST['group_count'] ?? 5)));
        $splitMode = sanitize_text_field($_POST['split_mode'] ?? 'auto');
        $motionAuto = sanitize_text_field($_POST['motion_auto'] ?? 'yes');
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));
        return compact('script', 'styleId', 'groupCount', 'splitMode', 'motionAuto', 'seedRefs');
    }

    /**
     * 加载画风关键词 (MJ/SD 等).
     *
     * @return array{0:string,1:string}
     */
    private static function loadStyleKeywords(string $styleId): array
    {
        $styleKeywords = '';
        $styleNegative = '';
        if (class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
            $styleConfig = \GenesisStyleEngine::load($styleId);
            $styleKeywords = $styleConfig['meta_prompt'] ?? '';
            $styleNegative = $styleConfig['negative_keywords'] ?? '';
        }
        return [$styleKeywords, $styleNegative];
    }

    /**
     * 处理单个 beat 的视频组生成 (首尾帧+Motion).
     */
    private static function processVideoBeat(
        $beat, int $i, ?object $fpExtractor, string $motionAuto,
        string $styleKeywords, string $styleNegative, array $seedDna, array $seedRefs
    ): array {
        $beatText = is_array($beat) ? ($beat['text'] ?? '') : (string)$beat;
        $emotion = is_array($beat) ? ($beat['emotion'] ?? 'neutral') : 'neutral';
        $arcPosition = is_array($beat) ? ($beat['arc_position'] ?? '发展') : '发展';

        // FP提取语义核
        $fpCore = ['action_en' => 'a scene depicting daily life', 'who' => 'a figure', 'where' => '', 'emotion' => $emotion];
        if ($fpExtractor) {
            try { $fpCore = $fpExtractor->extract($beatText, ['use_ai' => false]); } catch (\Throwable $e) {}
        }

        $firstFrame = self::build_frame_prompt($fpCore, $styleKeywords, $styleNegative, $seedDna, 'first');
        $lastFrame = self::build_frame_prompt($fpCore, $styleKeywords, $styleNegative, $seedDna, 'last');

        // Motion Prompt Engine
        [$engineGroup, $motionPrompt] = self::generateEngineVideoGroup(
            $motionAuto, $beatText, $emotion, $arcPosition, $styleKeywords, $seedRefs, $i
        );

        // 合并引擎结果与 patch 自建帧 (引擎帧优先, patch 帧作为 fallback)
        if ($engineGroup !== null) {
            return [
                'group_id' => $engineGroup['group_id'] ?: ('VG' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT)),
                'beat_text' => $engineGroup['beat_text'] ?: mb_substr($beatText, 0, 100),
                'arc_position' => $engineGroup['arc_position'] ?: $arcPosition,
                'emotion' => $engineGroup['emotion'] ?: $emotion,
                'first_frame' => $engineGroup['first_frame'] ?: $firstFrame,
                'last_frame' => $engineGroup['last_frame'] ?: $lastFrame,
                'motion_prompt' => $engineGroup['motion_prompt'] ?: $motionPrompt,
                'transition' => $engineGroup['transition'] ?: self::suggest_transition($arcPosition),
            ];
        }
        return [
            'group_id' => 'VG' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'beat_text' => mb_substr($beatText, 0, 100),
            'arc_position' => $arcPosition,
            'emotion' => $emotion,
            'first_frame' => $firstFrame,
            'last_frame' => $lastFrame,
            'motion_prompt' => $motionPrompt,
            'transition' => self::suggest_transition($arcPosition),
        ];
    }

    /**
     * 调用 MotionPromptEngine 生成视频组 (含降级).
     *
     * @return array{0:?array,1:string}
     */
    private static function generateEngineVideoGroup(
        string $motionAuto, string $beatText, string $emotion, string $arcPosition,
        string $styleKeywords, array $seedRefs, int $i
    ): array {
        $engineGroup = null;
        $motionPrompt = '';
        if (!class_exists('\Linked3\Classes\Genesis\MotionPromptEngine')) {
            return [$engineGroup, $motionPrompt];
        }
        if ($motionAuto === 'yes') {
            $beatForEngine = [
                'id' => 'VG' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'text' => $beatText,
                'emotion' => $emotion,
                'arc_position' => $arcPosition,
            ];
            $optsForEngine = [
                'style_keywords' => $styleKeywords,
                'seed_refs' => $seedRefs,
                'platform' => 'video',
            ];
            try {
                $engineGroup = \MotionPromptEngine::generate_video_group($beatForEngine, $optsForEngine);
            } catch (\Throwable $e) {
                $motionParams = \MotionPromptEngine::derive_from_emotion($emotion, $arcPosition);
                $motionPrompt = \MotionPromptEngine::generate($motionParams);
            }
        } else {
            $motionParams = \MotionPromptEngine::derive_from_emotion($emotion, $arcPosition);
            $motionPrompt = \MotionPromptEngine::generate($motionParams);
        }
        return [$engineGroup, $motionPrompt];
    }

    public static function ajax_charts_generate() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $inputs = self::parseChartsInputs();
        $topic = $inputs['topic'];
        $styleId = $inputs['styleId'];
        $moduleCount = $inputs['moduleCount'];
        $seedRefs = $inputs['seedRefs'];
        $cloudCategory = $inputs['cloudCategory'];
        $platform = $inputs['platform'];
        $aspectRatio = $inputs['aspectRatio'];
        $infographicLayout = $inputs['infographicLayout'];
        $infographicStyle = $inputs['infographicStyle'];

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3-ai')]);

        [$cloudSource, $cloudPalette, $cloudTone] = self::loadCloudTemplate($cloudCategory);

        if ($styleId === 'auto' && class_exists('\Linked3\Classes\Genesis\GenesisPatchV1006')) {
            try {
                $detected = \Linked3\Classes\Genesis\GenesisPatchV1006::auto_detect_style($topic);
                if (!empty($detected)) {
                    $styleId = $detected;
                    error_log("[Linked3] auto_detect_style: topic → {$styleId}");
                } else {
                    $styleId = 'cinematic_still'; // safe fallback
                    error_log("[Linked3] auto_detect_style: empty result, fallback to cinematic_still");
                }
            } catch (\Throwable $e) {
                $styleId = 'cinematic_still'; // safe fallback
                error_log("[Linked3] auto_detect_style FAILED: " . $e->getMessage() . " — fallback to cinematic_still");
            }
        }

        @set_time_limit(120);

        try {
            [$styleKeywords, $layoutDesc, $styleDesc] = self::resolveStyleLayoutDesc($styleId, $cloudTone, $infographicLayout, $infographicStyle, $topic, $moduleCount);
            $seedDna = self::load_seed_dna($seedRefs);

            $modules = self::buildSceneModules($moduleCount, $topic, $layoutDesc, $styleDesc, $styleKeywords, $seedDna, $infographicLayout, $infographicStyle, $platform, $aspectRatio);

            wp_send_json_success([
                'modules' => $modules,
                'scenes' => $modules,
                'total_modules' => count($modules),
                'scene_count' => count($modules),
                'style' => $styleId,
                'seed_refs' => $seedRefs,
                'mode' => 'v16_3_charts_4band_unified',
                'cloud_template_source' => $cloudSource,
                'cloud_palette' => $cloudPalette,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('图文脚本生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    /**
     * 解析 charts AJAX 输入 (含安全校验).
     */
    private static function parseChartsInputs(): array
    {
        $topic = wp_strip_all_tags(wp_unslash($_POST['topic'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $rawModuleCount = $_POST['module_count'] ?? '8';
        if ($rawModuleCount === 'auto') {
            $moduleCount = self::auto_select_module_count($topic);
        } else {
            $moduleCount = max(1, min(10, intval($rawModuleCount)));
        }
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));
        $cloudCategory = sanitize_key($_POST['cloud_template_category'] ?? '');
        $platform = sanitize_key($_POST['platform'] ?? 'midjourney');
        $aspectRatio = sanitize_text_field($_POST['aspect_ratio'] ?? '3:4');
        $allowedRatios = ['1:1', '3:4', '4:3', '16:9', '9:16'];
        if (!in_array($aspectRatio, $allowedRatios, true)) {
            $aspectRatio = '3:4';
        }
        $infographicLayout = sanitize_key($_POST['infographic_layout'] ?? 'auto-adapt');
        $infographicStyle = sanitize_key($_POST['infographic_style'] ?? 'xuehui-infographic');
        if ($infographicStyle === 'auto-adapt' || $infographicStyle === 'auto') {
            $infographicStyle = self::auto_select_visual_style($topic);
        }
        return compact('topic', 'styleId', 'moduleCount', 'seedRefs', 'cloudCategory', 'platform', 'aspectRatio', 'infographicLayout', 'infographicStyle');
    }

    /**
     * 加载云模版 (跨生态共享).
     *
     * @return array{0:?string,1:?array,2:string}
     */
    private static function loadCloudTemplate(string $cloudCategory): array
    {
        if (empty($cloudCategory) || !class_exists('\Linked3\Classes\Genesis\CloudTemplateFactory')) {
            return [null, null, ''];
        }
        try {
            $cloudFactory = new \CloudTemplateFactory();
            $shared = $cloudFactory->get_shared_template_for_script($cloudCategory, 'charts');
            return [$shared['source'] ?? null, $shared['palette'] ?? null, $shared['tone'] ?? ''];
        } catch (\Throwable $e) {
            return [null, null, ''];
        }
    }

    /**
     * 解析 layout/style 描述映射.
     *
     * @return array{0:string,1:string,2:string}
     */
    private static function resolveStyleLayoutDesc(string $styleId, string $cloudTone, string $infographicLayout, string $infographicStyle, string $topic, int $moduleCount): array
    {
        $styleKeywords = '';
        if (class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
            $styleConfig = \GenesisStyleEngine::load($styleId);
            $styleKeywords = $styleConfig['meta_prompt'] ?? '';
        }
        if (!empty($cloudTone)) {
            $styleKeywords .= ' (cloud: ' . $cloudTone . ')';
        }

        if ($infographicLayout === 'auto-adapt') {
            $infographicLayout = self::auto_select_layout($topic, $moduleCount);
        }

        $layoutDescMap = [
            'bento-grid' => 'bento grid layout with multiple content blocks in organized cells',
            'linear-progression' => 'linear progression layout showing step-by-step flow left to right',
            'binary-comparison' => 'binary comparison layout with two columns A vs B',
            'comparison-matrix' => 'comparison matrix grid with multiple factors',
            'hierarchical-layers' => 'hierarchical layers pyramid structure',
            'tree-branching' => 'tree branching structure with parent-child nodes',
            'hub-spoke' => 'hub and spoke layout with central concept radiating outward',
            'structural-breakdown' => 'structural breakdown exploded view',
            'iceberg' => 'iceberg model showing surface vs hidden layers',
            'bridge' => 'bridge layout connecting problem to solution',
            'funnel' => 'funnel layout narrowing from top to bottom',
            'dashboard' => 'dashboard layout with KPI metrics and charts',
            'periodic-table' => 'periodic table grid of categorized elements',
            'comic-strip' => 'comic strip sequential panels',
            'story-mountain' => 'story mountain arc with rising tension',
            'jigsaw' => 'jigsaw puzzle interconnected pieces',
            'venn-diagram' => 'venn diagram overlapping circles',
            'winding-roadmap' => 'winding roadmap path with milestones',
            'circular-flow' => 'circular flow cycle diagram',
        ];
        $styleDescMap = [
            'xuehui-infographic' => 'flat infographic design, rounded rectangle cards, clean three-color system (blue #31ACF4 primary, orange #FA9960 accent, purple #A088FF auxiliary), white background with generous whitespace, sans-serif typography (OPPOSans/HarmonyOS Sans), 3-layer hierarchy (module title > sub-topic > detail items), professional knowledge map style, no 3D no shadows no gradients, crisp vector aesthetic, text-embedded cards, numbered badges',
            'bold-graphic' => 'bold comic graphic halftone style',
            'corporate-memphis' => 'corporate memphis flat vector vibrant',
            'technical-schematic' => 'technical schematic blueprint engineering style',
            'craft-handmade' => 'hand-drawn paper craft style, warm textures',
            'claymation' => '3D claymation clay figures stop-motion style',
            'kawaii' => 'Japanese kawaii cute pastel style',
            'storybook-watercolor' => 'soft watercolor storybook illustration',
            'chalkboard' => 'chalk on blackboard style',
            'cyberpunk-neon' => 'cyberpunk neon glow futuristic',
            'origami' => 'origami folded paper geometric style',
            'pixel-art' => 'retro 8-bit pixel art',
            'ikea-manual' => 'ikea manual minimal line art',
            'knolling' => 'knolling organized flat-lay arrangement',
            'lego-brick' => 'lego brick toy construction style',
        ];
        $layoutDesc = $layoutDescMap[$infographicLayout] ?? $layoutDescMap['bento-grid'];
        $styleDesc = $styleDescMap[$infographicStyle] ?? $styleDescMap['xuehui-infographic'];
        return [$styleKeywords, $layoutDesc, $styleDesc];
    }

    /**
     * 构建 scene modules (每镜含完整4Band结构).
     */
    private static function buildSceneModules(int $moduleCount, string $topic, string $layoutDesc, string $styleDesc, string $styleKeywords, array $seedDna, string $infographicLayout, string $infographicStyle, string $platform, string $aspectRatio): array
    {
        $bandDefs = [
            'Hook'  => ['desc' => '吸引注意, 大标题+冲击力画面', 'zone' => '顶部区域'],
            'Body'  => ['desc' => '核心信息, 信息图谱+结构化', 'zone' => '中部区域'],
            'Proof' => ['desc' => '信任背书, 数据/案例/对比',   'zone' => '下部区域'],
            'CTA'   => ['desc' => '行动号召, 按钮引导+紧迫感',  'zone' => '底部区域'],
        ];

        $modules = [];
        for ($i = 0; $i < $moduleCount; $i++) {
            $modules[] = self::buildSingleScene(
                $i, $moduleCount, $topic, $bandDefs, $layoutDesc, $styleDesc,
                $styleKeywords, $seedDna, $infographicLayout, $infographicStyle,
                $platform, $aspectRatio
            );
        }
        return $modules;
    }

    /**
     * 构建单个 scene (4Band整体结构).
     */
    private static function buildSingleScene(int $i, int $moduleCount, string $topic, array $bandDefs, string $layoutDesc, string $styleDesc, string $styleKeywords, array $seedDna, string $infographicLayout, string $infographicStyle, string $platform, string $aspectRatio): array
    {
        $sceneBands = [];
        $bandTextOverlays = [];
        foreach (['Hook', 'Body', 'Proof', 'CTA'] as $bandKey) {
            $sceneBands[$bandKey] = [
                'name' => $bandKey,
                'zone' => $bandDefs[$bandKey]['zone'],
                'desc' => $bandDefs[$bandKey]['desc'],
                'text_overlay' => self::suggest_text_overlay($bandKey, $topic),
            ];
            $bandTextOverlays[] = $sceneBands[$bandKey]['text_overlay'];
        }

        $parts = [];
        $sceneLabel = $moduleCount > 1 ? ('Scene ' . ($i + 1) . ' of ' . $moduleCount . ', ') : '';
        $parts[] = 'A complete infographic, ' . $sceneLabel . 'topic: "' . mb_substr($topic, 0, 30) . '"';
        $parts[] = 'with 4Band vertical layout structure:';
        $parts[] = '[Top Hook zone] big title "' . $sceneBands['Hook']['text_overlay'] . '", strong contrast color, grab attention in 3 seconds';
        $parts[] = '[Middle Body zone] ' . $sceneBands['Body']['text_overlay'] . ', info points with clear layout and icons';
        $parts[] = '[Lower Proof zone] ' . $sceneBands['Proof']['text_overlay'] . ', data charts (bar/pie), professional color, trust endorsement';
        $parts[] = '[Bottom CTA zone] "' . $sceneBands['CTA']['text_overlay'] . '", arrow pointer, action button, guide interaction';
        $parts[] = 'Overall layout: ' . $layoutDesc;
        $parts[] = 'Overall style: ' . $styleDesc;
        if (!empty($seedDna['character'])) $parts[] = $seedDna['character'];
        if (!empty($seedDna['scene'])) $parts[] = $seedDna['scene'];
        if ($styleKeywords) $parts[] = $styleKeywords;

        // v16.0.26: 学会写作2.0风格增强
        if ($infographicStyle === 'xuehui-infographic') {
            $parts[] = 'Color palette: primary blue #31ACF4 (titles, key elements), accent orange #FA9960 (highlights, CTAs), auxiliary purple #A088FF (secondary info), background white #FFFFFF with light blue #E8F0FC cards';
            $parts[] = 'Typography: OPPOSans or HarmonyOS Sans, title 24pt bold, subtitle 14pt medium, body 12pt regular';
            $parts[] = 'Structure: rounded rectangle cards (border-radius 8px), 3-layer hierarchy, numbered circular badges (01-08), generous whitespace between modules';
            $parts[] = 'Quality: crisp vector, no 3D, no shadows, no gradients, flat design, text must be embedded inside shapes, professional knowledge map aesthetic';
        }

        $platformSuffix = self::build_platform_suffix($platform, $aspectRatio);

        return [
            'module_id' => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
            'scene_id' => 'S' . ($i + 1),
            'scene_index' => $i + 1,
            'scene_total' => $moduleCount,
            'band' => '4band-unified',
            'bands' => $sceneBands,
            'title' => $moduleCount > 1 ? ($topic . ' (第' . ($i + 1) . '镜/' . $moduleCount . '镜)') : $topic,
            'visual_prompt' => implode(', ', $parts) . '. ' . $platformSuffix,
            'text_overlay' => implode(' | ', $bandTextOverlays),
            'text_overlays' => $bandTextOverlays,
            'layout' => $infographicLayout,
            'visual_style' => $infographicStyle,
            'aspect_ratio' => $aspectRatio,
            'platform' => $platform,
        ];
    }

}
