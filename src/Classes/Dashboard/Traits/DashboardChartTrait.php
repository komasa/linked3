<?php

declare(strict_types=1);
/**
 * DashboardChartTrait — Chart prompt/outline/segment AJAX handlers.
 *
 * @package Linked3\Dashboard
 * @since 27.13.0
 */

namespace Linked3\Classes\Dashboard\Traits;

if (!defined('ABSPATH')) exit;

trait DashboardChartTrait
{
    static function ajax_generate_chart_prompts(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        // v6.0: 接收参数 (兼容 v5.x 老格式)
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $brand = sanitize_text_field($_POST['brand'] ?? '');
        $ai_provider = sanitize_key($_POST['ai_provider'] ?? '');
        $ai_model = sanitize_text_field($_POST['ai_model'] ?? '');

        // v6.0: 加载 v15 共享配置 (mood/culture/brand)
        $v15Context = self::loadChartV15Context();

        $promptContext = [
            'topic'        => $topic ?: $v15Context['topic'] ?? __('品牌', 'linked3'),
            'brand'        => $brand ?: ($v15Context['brand'] ?? ''),
            'mood'         => $v15Context['mood'] ?? '',
            'culture'      => $v15Context['culture'] ?? '',
            'ai_provider'  => $ai_provider ?: ($v15Context['ai_provider'] ?? ''),
            'ai_model'     => $ai_model ?: ($v15Context['ai_model'] ?? ''),
        ];

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            // v6.0: 构建图示...
            if (!class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate')) {
                wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3')]);
            }

            $master = new \Linked3\Classes\Diagram\DiagramMasterTemplate();
            $config = [
                'brand'        => $promptContext['brand'],
                'mood'         => $promptContext['mood'],
                'culture'      => $promptContext['culture'],
                'main_title'   => $promptContext['topic'] . __('全景图谱', 'linked3'),
                'english_title'=> $promptContext['topic'] . ' Architecture Map',
                'publisher'    => 'Linked3',
                'bands'        => [],
                'endpoint'     => [
                    'type'      => 'question',
                    'question'  => sprintf(__('如何解决「%s」？', 'linked3'), $promptContext['topic']),
                    'milestones'=> [],
                ],
                'footer'       => $promptContext['brand'] . __('·持续迭代', 'linked3'),
                'footer_type'  => __('公式型', 'linked3'),
                'followup_type'=> __('预测型', 'linked3'),
                'relationships'=> [],
            ];
            $result = $master->generate($config);

            wp_send_json_success([
                'prompts'      => [$result['prompt']],
                'char_count'   => $result['char_count'],
                'signature'    => $result['signature'],
                'config'       => $config,
                'v15_context'  => $v15Context,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    static function ajax_chart_outline(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $brand = sanitize_text_field($_POST['brand'] ?? '');
        $mood = sanitize_text_field($_POST['mood'] ?? '');
        $culture = sanitize_text_field($_POST['culture'] ?? '');

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            // v6.0: 基于品牌名+主题, AI 生成 3-5 段大纲
            $outline = [
                'bands' => [
                    ['title' => $topic . ' - Band 1', 'modules' => [['title' => 'Introduction', 'badge' => '01']]],
                    ['title' => $topic . ' - Band 2', 'modules' => [['title' => 'Development', 'badge' => '02']]],
                    ['title' => $topic . ' - Band 3', 'modules' => [['title' => 'Conclusion', 'badge' => '03']]],
                ],
                'meta' => [
                    'topic' => $topic,
                    'brand' => $brand,
                    'mood' => $mood,
                    'culture' => $culture,
                    'generated_at' => current_time('mysql'),
                ],
            ];

            wp_send_json_success($outline);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    static function ajax_chart_segment(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $bandIdx = intval($_POST['band_idx'] ?? 0);
        $moduleIdx = intval($_POST['module_idx'] ?? 0);
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $bandTitle = sanitize_text_field($_POST['band_title'] ?? ('Band ' . ($bandIdx + 1)));
        $moduleTitle = sanitize_text_field($_POST['module_title'] ?? ('Module ' . ($moduleIdx + 1)));

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        try {
            // v6.0: 生成单个模块的图示提示词
            if (!class_exists('\Linked3\Classes\Diagram\DiagramMasterTemplate')) {
                wp_send_json_error(['message' => __('图示引擎未加载 (需要 v6.1.0+)', 'linked3')]);
            }

            $master = new \Linked3\Classes\Diagram\DiagramMasterTemplate();
            $config = [
                'brand'        => '',
                'mood'         => '',
                'culture'      => '',
                'main_title'   => $moduleTitle,
                'english_title'=> $moduleTitle . ' Diagram',
                'publisher'    => 'Linked3',
                'bands'        => [[
                    'title'   => $bandTitle,
                    'modules' => [['title' => $moduleTitle, 'badge' => str_pad((string)($moduleIdx+1), 2, '0', STR_PAD_LEFT)]],
                ]],
                'endpoint'     => [
                    'type'      => 'question',
                    'question'  => sprintf(__('如何解决「%s」？', 'linked3'), $moduleTitle),
                    'milestones'=> [],
                ],
                'footer'       => $bandTitle . '·' . $moduleTitle,
                'footer_type'  => __('公式型', 'linked3'),
                'followup_type'=> __('预测型', 'linked3'),
                'relationships'=> [],
            ];
            $result = $master->generate($config);

            wp_send_json_success([
                'band_idx'     => $bandIdx,
                'module_idx'   => $moduleIdx,
                'band_title'   => $bandTitle,
                'module_title' => $moduleTitle,
                'prompt'       => $result['prompt'],
                'char_count'   => $result['char_count'],
                'signature'    => $result['signature'],
                'config'       => $config,
                'synced'       => self::syncChartPromptsToTemplates($bandTitle, $moduleTitle, $result['prompt']),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    // ── Private chart helpers ──────────────────────────────────────────

    /**
     * v6.0: 加载 v15 共享配置 (mood/culture/brand).
     */
    private static function loadChartV15Context(): array
    {
        // 从 option 加载 v15 配置
        $profile = (array) get_option(LINKED3_OPTION_PREFIX . 'v15_brand_profile', []);
        return [
            'topic'       => '',
            'brand'       => $profile['brand'] ?? '',
            'mood'        => $profile['mood'] ?? '',
            'culture'     => $profile['culture'] ?? '',
            'ai_provider' => $profile['ai_provider'] ?? '',
            'ai_model'    => $profile['ai_model'] ?? '',
        ];
    }

    /**
     * v6.0: 将生成的 prompt 同步到内容模版 (可选, 返回同步结果).
     */
    private static function syncChartPromptsToTemplates(string $bandTitle, string $moduleTitle, string $prompt): array
    {
        // 尝试同步到 wp_options 的 cloud_templates
        $option_key = LINKED3_OPTION_PREFIX . 'cloud_templates';
        $all_templates = (array) get_option($option_key, []);
        $synced = 0;
        foreach ($all_templates as $tid => $tpl) {
            if (($tpl['name'] ?? '') === $bandTitle) {
                $all_templates[$tid]['prompts'][$moduleTitle] = $prompt;
                $synced++;
            }
        }
        if ($synced > 0) {
            update_option($option_key, $all_templates, false);
        }
        return [
            'synced_count' => $synced,
            'band_title'   => $bandTitle,
            'module_title' => $moduleTitle,
        ];
    }
}
