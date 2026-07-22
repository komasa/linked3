<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
/**
 * DashboardMediaAjax — G8 extraction.
 * @since 27.13.0
 */
class DashboardMediaAjax
{
        public static function ajax_video_generate_script() : mixed { return DashboardVideoAjax::ajax_video_generate_script(); }

        public static function ajax_video_outline() : mixed { return DashboardVideoAjax::ajax_video_outline(); }

        public static function ajax_video_segment() : mixed { return DashboardVideoAjax::ajax_video_segment(); }

    static function ajax_diagram_generate(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        [$topic, $content, $brand, $diagramType, $density, $endpointType, $footerText, $mood, $culture, $color]
            = self::parseDiagramInputs();
        $topic = self::resolveDiagramTopic($topic, $content);

        if (!class_exists('\Linked3\Classes\Dashboard\DiagramMasterTemplate')) {
            wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3-ai')]);
        }

        // v6.5.2: "auto" 自动适配
        if ($diagramType === 'auto' || $endpointType === 'auto' || $density === 'auto') {
            $autoConfig = self::autoAdapt($topic, $content, $diagramType, $endpointType, $density);
            $diagramType = $autoConfig['diagram_type'];
            $endpointType = $autoConfig['endpoint_type'];
            $density = $autoConfig['density'];
        }

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            $bands = self::aiExpandToBands($topic, $content, $diagramType);
            $config = self::buildDiagramConfig($topic, $brand, $mood, $culture, $color, $density, $endpointType, $footerText, $bands);
            $result = (new \DiagramMasterTemplate())->generate($config);
            $extras = self::collectDiagramExtras($result, $diagramType, $endpointType);

            wp_send_json_success(array_merge([
                'diagram_id'     => $result['diagram_id'],
                'prompt'         => $result['prompt'],
                'meta'           => $result['meta'],
                'script'         => $result['script'],
                'validation'     => $result['validation'],
                'char_count'     => $result['char_count'],
                'signature'      => $result['signature'],
                'config'         => $config,
                'auto_adapted'   => [
                    'diagram_type' => $diagramType,
                    'endpoint_type' => $endpointType,
                    'density' => $density,
                ],
            ], $extras));
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    static function ajax_diagram_validate(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $diagramJson = wp_unslash($_POST['diagram'] ?? '{}');
        $diagram = json_decode($diagramJson, true);
        if (!is_array($diagram)) {
            wp_send_json_error(['message' => __('图示数据无效', 'linked3-ai')]);
        }

        if (!class_exists('\Linked3\Classes\Diagram\DiagramValidation13Dim')) {
            wp_send_json_error(['message' => __('校验引擎未加载', 'linked3-ai')]);
        }

        $validator = new \DiagramValidation13Dim();
        $result = $validator->validate($diagram);

        wp_send_json_success($result);
    }

    static function ajax_diagram_types(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $types16 = [];
        $spectrum30 = [];

        if (class_exists('\Linked3\Classes\Dashboard\DiagramTypeRegistry')) {
            $types16 = \DiagramTypeRegistry::instance()->all();
        }
        if (class_exists('\Linked3\Classes\Dashboard\Diagram30Spectrum')) {
            $spectrum30 = \Diagram30Spectrum::instance()->all();
        }

        wp_send_json_success([
            'types_16' => $types16,
            'spectrum_30' => $spectrum30,
        ]);
    }

    static function ajax_diagram_generate_multi(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        [$topic, $content, $brand, $diagramType, $density, $endpointType, $_footerText, $mood, $culture, $color]
            = self::parseDiagramInputs();
        $topic = self::resolveDiagramTopic($topic, $content);

        if (!class_exists('\Linked3\Classes\Dashboard\DiagramMasterTemplate')) {
            wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3-ai')]);
        }

        // 自动适配
        if ($diagramType === 'auto' || $endpointType === 'auto' || $density === 'auto') {
            $autoConfig = self::autoAdapt($topic, $content, $diagramType, $endpointType, $density);
            $diagramType = $autoConfig['diagram_type'];
            $endpointType = $autoConfig['endpoint_type'];
            $density = $autoConfig['density'];
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        try {
            $bands = self::aiExpandToBands($topic, $content, $diagramType);
            [$prompts, $totalModules] = self::buildMultiModulePrompts($bands, $brand, $mood, $culture, $color, $density, $endpointType, $diagramType);

            $overview = [
                'topic'        => $topic,
                'brand'        => $brand,
                'diagram_type' => $diagramType,
                'endpoint_type'=> $endpointType,
                'density'      => $density,
                'band_count'   => count($bands),
                'module_count' => $totalModules,
                'mood'         => $mood,
                'culture'      => $culture,
                'color'        => $color,
            ];

            wp_send_json_success([
                'overview'    => $overview,
                'prompts'     => $prompts,
                'total_count' => count($prompts),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    /**
     * 解析 diagram_generate / diagram_generate_multi 共享输入.
     *
     * @return array [topic, content, brand, diagramType, density, endpointType, footerText, mood, culture, color]
     */
    private static function parseDiagramInputs(): array
    {
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $brand = sanitize_text_field($_POST['brand'] ?? '');
        $diagramType = sanitize_text_field($_POST['diagram_type'] ?? 'auto');
        $density = sanitize_text_field($_POST['density'] ?? 'auto');
        $endpointType = sanitize_text_field($_POST['endpoint_type'] ?? 'auto');
        $footerText = sanitize_text_field($_POST['footer'] ?? '');
        $mood = sanitize_text_field($_POST['mood'] ?? '');
        $culture = sanitize_text_field($_POST['culture'] ?? '');
        $color = sanitize_text_field($_POST['color'] ?? '');

        // v6.5.5: 空值填默认
        if (empty($brand)) $brand = '知识图谱';
        if (empty($mood)) $mood = '宏大严密·克制高级';
        if (empty($culture)) $culture = '结构化知识图谱';
        if (empty($color)) $color = '#2F4F4F';

        return [$topic, $content, $brand, $diagramType, $density, $endpointType, $footerText, $mood, $culture, $color];
    }

    /**
     * 校验 topic+content 非空, 如缺 topic 则从 content 提取.
     */
    private static function resolveDiagramTopic(string $topic, string $content): string
    {
        if (empty($topic) && empty($content)) {
            wp_send_json_error(['message' => __('请填写主题或粘贴文章内容', 'linked3-ai')]);
        }
        if (empty($topic) && !empty($content)) {
            $topic = self::extractShortTitle($content);
        }
        return $topic;
    }

    /**
     * 构建 diagram 配置.
     */
    private static function buildDiagramConfig(string $topic, string $brand, string $mood, string $culture, string $color, string $density, string $endpointType, string $footerText, array $bands): array
    {
        return [
            'id' => 'DIAGRAM_' . date('Ymd_His'),
            'brand' => $brand,
            'main_title' => "《{$topic}全景图谱》",
            'english_title' => $topic . ' Architecture Map',
            'mood' => $mood,
            'culture' => $culture,
            'theme_color' => $color,
            'density' => $density,
            'publisher' => 'Linked3',
            'bands' => $bands,
            'endpoint' => [
                'type' => $endpointType,
                'question' => self::getEndpointQuestion($endpointType, $topic),
                'milestones' => ['阶段1: 起步', '阶段2: 发展', '阶段3: 加速', '阶段4: 成熟'],
            ],
            'footer' => $footerText ?: ($brand . '·持续迭代'),
            'footer_type' => '公式型',
            'followup_type' => '预测型',
            'relationships' => self::buildDefaultRelationships(),
        ];
    }

    /**
     * 收集 diagram 附加信息 (13维校验 + 类型/Endpoint 信息).
     */
    private static function collectDiagramExtras(array $result, string $diagramType, string $endpointType): array
    {
        $extras = ['validation_13dim' => [], 'type_info' => [], 'endpoint_info' => []];
        if (class_exists('\Linked3\Classes\Diagram\DiagramValidation13Dim')) {
            $extras['validation_13dim'] = (new \DiagramValidation13Dim())->validate($result);
        }
        if (class_exists('\Linked3\Classes\Dashboard\DiagramTypeRegistry')) {
            $extras['type_info'] = \DiagramTypeRegistry::instance()->get($diagramType);
        }
        if (class_exists('\Linked3\Classes\Dashboard\DiagramEndpointRegistry')) {
            $extras['endpoint_info'] = \DiagramEndpointRegistry::instance()->get($endpointType);
        }
        return $extras;
    }

    /**
     * 为每个模块构建独立图示提示词.
     *
     * @return array{0:array,1:int} [prompts, totalModules]
     */
    private static function buildMultiModulePrompts(array $bands, string $brand, string $mood, string $culture, string $color, string $density, string $endpointType, string $diagramType): array
    {
        $prompts = [];
        $totalModules = 0;
        foreach ($bands as $bandIdx => $band) {
            foreach ($band['modules'] ?? [] as $moduleIdx => $module) {
                $totalModules++;
                $moduleConfig = self::buildModuleConfig($band, $bandIdx, $module, $brand, $mood, $culture, $color, $density, $endpointType);
                $result = (new \DiagramMasterTemplate())->generate($moduleConfig);
                $prompts[] = [
                    'badge'        => $module['badge'] ?? str_pad((string)$totalModules, 2, '0', STR_PAD_LEFT),
                    'title'        => $module['title'] ?? '模块' . $totalModules,
                    'band'         => $band['title'] ?? ('Band ' . ($bandIdx + 1)),
                    'diagram_type' => $module['diagram_type'] ?? $diagramType,
                    'cognitive'    => $module['cognitive_level'] ?? '[R]',
                    'prompt'       => $result['prompt'],
                    'char_count'   => $result['char_count'],
                    'sub_topics'   => array_map(fn($st) => $st['title'] ?? '', $module['sub_topics'] ?? []),
                    'text_embedded'=> $module['text_embedded'] ?? [],
                ];
            }
        }
        return [$prompts, $totalModules];
    }

    /**
     * 构建单个模块的 mini-config.
     */
    private static function buildModuleConfig(array $band, int $bandIdx, array $module, string $brand, string $mood, string $culture, string $color, string $density, string $endpointType): array
    {
        return [
            'id' => 'DIAGRAM_' . date('Ymd_His') . '_' . ($module['badge'] ?? ''),
            'brand' => $brand,
            'main_title' => $module['title'],
            'english_title' => $module['title'] . ' Diagram',
            'mood' => $mood,
            'culture' => $culture,
            'theme_color' => $color,
            'density' => $density,
            'publisher' => 'Linked3',
            'bands' => [[
                'act_name' => $band['act_name'] ?? ('Band' . ($bandIdx + 1)),
                'title' => $band['title'] ?? ('Band ' . ($bandIdx + 1)),
                'tint' => $band['tint'] ?? 'Light Blue',
                'modules' => [$module],
            ]],
            'endpoint' => [
                'type' => $endpointType,
                'question' => self::getEndpointQuestion($endpointType, $module['title']),
                'milestones' => ['阶段1', '阶段2', '阶段3', '阶段4'],
            ],
            'footer' => $brand . '·' . $module['title'],
            'footer_type' => '公式型',
            'followup_type' => '预测型',
            'relationships' => [],
        ];
    }

    static function ajax_generate_chart_prompts(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $count = (int) ($_POST['count'] ?? 3);
        $chart_codes_raw = sanitize_text_field($_POST['chart_codes'] ?? '');
        $chart_codes = array_filter(array_map('trim', explode(',', $chart_codes_raw)));
        $sync_to_templates = !empty($_POST['sync_to_templates']);

        $v15_context = self::loadChartV15Context();

        if (empty($topic)) {
            wp_send_json_error(['message' => __('请填写主题', 'linked3-ai')]);
        }

        if (!class_exists('\Linked3\Classes\V15\V15ChartPromptGenerator')) {
            wp_send_json_error(['message' => __('图示提示词生成器未加载', 'linked3-ai')]);
        }

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            $gen = new \Linked3\Classes\V15\V15ChartPromptGenerator();
            $result = $gen->generate($topic, $v15_context, [
                'chart_codes' => $chart_codes,
                'category'    => $category,
                'count'       => $count,
                'user_id'     => get_current_user_id(),
            ]);

            $synced = self::syncChartPromptsToTemplates($result['prompts'] ?? [], $sync_to_templates);

            wp_send_json_success([
                'prompts'         => $result['prompts'],
                'usage'           => $result['usage'] ?? [],
                'provider'        => $result['provider'] ?? '',
                'model'           => $result['model'] ?? '',
                'v15_context'     => $v15_context,
                'synced_to_templates' => $synced,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    /**
     * 加载 chart_prompts 的 V15 上下文 (品牌配置 + 前端覆盖).
     */
    private static function loadChartV15Context(): array
    {
        $brand_profile_id = (int) ($_POST['brand_profile_id'] ?? 0);
        $v15_context = [];
        if ($brand_profile_id > 0 && class_exists('\\Linked3\\Classes\\V15\\V15BrandProfileManager')) {
            $bp_mgr = \Linked3\Classes\V15\V15BrandProfileManager::instance();
            $all_profiles = $bp_mgr->get_all_profiles(get_current_user_id());
            foreach ($all_profiles as $bp) {
                if ((int) $bp['id'] === $brand_profile_id) {
                    $v15_context = $bp_mgr->profile_to_placeholders($bp);
                    break;
                }
            }
        }
        foreach (['brand','signature','color','mood','culture','platform','density','product_type'] as $k) {
            $val = sanitize_text_field($_POST['v15_' . $k] ?? '');
            if (!empty($val)) $v15_context[$k] = $val;
        }
        return $v15_context;
    }

    /**
     * 同步 chart prompts 到云模板.
     */
    private static function syncChartPromptsToTemplates(array $prompts, bool $sync_to_templates): int
    {
        if (!$sync_to_templates || empty($prompts) || !class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            return 0;
        }
        $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
        $synced = 0;
        foreach ($prompts as $p) {
            if (empty($p['prompt'])) continue;
            $tpl_mgr->add(
                sprintf('图示 %s %s (%s)', $p['dna_code'], $p['chart_name'], date('m-d H:i')),
                'visual',
                ['prompt' => $p['prompt'], 'dna_code' => $p['dna_code'], 'chart_name' => $p['chart_name']]
            );
            $synced++;
        }
        return $synced;
    }

    static function ajax_chart_outline(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $chart_codes_raw = sanitize_text_field($_POST['chart_codes'] ?? '');
        $chart_codes = array_filter(array_map('trim', explode(',', $chart_codes_raw)));
        $category = sanitize_text_field($_POST['category'] ?? '');
        $count = (int) ($_POST['count'] ?? 3);

        if (!class_exists('\\Linked3\\Classes\\V15\\V15ChartPromptGenerator')) {
            wp_send_json_error(['message' => __('图示脚本生成器未加载', 'linked3')]);
        }

        $gen = new \Linked3\Classes\V15\V15ChartPromptGenerator();
        $all = $gen->get_chart_dna_index();

        $outline = [];
        if (!empty($chart_codes)) {
            foreach ($all as $c) {
                if (in_array($c['dna_code'], $chart_codes, true)) $outline[] = $c;
            }
        } elseif (!empty($category)) {
            foreach ($all as $c) {
                if ($c['category'] === $category) $outline[] = $c;
            }
            $outline = array_slice($outline, 0, $count);
        } else {
            $default_codes = ['D18', 'D08', 'D03', 'D19', 'D21'];
            foreach ($all as $c) {
                if (in_array($c['dna_code'], $default_codes, true)) $outline[] = $c;
            }
            $outline = array_slice($outline, 0, $count);
        }

        wp_send_json_success(['outline' => $outline]);
    }

    static function ajax_chart_segment(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $chart_item_json = wp_unslash($_POST['chart_item'] ?? '{}');
        $chart_item = json_decode($chart_item_json, true);
        if (!is_array($chart_item) || empty($chart_item['dna_code'])) {
            wp_send_json_error(['message' => __('图示项无效', 'linked3')]);
        }
        $brand_profile_id = (int) ($_POST['brand_profile_id'] ?? 0);
        $v15_context = self::build_v15_context_from_request($brand_profile_id, get_current_user_id());

        if (!class_exists('\\Linked3\\Classes\\V15\\V15ChartPromptGenerator')) {
            wp_send_json_error(['message' => __('图示脚本生成器未加载', 'linked3')]);
        }

        @set_time_limit(60);
        try {
            $gen = new \Linked3\Classes\V15\V15ChartPromptGenerator();
            $result = $gen->generate_single($topic, $chart_item, $v15_context, [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

}
