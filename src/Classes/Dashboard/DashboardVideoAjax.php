<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class DashboardVideoAjax
{
    public static function ajax_video_generate_script(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $inputs = self::parseVideoScriptInputs();
        if (is_array($inputs) && isset($inputs['error'])) {
            wp_send_json_error(['message' => $inputs['error']]);
            return;
        }
        [
            'title' => $title, 'content' => $content, 'style' => $style,
            'duration' => $duration, 'output_mode' => $output_mode,
            'sync_frames_to_templates' => $sync_frames_to_templates,
            'video_template_idx' => $video_template_idx, 'brand_profile_id' => $brand_profile_id,
        ] = $inputs;

        if (!class_exists('\\Linked3\\Classes\\Media\\VideoGenerator')) {
            wp_send_json_error(['message' => __('视频生成模块未加载', 'linked3')]);
        }

        $v15_context = self::loadV15Context($brand_profile_id, $inputs);
        $custom_prompt = self::loadVideoTemplatePrompt($video_template_idx);

        $timeout = $duration <= 60 ? 120 : 180;
        if (function_exists('set_time_limit')) @set_time_limit($timeout + 120);
        @ini_set('max_execution_time', (string) ($timeout + 120));
        @ini_set('memory_limit', '512M');
        while (ob_get_level() > 0) @ob_end_clean();

        self::logVideoRequestStart($title, $duration, $video_template_idx, $brand_profile_id, $timeout);

        try {
            $vg = new \Linked3\Classes\Media\VideoGenerator();
            if ($output_mode === 'frames') {
                self::executeFramesMode($vg, $title, $content, $style, $duration, $custom_prompt, $v15_context, $sync_frames_to_templates);
            } else {
                self::executeScenesMode($vg, $title, $content, $style, $duration, $custom_prompt, $v15_context);
            }
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'trace'   => WP_DEBUG ? $e->getTraceAsString() : '',
            ]);
        }
    }

    /**
     * 解析 video_generate_script AJAX 输入.
     *
     * @return array|string[] 输入数组或 ['error' => '...']
     */
    private static function parseVideoScriptInputs(): array
    {
        $title   = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $style   = sanitize_text_field($_POST['style'] ?? '解说');
        $duration = (int) ($_POST['duration'] ?? 60);
        $output_mode = sanitize_text_field($_POST['output_mode'] ?? 'scenes');
        $sync_frames_to_templates = !empty($_POST['sync_frames_to_templates']);

        if (empty($title) && empty($content)) {
            return ['error' => __('标题或内容至少填一个', 'linked3')];
        }

        return [
            'title' => $title, 'content' => $content, 'style' => $style,
            'duration' => $duration, 'output_mode' => $output_mode,
            'sync_frames_to_templates' => $sync_frames_to_templates,
            'video_template_idx' => sanitize_text_field($_POST['video_template'] ?? ''),
            'brand_profile_id'   => (int) ($_POST['brand_profile_id'] ?? 0),
            'v15_brand'     => sanitize_text_field($_POST['v15_brand'] ?? ''),
            'v15_signature' => sanitize_text_field($_POST['v15_signature'] ?? ''),
            'v15_color'     => sanitize_text_field($_POST['v15_color'] ?? ''),
            'v15_mood'      => sanitize_text_field($_POST['v15_mood'] ?? ''),
            'v15_culture'   => sanitize_text_field($_POST['v15_culture'] ?? ''),
            'v15_platform'  => sanitize_text_field($_POST['v15_platform'] ?? ''),
            'v15_density'   => sanitize_text_field($_POST['v15_density'] ?? ''),
            'v15_product'   => sanitize_text_field($_POST['v15_product_type'] ?? ''),
        ];
    }

    /**
     * 加载 V15 品牌上下文 (品牌配置 + 前端覆盖).
     */
    private static function loadV15Context(int $brand_profile_id, array $inputs): array
    {
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
        // 前端输入优先级最高
        foreach (['brand' => 'v15_brand', 'signature' => 'v15_signature', 'color' => 'v15_color', 'mood' => 'v15_mood', 'culture' => 'v15_culture', 'platform' => 'v15_platform', 'density' => 'v15_density', 'product_type' => 'v15_product'] as $ctx_key => $input_key) {
            if (!empty($inputs[$input_key])) {
                $v15_context[$ctx_key] = $inputs[$input_key];
            }
        }
        return $v15_context;
    }

    /**
     * 从管线模板读取视频脚本提示词.
     */
    private static function loadVideoTemplatePrompt(string $video_template_idx): string
    {
        if ($video_template_idx === '' || !class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            return '';
        }
        $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
        $video_templates = $tpl_mgr->get_pipeline_templates('video_script');
        $idx = (int) $video_template_idx;
        return $video_templates[$idx]['config']['prompt'] ?? '';
    }

    /**
     * 记录请求开始日志.
     */
    private static function logVideoRequestStart(string $title, int $duration, string $tpl_idx, int $brand_id, int $timeout): void
    {
        if (!class_exists('\\Linked3\\Includes\\Log\\Logger')) return;
        $log = \Linked3\Includes\Log\Logger::instance();
        if (!$log) return;
        $log->info('video', 'AJAX video_generate_script start', [
            'title' => mb_substr($title, 0, 80),
            'duration' => $duration,
            'template_idx' => $tpl_idx,
            'brand_profile_id' => $brand_id,
            'timeout' => $timeout,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);
    }

    /**
     * 执行 frames 模式 (图片提示词 + 剧本 + 画外音交替).
     */
    private static function executeFramesMode(
        \Linked3\Classes\Media\VideoGenerator $vg,
        string $title, string $content, string $style, int $duration,
        string $custom_prompt, array $v15_context, bool $sync_frames_to_templates
    ): void {
        $result = $vg->generate_frames_script($title, $content, [
            'style'         => $style,
            'duration'      => $duration,
            'user_id'       => get_current_user_id(),
            'custom_prompt' => $custom_prompt,
            'v15_context'   => $v15_context,
        ]);

        $frames = $result['frames'] ?? [];
        $total_duration = 0;
        foreach ($frames as $f) {
            $total_duration += (int) ($f['duration'] ?? 0);
        }

        $synced = 0;
        if ($sync_frames_to_templates && !empty($frames) && class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
            foreach ($frames as $f) {
                if ($f['type'] !== 'image' || empty($f['visual_prompt'])) continue;
                $tpl_mgr->add(
                    sprintf('视频分镜 #%d (%s)', $f['index'], date('m-d H:i')),
                    'visual',
                    ['prompt' => $f['visual_prompt'], 'scene_index' => $f['index']]
                );
                $synced++;
            }
        }

        $parse_warning = '';
        if (empty($frames) && !empty($result['script'])) {
            $parse_warning = 'AI 返回了内容但 JSON 解析失败, 请检查"原始输出"';
        }

        wp_send_json_success([
            'frames'         => $frames,
            'script'         => $result['script'] ?? '',
            'total_duration' => $total_duration,
            'usage'          => $result['usage'] ?? [],
            'provider'       => $result['provider'] ?? '',
            'model'          => $result['model'] ?? '',
            'v15_context'    => $v15_context,
            'parse_warning'  => $parse_warning,
            'synced_to_templates' => $synced,
            'output_mode'    => 'frames',
        ]);
    }

    /**
     * 执行 scenes 模式 (默认).
     */
    private static function executeScenesMode(
        \Linked3\Classes\Media\VideoGenerator $vg,
        string $title, string $content, string $style, int $duration,
        string $custom_prompt, array $v15_context
    ): void {
        $result = $vg->generate_script($title, $content, [
            'style'         => $style,
            'duration'      => $duration,
            'user_id'       => get_current_user_id(),
            'custom_prompt' => $custom_prompt,
            'v15_context'   => $v15_context,
        ]);

        $scenes = $result['scenes'] ?? [];
        $total_duration = (int) ($result['total_duration'] ?? 0);

        $parse_warning = '';
        if (empty($scenes) && !empty($result['script'])) {
            $parse_warning = 'AI 返回了内容但 JSON 解析失败, 请检查"原始输出"并调整提示词或更换模型';
        }

        wp_send_json_success([
            'scenes'         => $scenes,
            'script'         => $result['script'] ?? '',
            'total_duration' => $total_duration,
            'usage'          => $result['usage'] ?? [],
            'provider'       => $result['provider'] ?? '',
            'model'          => $result['model'] ?? '',
            'v15_context'    => $v15_context,
            'parse_warning'  => $parse_warning,
            'output_mode'    => 'scenes',
        ]);
    }

    public static function ajax_video_outline(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $duration = (int) ($_POST['duration'] ?? 60);
        $output_mode = sanitize_text_field($_POST['output_mode'] ?? 'scenes');
        $brand_profile_id = (int) ($_POST['brand_profile_id'] ?? 0);

        if (empty($title)) wp_send_json_error(['message' => __('请填写标题', 'linked3')]);

        $v15_context = self::build_v15_context_from_request($brand_profile_id, get_current_user_id());

        if (!class_exists('\\Linked3\\Classes\\Media\\VideoGenerator')) {
            wp_send_json_error(['message' => __('视频模块未加载', 'linked3')]);
        }

        @set_time_limit(60);
        try {
            $vg = new \Linked3\Classes\Media\VideoGenerator();
            $result = $vg->generate_outline($title, $content, [
                'duration' => $duration,
                'output_mode' => $output_mode,
                'v15_context' => $v15_context,
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function ajax_video_segment(): void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $output_mode = sanitize_text_field($_POST['output_mode'] ?? 'scenes');
        $segment_index = (int) ($_POST['segment_index'] ?? 0);
        $outline_json = wp_unslash($_POST['outline'] ?? '[]');
        $outline = json_decode($outline_json, true);
        if (!is_array($outline) || !isset($outline[$segment_index])) {
            wp_send_json_error(['message' => __('大纲项不存在', 'linked3')]);
        }
        $outline_item = $outline[$segment_index];
        $previous_summary = sanitize_text_field($_POST['previous_summary'] ?? '');
        $brand_profile_id = (int) ($_POST['brand_profile_id'] ?? 0);

        $v15_context = self::build_v15_context_from_request($brand_profile_id, get_current_user_id());

        if (!class_exists('\\Linked3\\Classes\\Media\\VideoGenerator')) {
            wp_send_json_error(['message' => __('视频模块未加载', 'linked3')]);
        }

        @set_time_limit(60);
        try {
            $vg = new \Linked3\Classes\Media\VideoGenerator();

            // v5.3.5: 传 next_title + is_last (frames 模式飞车转场需要)
            $next_title = '';
            $is_last = false;
            if ($output_mode === 'frames') {
                $is_last = ($segment_index >= count($outline) - 1);
                if (!$is_last && isset($outline[$segment_index + 1])) {
                    $next_title = $outline[$segment_index + 1]['title'] ?? '';
                }
            }

            $opts = [
                'title' => $title,
                'content' => $content,
                'v15_context' => $v15_context,
                'user_id' => get_current_user_id(),
                'previous_summary' => $previous_summary,
                'next_title' => $next_title,
                'is_last' => $is_last,
            ];

            if ($output_mode === 'frames') {
                $result = $vg->generate_frame_segment($outline_item, $opts);
                wp_send_json_success([
                    'frames' => $result['frames'],
                    'usage' => $result['usage'],
                    'segment_index' => $segment_index,
                ]);
            } else {
                $result = $vg->generate_scene_segment($outline_item, $opts);
                wp_send_json_success([
                    'scene' => $result['scene'],
                    'usage' => $result['usage'],
                    'segment_index' => $segment_index,
                ]);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

}
