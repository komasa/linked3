<?php


declare(strict_types=1);
namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class GenesisV9Processor
{
    public static function ajax_genesis_generate_v9()
    : void {
        // Phase 1: Security & input
        $input = self::validate_v9_generate_request();

        // Phase 2: Setup environment
        $prev_er = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        $prev_de = @ini_set('display_errors', '0');
        if (function_exists('ob_start')) ob_start();
        @set_time_limit(300);

        try {
            // Phase 3: Delegate to GenesisProcessorDelegates
            $result = \Linked3\Classes\Dashboard\GenesisProcessorDelegates::genesisGenerateMultiInternal(
                $input['script'],
                $input['style_id'],
                $input['platform'],
                $input['panel_count_raw'],
                null,
                $input['extra_options']
            );

            if (function_exists('ob_end_clean')) @ob_end_clean();

            if (isset($result['error']) || empty($result['panels'])) {
                wp_send_json_error([
                    'message' => $result['error'] ?? __('生成失败: 无分镜结果', 'linked3-ai'),
                    'diagnostic' => $result['diagnostic'] ?? [],
                ]);
            }

            wp_send_json_success($result);
        } catch (\Throwable $e) {
            if (function_exists('ob_end_clean')) @ob_end_clean();
            wp_send_json_error([
                'message' => __('Genesis V9 生成失败: ', 'linked3-ai') . $e->getMessage(),
                'file'    => WP_DEBUG ? $e->getFile() . ':' . $e->getLine() : '',
            ]);
        } finally {
            error_reporting($prev_er);
            if ($prev_de !== false) @ini_set('display_errors', $prev_de);
        }
    }

    /**
     * Validate V9 generate request: security + input sanitization.
     */
    private static function validate_v9_generate_request(): array {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }

        $script = wp_strip_all_tags(wp_unslash($_POST['script'] ?? ''));
        if (empty($script)) {
            wp_send_json_error(['message' => __('请输入剧本', 'linked3-ai')]);
        }

        $extraOptions = [];
        if (!empty($_POST['seed_id'])) $extraOptions['seed_id'] = sanitize_text_field($_POST['seed_id']);
        if (!empty($_POST['split_mode'])) $extraOptions['split_mode'] = sanitize_text_field($_POST['split_mode']);
        if (!empty($_POST['chapter_marker'])) $extraOptions['chapter_marker'] = sanitize_text_field($_POST['chapter_marker']);

        return [
            'script'            => $script,
            'style_id'          => sanitize_text_field($_POST['style'] ?? 'documentary_photo'),
            'platform'          => sanitize_text_field($_POST['platform'] ?? 'midjourney'),
            'panel_count_raw'   => sanitize_text_field($_POST['panel_count'] ?? 'auto'),
            'extra_options'     => $extraOptions,
        ];
    }

        public static function ajax_genesis_v9_stage1() : mixed { return GenesisV9Stages::ajax_genesis_v9_stage1(); }

        public static function ajax_genesis_v9_stage2() : mixed { return GenesisV9Stages::ajax_genesis_v9_stage2(); }
}

