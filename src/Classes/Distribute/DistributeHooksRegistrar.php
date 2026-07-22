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

    public static function register(): void {
        // Auto-distribute on publish (Pro+, configurable).
        add_action('transition_post_status', [__CLASS__, 'on_publish'], 20, 3);
        // AJAX: test platform + save config + manual distribute.
        add_action('wp_ajax_linked3_distribute_test', [__CLASS__, 'ajax_test']);
        add_action('wp_ajax_linked3_distribute_save', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_linked3_distribute_now', [__CLASS__, 'ajax_now']);
        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    public static function on_publish($new_status, $old_status, $post): void {
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
    public static function on_shutdown_distribute(): void {
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
    private static function guard(): void {
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

    public static function ajax_test(): void {
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

    public static function ajax_save(): void {
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

    public static function ajax_now(): void {
        self::guard();
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id) wp_send_json_error(['message' => __('需要文章 ID。', 'linked3')], 400);
        $results = DistributeManager::instance()->distribute_post($post_id);
        wp_send_json_success(['results' => $results]);
    }

    private static function parse_config($platform) : mixed {
        $cfg = ['enabled' => !empty($_POST['enabled'])];
        $method = 'parse_' . $platform . '_config';
        if (method_exists(__CLASS__, $method)) {
            $cfg = array_merge($cfg, self::$method());
        }
        return $cfg;
    }

    private static function parse_twitter_config(): array {
        return ['bearer_token' => sanitize_text_field($_POST['bearer_token'] ?? '')];
    }

    private static function parse_telegram_config(): array {
        return [
            'bot_token' => sanitize_text_field($_POST['bot_token'] ?? ''),
            'chat_id'   => sanitize_text_field($_POST['chat_id'] ?? ''),
        ];
    }

    private static function parse_discord_config(): array {
        return [
            'webhook_url' => esc_url_raw($_POST['webhook_url'] ?? ''),
            'bot_name'    => sanitize_text_field($_POST['bot_name'] ?? 'Linked3'),
        ];
    }

    private static function parse_wechat_config(): array {
        return [
            'app_id'                => sanitize_text_field($_POST['app_id'] ?? ''),
            'app_secret'            => sanitize_text_field($_POST['app_secret'] ?? ''),
            'default_thumb_media_id'=> sanitize_text_field($_POST['default_thumb_media_id'] ?? ''),
        ];
    }

    private static function parse_xiaohongshu_config(): array {
        return [
            'api_url'      => esc_url_raw($_POST['api_url'] ?? ''),
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
        ];
    }

    private static function parse_weibo_config(): array {
        return self::parse_token_only_config();
    }

    private static function parse_zhihu_config(): array {
        return [
            'api_url'      => esc_url_raw($_POST['api_url'] ?? ''),
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'column_id'    => sanitize_text_field($_POST['column_id'] ?? ''),
        ];
    }

    private static function parse_smzdm_config(): array {
        return [
            'api_url'      => esc_url_raw($_POST['api_url'] ?? ''),
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
        ];
    }

    private static function parse_juejin_config(): array {
        return [
            'access_token'=> sanitize_text_field($_POST['access_token'] ?? ''),
            'category_id' => sanitize_text_field($_POST['category_id'] ?? ''),
        ];
    }

    private static function parse_csdn_config(): array {
        return self::parse_token_only_config();
    }

    private static function parse_blogger_config(): array {
        return [
            'access_token' => sanitize_text_field($_POST['access_token'] ?? ''),
            'blog_id'      => sanitize_text_field($_POST['blog_id'] ?? ''),
        ];
    }

    private static function parse_medium_config(): array {
        return self::parse_token_only_config();
    }

    private static function parse_reddit_config(): array {
        return [
            'access_token'=> sanitize_text_field($_POST['access_token'] ?? ''),
            'subreddit'   => sanitize_text_field($_POST['subreddit'] ?? ''),
        ];
    }

    /**
     * 共用: 仅 access_token 字段的平台 (weibo/csdn/medium).
     */
    private static function parse_token_only_config(): array {
        return ['access_token' => sanitize_text_field($_POST['access_token'] ?? '')];
    }

    public static function register_admin_menu(): void {
        add_submenu_page('linked3-dashboard', '社交分发', '社交分发', 'manage_options', 'linked3-distribute', [__CLASS__, 'render_admin_page']);
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) return;
        $configs = get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
        $auto = get_option(LINKED3_OPTION_PREFIX . 'distribute_auto', []);
        include LINKED3_DIR . 'admin/views/distribute/dashboard.php';
    }
}
