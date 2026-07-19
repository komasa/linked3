<?php

declare(strict_types=1);
namespace Linked3\Classes\Distribute;
if (!defined('ABSPATH')) exit;

/**
 * Distribute hooks registrar.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Distribute
 * @since      27.1.0
 */

final class DistributeHooksRegistrar
{
    /** @var int[] 待分发的文章 ID 队列 (避免闭包 use) */
    private static array $pending_distribute_ids = [];

    public static function register()
    : void {
        // Auto-distribute on publish (Pro+, configurable).
        add_action('transition_post_status', [__CLASS__, 'on_publish'], 20, 3);
        // AJAX: test platform + save config + manual distribute.
        add_action('wp_ajax_linked3_distribute_test', [__CLASS__, 'ajax_test']);
        add_action('wp_ajax_linked3_distribute_save', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_linked3_distribute_now', [__CLASS__, 'ajax_now']);
        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    public static function on_publish($new_status, $old_status, $post)
    : void {
        if ($new_status !== 'publish' || $old_status === 'publish') return;
        $auto = get_option(LINKED3_OPTION_PREFIX . 'distribute_auto', []);
        if (!is_array($auto) || empty($auto[$post->post_type] ?? false)) return;
        // Defer to shutdown to avoid blocking the save.
        self::$pending_distribute_ids[] = (int) $post->ID;
        add_action('shutdown', [__CLASS__, 'on_shutdown_distribute']);
    }

    /**
     * shutdown 回调: 批量执行延迟分发 (替代闭包 use)。
     */
    public static function on_shutdown_distribute()
    : void {
        $ids = self::$pending_distribute_ids;
        self::$pending_distribute_ids = [];
        foreach ($ids as $post_id) {
            DistributeManager::instance()->distribute_post($post_id);
        }
    }

    /**
     * Inline nonce+capability check (no trait — avoids $this in static context).
     *
     * @return void
     */
    private static function guard()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限。', 'linked3')], 403);
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'linked3_distribute')) {
            wp_send_json_error(['message' => __('安全校验失败。', 'linked3')], 403);
        }
        // Global rate limiter.
        if (class_exists('\\Linked3\\Classes\\Security\\RateLimiter')) {
            \Linked3\Classes\Security\RateLimiter::gate('linked3_distribute');
        }
    }

    public static function ajax_test()
    : void {
        self::guard();
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $cfg = self::parse_config($platform);
        // Save config first so test uses latest.
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        $configs[$platform] = $cfg;
        update_option(LINKED3_OPTION_PREFIX . 'distribute_configs', $configs);
        $r = DistributeManager::instance()->test_platform($platform);
        $r['ok'] ? wp_send_json_success($r) : wp_send_json_error($r, 400);
    }

    public static function ajax_save()
    : void {
        self::guard();
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $cfg = self::parse_config($platform);
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        $configs[$platform] = $cfg;
        update_option(LINKED3_OPTION_PREFIX . 'distribute_configs', $configs);
        // Save auto-distribute per post type.
        if (isset($_POST['auto_types'])) {
            $auto = get_option(LINKED3_OPTION_PREFIX . 'distribute_auto', []);
            foreach (['post', 'page', 'product'] as $pt) {
                $auto[$pt] = in_array($pt, (array) $_POST['auto_types'], true);
            }
            update_option(LINKED3_OPTION_PREFIX . 'distribute_auto', $auto);
        }
        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_now()
    : void {
        self::guard();
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error(['message' => __('需要文章 ID。', 'linked3')], 400);
        $results = DistributeManager::instance()->distribute_post($post_id);
        wp_send_json_success(['results' => $results]);
    }

    private static function parse_config($platform) : mixed {
        $cfg = ['enabled' => !empty($_POST['enabled'])];
        switch ($platform) {
            case 'twitter':
                $cfg['bearer_token'] = sanitize_text_field($_POST['bearer_token'] ?? '');
                break;
            case 'telegram':
                $cfg['bot_token'] = sanitize_text_field($_POST['bot_token'] ?? '');
                $cfg['chat_id'] = sanitize_text_field($_POST['chat_id'] ?? '');
                break;
            case 'discord':
                $cfg['webhook_url'] = esc_url_raw($_POST['webhook_url'] ?? '');
                $cfg['bot_name'] = sanitize_text_field($_POST['bot_name'] ?? 'Linked3');
                break;
            case 'wechat':
                $cfg['app_id'] = sanitize_text_field($_POST['app_id'] ?? '');
                $cfg['app_secret'] = sanitize_text_field($_POST['app_secret'] ?? '');
                $cfg['default_thumb_media_id'] = sanitize_text_field($_POST['default_thumb_media_id'] ?? '');
                break;
            case 'xiaohongshu':
                $cfg['api_url'] = esc_url_raw($_POST['api_url'] ?? '');
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                break;
            case 'weibo':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                break;
            // v3.2.0: 恢复知乎/SMZDM (MCP 中转模式)
            case 'zhihu':
                $cfg['api_url'] = esc_url_raw($_POST['api_url'] ?? '');
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                $cfg['column_id'] = sanitize_text_field($_POST['column_id'] ?? '');
                break;
            case 'smzdm':
                $cfg['api_url'] = esc_url_raw($_POST['api_url'] ?? '');
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                break;
            case 'juejin':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                $cfg['category_id'] = sanitize_text_field($_POST['category_id'] ?? '');
                break;
            case 'csdn':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                break;
            case 'blogger':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                $cfg['blog_id'] = sanitize_text_field($_POST['blog_id'] ?? '');
                break;
            case 'medium':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                break;
            case 'reddit':
                $cfg['access_token'] = sanitize_text_field($_POST['access_token'] ?? '');
                $cfg['subreddit'] = sanitize_text_field($_POST['subreddit'] ?? '');
                break;
        }
        return $cfg;
    }

    public static function register_admin_menu()
    : void {
        add_submenu_page('linked3-dashboard', '社交分发', '社交分发', 'manage_options', 'linked3-distribute', [__CLASS__, 'render_admin_page']);
    }

    public static function render_admin_page()
    : void {
        if (!current_user_can('manage_options')) return;
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        $auto = get_option(LINKED3_OPTION_PREFIX . 'distribute_auto', []);
        include LINKED3_DIR . 'admin/views/distribute/dashboard.php';
    }
}
