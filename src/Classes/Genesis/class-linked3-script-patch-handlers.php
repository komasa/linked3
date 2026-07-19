<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Script_Patch_Handlers
{
    public static function ajax_video_generate() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        $groupCount = intval($_POST['group_count'] ?? 5);
        $groupCount = max(1, min(20, $groupCount));
        $splitMode = sanitize_text_field($_POST['split_mode'] ?? 'auto');
        $motionAuto = sanitize_text_field($_POST['motion_auto'] ?? 'yes');
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));

        if (empty($script)) wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);

        // 画风自动检测
        if ($styleId === 'auto' && class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Patch_V1006')) {
            $styleId = \Linked3_Genesis_Patch_V1006::auto_detect_style($script);
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        try {
            // 1. 拆解剧本为beats
            $beats = self::split_script_to_beats($script, $groupCount, $splitMode);

            // 2. 加载画风关键词
            $styleKeywords = '';
            $styleNegative = '';
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_StyleEngine')) {
                $styleConfig = \Linked3_Genesis_StyleEngine::load($styleId);
                $styleKeywords = $styleConfig['meta_prompt'] ?? '';
                $styleNegative = $styleConfig['negative_keywords'] ?? '';
            }

            // 3. 加载SEED visual_dna
            $seedDna = self::load_seed_dna($seedRefs);

            // 4. 逐beat生成首尾帧+Motion Prompt
            $groups = [];
            $fpExtractor = class_exists('\Linked3\Classes\Genesis\Linked3_FP_Extractor') ? new \Linked3_FP_Extractor() : null;

            foreach ($beats as $i => $beat) {
                $beatText = is_array($beat) ? ($beat['text'] ?? '') : (string)$beat;
                $emotion = is_array($beat) ? ($beat['emotion'] ?? 'neutral') : 'neutral';
                $arcPosition = is_array($beat) ? ($beat['arc_position'] ?? '发展') : '发展';

                // FP提取语义核
                $fpCore = ['action_en' => 'a scene depicting daily life', 'who' => 'a figure', 'where' => '', 'emotion' => $emotion];
                if ($fpExtractor) {
                    try {
                        $fpCore = $fpExtractor->extract($beatText, ['use_ai' => false]);
                    } catch (\Throwable $e) {}
                }

                // 生成首帧Prompt
                $firstFrame = self::build_frame_prompt($fpCore, $styleKeywords, $styleNegative, $seedDna, 'first');

                // 生成尾帧Prompt (与首帧有变化, 体现动作进展)
                $lastFrame = self::build_frame_prompt($fpCore, $styleKeywords, $styleNegative, $seedDna, 'last');

                // v10.1.1 [S1 fix]: 修复 Motion Prompt Engine 签名不匹配导致的 TypeError。
                // 原代码 generate($beatText, $emotion, ...) 传4个标量, 但引擎签名是 generate(array $params)。
                // 修复策略: 调用引擎的 generate_video_group() 一站式生成 (首帧+尾帧+Motion+转场),
                // 符合公理E (DRY), 消除 patch 自带的 build_frame_prompt/suggest_transition 重复实现。
                // 若 motion_auto=no, 仅用 derive_from_emotion + generate 生成 Motion Prompt, 保留 patch 自建帧。
                $motionPrompt = '';
                $engineGroup = null;
                if (class_exists('\Linked3\Classes\Genesis\Linked3_Motion_Prompt_Engine')) {
                    if ($motionAuto === 'yes') {
                        // 一站式: 引擎生成完整视频组 (首帧+尾帧+Motion+转场)
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
                            $engineGroup = \Linked3_Motion_Prompt_Engine::generate_video_group($beatForEngine, $optsForEngine);
                        } catch (\Throwable $e) {
                            // 降级: 仅生成 Motion Prompt 文本
                            $motionParams = \Linked3_Motion_Prompt_Engine::derive_from_emotion($emotion, $arcPosition);
                            $motionPrompt = \Linked3_Motion_Prompt_Engine::generate($motionParams);
                        }
                    } else {
                        // 手动模式: 仅推导 Motion Prompt, 帧由 patch 自建
                        $motionParams = \Linked3_Motion_Prompt_Engine::derive_from_emotion($emotion, $arcPosition);
                        $motionPrompt = \Linked3_Motion_Prompt_Engine::generate($motionParams);
                    }
                }

                // 合并引擎结果与 patch 自建帧 (引擎帧优先, patch 帧作为 fallback)
                if ($engineGroup !== null) {
                    $groups[] = [
                        'group_id' => $engineGroup['group_id'] ?: ('VG' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT)),
                        'beat_text' => $engineGroup['beat_text'] ?: mb_substr($beatText, 0, 100),
                        'arc_position' => $engineGroup['arc_position'] ?: $arcPosition,
                        'emotion' => $engineGroup['emotion'] ?: $emotion,
                        'first_frame' => $engineGroup['first_frame'] ?: $firstFrame,
                        'last_frame' => $engineGroup['last_frame'] ?: $lastFrame,
                        'motion_prompt' => $engineGroup['motion_prompt'] ?: $motionPrompt,
                        'transition' => $engineGroup['transition'] ?: self::suggest_transition($arcPosition),
                    ];
                } else {
                    $groups[] = [
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

    public static function ajax_charts_generate() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topic = wp_strip_all_tags(wp_unslash($_POST['topic'] ?? ''));
        $styleId = sanitize_text_field($_POST['style'] ?? 'documentary_photo');
        // v1.3: 修复模块数量"auto"BUG — 旧版intval('auto')=0被钳为1, 实际只生成1个模块
        // 新版: 检测'auto'字符串, 调用AI决定逻辑(基于内容长度和复杂度)
        $rawModuleCount = $_POST['module_count'] ?? '8';
        if ($rawModuleCount === 'auto') {
            $moduleCount = self::auto_select_module_count($topic);
        } else {
            $moduleCount = intval($rawModuleCount);
            $moduleCount = max(1, min(10, $moduleCount));
        }
        $seedRefs = array_filter(array_map('sanitize_text_field', explode(',', $_POST['seed_refs'] ?? '')));
        // v10.7.0: 跨生态云模版共享
        $cloudCategory = sanitize_key($_POST['cloud_template_category'] ?? '');
        // v1.4: 消费platform参数(前端已传但后端未用) — 不同平台生成不同Prompt后缀
        $platform = sanitize_key($_POST['platform'] ?? 'midjourney');
        // v1.4: 消费aspect_ratio参数(前端已传但后端硬编码--ar 3:4) — 修复硬编码BUG
        $aspectRatio = sanitize_text_field($_POST['aspect_ratio'] ?? '3:4');
        // 安全校验: aspect_ratio只允许规定格式
        $allowedRatios = ['1:1', '3:4', '4:3', '16:9', '9:16'];
        if (!in_array($aspectRatio, $allowedRatios, true)) {
            $aspectRatio = '3:4';
        }
        // v11.3.0 #1: 宝玉20布局+17风格
        // v16.0.26: 默认布局改为auto-adapt(新手自适应), 默认视觉风格改为xuehui-infographic(学会写作2.0同款, 商业生产级)
        $infographicLayout = sanitize_key($_POST['infographic_layout'] ?? 'auto-adapt');
        // v1.3: 信息图技法支持auto-adapt(自动适配), 兜底为xuehui-infographic
        $infographicStyle = sanitize_key($_POST['infographic_style'] ?? 'xuehui-infographic');
        if ($infographicStyle === 'auto-adapt' || $infographicStyle === 'auto') {
            $infographicStyle = self::auto_select_visual_style($topic);
        }

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3-ai')]);

        // v10.7.0: 云模版消费 — 若指定, 从写作生态云模版池拉取 style/palette
        $cloudSource = null;
        $cloudPalette = null;
        $cloudTone = '';
        if (!empty($cloudCategory) && class_exists('\Linked3\Classes\Genesis\Linked3_Cloud_Template_Factory')) {
            try {
                $cloudFactory = new \Linked3_Cloud_Template_Factory();
                $shared = $cloudFactory->get_shared_template_for_script($cloudCategory, 'charts');
                $cloudSource = $shared['source'];
                $cloudPalette = $shared['palette'];
                $cloudTone = $shared['tone'] ?? '';
            } catch (\Throwable $e) {}
        }

        // 画风自动检测
        if ($styleId === 'auto' && class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_Patch_V1006')) {
            $styleId = \Linked3_Genesis_Patch_V1006::auto_detect_style($topic);
        }

        @set_time_limit(120);

        try {
            // 加载画风关键词
            $styleKeywords = '';
            if (class_exists('\Linked3\Classes\Genesis\Linked3_Genesis_StyleEngine')) {
                $styleConfig = \Linked3_Genesis_StyleEngine::load($styleId);
                $styleKeywords = $styleConfig['meta_prompt'] ?? '';
            }
            // v10.7.0: 云模版风格关键词补充
            if (!empty($cloudTone)) {
                $styleKeywords .= ' (cloud: ' . $cloudTone . ')';
            }

            // 加载SEED
            $seedDna = self::load_seed_dna($seedRefs);

            // v16.3.0: $bands变量已移除 (原用于$bands[$i%4]轮询拆分, 已改为每镜完整4Band)
            $modules = [];

            // v11.3.0 #1: 宝玉布局+风格描述映射 (基于feicai4.0)
            // v16.0.23: auto-adapt 自动适配 — 根据内容特征选最佳布局
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
                // v16.0.26: 新增 xuehui-infographic 学会写作2.0同款风格 (商业生产级默认)
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

            // v16.3.0 架构修正 (用户反馈):
            //   原模型: moduleCount个模块, 每模块1个Band($bands[$i%4]轮询), 每模块1个独立提示词 → 拆分式(错误)
            //   新模型: moduleCount个镜, 每镜含完整4Band(Hook+Body+Proof+CTA), 每镜1个整体提示词 → 整体式(正确)
            //
            // 4Band本质是单张信息图的布局结构(顶部Hook/中部Body/下部Proof/底部CTA),
            // 不应拆成4张独立图。每镜输出1个整体提示词, 描述一张含4Band布局的完整信息图。
            $bandDefs = [
                'Hook'  => ['desc' => '吸引注意, 大标题+冲击力画面', 'zone' => '顶部区域'],
                'Body'  => ['desc' => '核心信息, 信息图谱+结构化', 'zone' => '中部区域'],
                'Proof' => ['desc' => '信任背书, 数据/案例/对比',   'zone' => '下部区域'],
                'CTA'   => ['desc' => '行动号召, 按钮引导+紧迫感',  'zone' => '底部区域'],
            ];

            for ($i = 0; $i < $moduleCount; $i++) {
                // v16.3.0: 每镜构建完整4Band结构
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

                // v16.3.0 核心: 每镜生成1个整体提示词 (含4Band布局描述, 非拆分)
                $parts = [];
                $sceneLabel = $moduleCount > 1 ? ('Scene ' . ($i + 1) . ' of ' . $moduleCount . ', ') : '';
                $parts[] = 'A complete infographic, ' . $sceneLabel . 'topic: "' . mb_substr($topic, 0, 30) . '"';
                $parts[] = 'with 4Band vertical layout structure:';
                // 4Band作为一张图的4个区域, 整体描述
                $parts[] = '[Top Hook zone] big title "' . $sceneBands['Hook']['text_overlay'] . '", strong contrast color, grab attention in 3 seconds';
                $parts[] = '[Middle Body zone] ' . $sceneBands['Body']['text_overlay'] . ', info points with clear layout and icons';
                $parts[] = '[Lower Proof zone] ' . $sceneBands['Proof']['text_overlay'] . ', data charts (bar/pie), professional color, trust endorsement';
                $parts[] = '[Bottom CTA zone] "' . $sceneBands['CTA']['text_overlay'] . '", arrow pointer, action button, guide interaction';
                // 整体布局+风格 (非每Band独立)
                $parts[] = 'Overall layout: ' . $layoutDesc;
                $parts[] = 'Overall style: ' . $styleDesc;
                if (!empty($seedDna['character'])) $parts[] = $seedDna['character'];
                if (!empty($seedDna['scene'])) $parts[] = $seedDna['scene'];
                if ($styleKeywords) $parts[] = $styleKeywords;

                // v16.0.26: 学会写作2.0风格增强 — 追加商业生产级规格 (整体应用, 非每Band)
                if ($infographicStyle === 'xuehui-infographic') {
                    $parts[] = 'Color palette: primary blue #31ACF4 (titles, key elements), accent orange #FA9960 (highlights, CTAs), auxiliary purple #A088FF (secondary info), background white #FFFFFF with light blue #E8F0FC cards';
                    $parts[] = 'Typography: OPPOSans or HarmonyOS Sans, title 24pt bold, subtitle 14pt medium, body 12pt regular';
                    $parts[] = 'Structure: rounded rectangle cards (border-radius 8px), 3-layer hierarchy, numbered circular badges (01-08), generous whitespace between modules';
                    $parts[] = 'Quality: crisp vector, no 3D, no shadows, no gradients, flat design, text must be embedded inside shapes, professional knowledge map aesthetic';
                }

                // v1.4: 构建平台特定的Prompt后缀
                $platformSuffix = self::build_platform_suffix($platform, $aspectRatio);

                // v16.3.0: 模块结构改为"镜"结构 — 含完整4Band + 1个整体提示词
                $modules[] = [
                    'module_id' => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT), // S=Scene(镜)
                    'scene_id' => 'S' . ($i + 1), // v16.3.0: 镜ID
                    'scene_index' => $i + 1,
                    'scene_total' => $moduleCount,
                    'band' => '4band-unified', // v16.3.0: 标注为4Band整体 (非单个Band)
                    'bands' => $sceneBands, // v16.3.0: 完整4Band结构 (供前端布局预览)
                    'title' => $moduleCount > 1 ? ($topic . ' (第' . ($i + 1) . '镜/' . $moduleCount . '镜)') : $topic,
                    'visual_prompt' => implode(', ', $parts) . '. ' . $platformSuffix, // v16.3.0: 1个整体提示词
                    'text_overlay' => implode(' | ', $bandTextOverlays), // v16.3.0: 4Band文字合并
                    'text_overlays' => $bandTextOverlays, // v16.3.0: 4Band文字数组 (供前端分区域显示)
                    'layout' => $infographicLayout,
                    'visual_style' => $infographicStyle,
                    'aspect_ratio' => $aspectRatio,
                    'platform' => $platform,
                ];
            }

            wp_send_json_success([
                'modules' => $modules,
                'scenes' => $modules, // v16.3.0: 别名, 前端可读scenes或modules
                'total_modules' => count($modules),
                'scene_count' => count($modules), // v16.3.0: 镜数量
                'style' => $styleId,
                'seed_refs' => $seedRefs,
                'mode' => 'v16_3_charts_4band_unified', // v16.3.0: 标记新模式
                'cloud_template_source' => $cloudSource,
                'cloud_palette' => $cloudPalette,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('图文脚本生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

}
