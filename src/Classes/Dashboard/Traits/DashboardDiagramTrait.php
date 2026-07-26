<?php

declare(strict_types=1);
/**
 * DashboardDiagramTrait — Diagram generation/validation/types/multi methods.
 *
 * @package Linked3\Dashboard
 * @since 27.13.0
 */

namespace Linked3\Classes\Dashboard\Traits;

if (!defined('ABSPATH')) exit;

trait DashboardDiagramTrait
{
    static function ajax_diagram_generate(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        [$topic, $content, $brand, $diagramType, $density, $endpointType, $footerText, $mood, $culture, $color]
            = self::parseDiagramInputs();
        $topic = self::resolveDiagramTopic($topic, $content);

        if (!class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate')) {
            wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3')]);
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
            $result = (new \Linked3\Classes\Diagram\DiagramMasterTemplate())->generate($config);
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
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $diagramJson = wp_unslash($_POST['diagram'] ?? '{}');
        $diagram = json_decode($diagramJson, true);
        if (!is_array($diagram)) {
            wp_send_json_error(['message' => __('图示数据无效', 'linked3')]);
        }

        if (!class_exists('\Linked3\Classes\Diagram\DiagramValidation13Dim')) {
            wp_send_json_error(['message' => __('校验引擎未加载', 'linked3')]);
        }

        $validator = new \Linked3\Classes\Diagram\DiagramValidation13Dim();
        $result = $validator->validate($diagram);

        wp_send_json_success($result);
    }

    static function ajax_diagram_types(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $types16 = [];
        $spectrum30 = [];

        if (class_exists('\Linked3\Classes\Diagram\DiagramTypeRegistry')) {
            $types16 = \Linked3\Classes\Diagram\DiagramTypeRegistry::instance()->all();
        }
        if (class_exists('\Linked3\Classes\Diagram\Diagram30Spectrum')) {
            $spectrum30 = \Linked3\Classes\Diagram\Diagram30Spectrum::instance()->all();
        }

        wp_send_json_success([
            'types_16' => $types16,
            'spectrum_30' => $spectrum30,
        ]);
    }

    static function ajax_diagram_generate_multi(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        [$topic, $content, $brand, $diagramType, $density, $endpointType, $_footerText, $mood, $culture, $color]
            = self::parseDiagramInputs();
        $topic = self::resolveDiagramTopic($topic, $content);

        if (!class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate')) {
            wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3')]);
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

    // ── Private diagram helpers ───────────────────────────────────────

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
        if (empty($brand)) $brand = __('知识图谱', 'linked3');
        if (empty($mood)) $mood = __('宏大严密·克制高级', 'linked3');
        if (empty($culture)) $culture = __('结构化知识图谱', 'linked3');
        if (empty($color)) $color = '#2F4F4F';

        return [$topic, $content, $brand, $diagramType, $density, $endpointType, $footerText, $mood, $culture, $color];
    }

    /**
     * 校验 topic+content 非空, 如缺 topic 则从 content 提取.
     */
    private static function resolveDiagramTopic(string $topic, string $content): string
    {
        if (empty($topic) && empty($content)) {
            wp_send_json_error(['message' => __('请填写主题或粘贴文章内容', 'linked3')]);
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
            'main_title' => sprintf(__('《%s全景图谱》', 'linked3'), $topic),
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
                'milestones' => [__('阶段1: 起步', 'linked3'), __('阶段2: 发展', 'linked3'), __('阶段3: 加速', 'linked3'), __('阶段4: 成熟', 'linked3')],
            ],
            'footer' => $footerText ?: ($brand . __('·持续迭代', 'linked3')),
            'footer_type' => __('公式型', 'linked3'),
            'followup_type' => __('预测型', 'linked3'),
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
            $extras['validation_13dim'] = (new \Linked3\Classes\Diagram\DiagramValidation13Dim())->validate($result);
        }
        if (class_exists('\Linked3\Classes\Diagram\DiagramTypeRegistry')) {
            $extras['type_info'] = \Linked3\Classes\Diagram\DiagramTypeRegistry::instance()->get($diagramType);
        }
        if (class_exists('\Linked3\Classes\Diagram\DiagramEndpointRegistry')) {
            $extras['endpoint_info'] = \Linked3\Classes\Diagram\DiagramEndpointRegistry::instance()->get($endpointType);
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
                $result = (new \Linked3\Classes\Diagram\DiagramMasterTemplate())->generate($moduleConfig);
                $prompts[] = [
                    'badge'        => $module['badge'] ?? str_pad((string)$totalModules, 2, '0', STR_PAD_LEFT),
                    'title'        => $module['title'] ?? __('模块', 'linked3') . $totalModules,
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
                'milestones' => [__('阶段1', 'linked3'), __('阶段2', 'linked3'), __('阶段3', 'linked3'), __('阶段4', 'linked3')],
            ],
            'footer' => $brand . '·' . $module['title'],
            'footer_type' => __('公式型', 'linked3'),
            'followup_type' => __('预测型', 'linked3'),
            'relationships' => [],
        ];
    }

    // ── v27.6.16-fix: Stub implementations for methods referenced but
    //    never defined. These provide safe fallbacks so the diagram/chart
    //    generation pipeline doesn't fatal-error. ──────────────────────

    /**
     * Auto-adapt diagram configuration based on content analysis.
     */
    private static function autoAdapt(string $topic, string $content, string $diagramType, string $endpointType, string $density): array
    {
        return [
            'topic'         => $topic ?: self::extractShortTitle($content),
            'diagram_type'  => $diagramType ?: 'flowchart',
            'endpoint_type' => $endpointType ?: 'question',
            'density'       => $density ?: 'medium',
            'bands'         => self::buildDefaultRelationships(),
        ];
    }

    /**
     * AI-expand content into structured bands for diagram generation.
     */
    private static function aiExpandToBands(string $topic, string $content, string $diagramType): array
    {
        // Fallback: split content into 3-5 bands by paragraphs
        $paragraphs = array_filter(array_map('trim', explode("\n\n", $content)));
        $bands = [];
        $count = min(count($paragraphs), 5);
        for ($i = 0; $i < $count; $i++) {
            $bands[] = [
                'title'   => $topic . ' - Part ' . ($i + 1),
                'content' => $paragraphs[$i] ?? '',
            ];
        }
        if (empty($bands)) {
            $bands[] = ['title' => $topic, 'content' => $content];
        }
        return $bands;
    }

    /**
     * Extract a short title from content (first sentence or first 50 chars).
     */
    private static function extractShortTitle(string $content): string
    {
        $content = trim(wp_strip_all_tags($content));
        if (empty($content)) return '';
        // Try first sentence
        if (preg_match('/^[^。！？\.\!\?]+/', $content, $m)) {
            $title = trim($m[0]);
            if (mb_strlen($title) > 50) {
                $title = mb_substr($title, 0, 50) . '...';
            }
            return $title;
        }
        return mb_substr($content, 0, 50);
    }

    /**
     * Get the endpoint question text for a given endpoint type.
     */
    private static function getEndpointQuestion(string $endpointType, string $topic): string
    {
        $questions = [
            'question'  => sprintf(__('如何解决「%s」？', 'linked3'), $topic),
            'compare'   => sprintf(__('「%s」的对比分析', 'linked3'), $topic),
            'process'   => sprintf(__('「%s」的流程是什么？', 'linked3'), $topic),
            'structure' => sprintf(__('「%s」的结构是什么？', 'linked3'), $topic),
            'default'   => $topic,
        ];
        return $questions[$endpointType] ?? $questions['default'];
    }

    /**
     * Build default relationship structure for diagrams.
     */
    private static function buildDefaultRelationships(): array
    {
        return [
            ['from' => 'start', 'to' => 'process', 'label' => ''],
            ['from' => 'process', 'to' => 'end', 'label' => ''],
        ];
    }
}
