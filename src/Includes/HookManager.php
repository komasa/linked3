<?php

declare(strict_types=1);
/**
 * Layer 2/3: Hook Manager.
 * Centralises handler instantiation + hook registration.
 * Handlers are created here (with `class_exists` guards so Pro classes
 * can be absent without fatal), then passed to Registrars.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class HookManager
{
    /**
     * @param string $version
     * @return void
     */
    public static function register_hooks(string $version)
    : void {
        // i18n
        $i18n = new I18n();
        add_action('init', [$i18n, 'load_textdomain'], 0);

        self::register_genesis_hooks();
        self::register_seed_hooks();
        self::register_dashboard_hooks();
        self::register_ecosystem_hooks();
        self::register_woocommerce_hooks();
        self::register_content_hooks();
        self::register_misc_hooks($version);
    }

    private static function register_genesis_hooks(): void {
        if (class_exists('\\Linked3\\Includes\\GenesisSeedCPT')) {
            add_action('init', ['\\Linked3\\Classes\\Genesis\\GenesisSeedCPT', 'register'], 5);
        }
        if (class_exists('\\Linked3\\Includes\\StoryPipeline')) {
            add_action('wp_ajax_linked3_import_script',    ['\\Linked3\\Classes\\Genesis\\StoryPipeline', 'ajax_import_script']);
            add_action('wp_ajax_linked3_parse_story',      ['\\Linked3\\Classes\\Genesis\\StoryPipeline', 'ajax_parse_story']);
            add_action('wp_ajax_linked3_detect_characters',['\\Linked3\\Classes\\Genesis\\StoryPipeline', 'ajax_detect_characters']);
        }
        if (class_exists('\\Linked3\\Includes\\SceneAxis')) {
            add_action('wp_ajax_linked3_get_scene_axes', ['\\Linked3\\Classes\\Genesis\\SceneAxis', 'ajax_get_axes']);
            add_action('wp_ajax_linked3_route_skeleton', ['\\Linked3\\Classes\\Genesis\\SceneAxis', 'ajax_route_skeleton']);
        }
    }

    private static function register_seed_hooks(): void {
        if (class_exists('\\Linked3\\Classes\\Genesis\\SeedAdminRender')) {
            add_action('admin_menu', ['\\Linked3\\Classes\\Genesis\\SeedAdminRender', 'register_menu'], 20);
            add_action('admin_post_linked3_seed_bulk', ['\\Linked3\\Classes\\Genesis\\SeedAdminRender', 'handle_bulk_post']);
        }
        if (class_exists('\\Linked3\\Classes\\Genesis\\SeedAdminAjax')) {
            add_action('wp_ajax_linked3_save_seed',           ['\\Linked3\\Classes\\Genesis\\SeedAdminAjax', 'ajax_save_seed']);
            add_action('wp_ajax_linked3_trash_all_seeds',     ['\\Linked3\\Classes\\Genesis\\SeedAdminAjax', 'ajax_trash_all']);
            add_action('wp_ajax_linked3_download_seed',       ['\\Linked3\\Classes\\Genesis\\SeedAdminAjax', 'ajax_download_seed']);
            add_action('wp_ajax_linked3_export_batch_seeds',  ['\\Linked3\\Classes\\Genesis\\SeedAdminAjax', 'ajax_export_batch']);
        }
    }

    private static function register_dashboard_hooks(): void {
        add_action('admin_init', ['Linked3\\Includes\\Activator', 'check_for_updates'], 10);
        if (class_exists('\\Linked3\\Classes\\Dashboard\\DashboardMenuRegistrar')) {
            add_action('admin_menu', ['\\Linked3\\Classes\\Dashboard\\DashboardMenuRegistrar', 'register'], 5);
        }
        if (class_exists('\\Linked3\\Classes\\Dashboard\\DashboardAjaxRegistrar')) {
            add_action('admin_init', ['\\Linked3\\Classes\\Dashboard\\DashboardAjaxRegistrar', 'register_all'], 5);
        }
    }

    private static function register_ecosystem_hooks(): void {
        if (class_exists('\\Linked3\\Classes\\Content\\EcosystemAjax')) {
            $eco = ['\\Linked3\\Classes\\Content\\EcosystemAjax', ''];
            add_action('wp_ajax_linked3_template_list',     [$eco[0], 'ajax_template_list']);
            add_action('wp_ajax_linked3_template_save',     [$eco[0], 'ajax_template_save']);
            add_action('wp_ajax_linked3_template_delete',   [$eco[0], 'ajax_template_delete']);
            add_action('wp_ajax_linked3_cloud_master_save', [$eco[0], 'ajax_cloud_master_save']);
            add_action('wp_ajax_linked3_cloud_fork',        [$eco[0], 'ajax_cloud_fork']);
            add_action('wp_ajax_linked3_cloud_preview',     [$eco[0], 'ajax_cloud_preview']);
            add_action('wp_ajax_linked3_charts_generate_v10', [$eco[0], 'ajax_charts_generate_v10']);
        }
    }

    private static function register_woocommerce_hooks(): void {
        if (class_exists('\\Linked3\\Classes\\WooCommerce\\WcTokenPackage')) {
            add_action('wp_ajax_linked3_wc_generate_desc',    ['\\Linked3\\Classes\\WooCommerce\\WcTokenPackage', 'ajax_generate_desc']);
            add_action('wp_ajax_linked3_wc_generate_image',   ['\\Linked3\\Classes\\WooCommerce\\WcTokenPackage', 'ajax_generate_image']);
            add_action('wp_ajax_linked3_wc_generate_reviews', ['\\Linked3\\Classes\\WooCommerce\\WcTokenPackage', 'ajax_generate_reviews']);
        }
    }

    private static function register_content_hooks(): void {
        if (class_exists('\\Linked3\\Classes\\AutoGPT\\AutoGPTCron')) {
            add_action('linked3_autogpt_cron', ['\\Linked3\\Classes\\AutoGPT\\AutoGPTCron', 'run']);
        }
        if (class_exists('\\Linked3\\Classes\\Distribute\\DistributeManager')) {
            add_action('linked3_distribute_retry', ['\\Linked3\\Classes\\Distribute\\DistributeManager', 'process_retry_queue']);
        }
    }

    private static function register_misc_hooks(string $version): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_head', [__CLASS__, 'print_meta_tags'], 5);
        add_filter('plugin_action_links_' . plugin_basename(LINKED3_FILE), [__CLASS__, 'add_action_links']);
        add_action('admin_notices', [__CLASS__, 'show_registrar_errors']);
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 10);
            add_action('admin_init', [__CLASS__, 'render_security_audit'], 20);
        }
        add_action('send_headers', [__CLASS__, 'send_security_headers'], 10);
    }

    /** @var string[] */
    private static $registrar_errors = [];

    /**
     * @return void
     */
    public static function show_registrar_errors()
    : void {
        echo '<div class="notice notice-error"><p><strong>Linked3:</strong> '
            . esc_html__('部分模块加载失败,其他菜单仍可用。', 'linked3')
            . '</p><ul>';
        foreach (self::$registrar_errors as $err) {
            echo '<li><code>' . esc_html($err) . '</code></li>';
        }
        // Also show Dependency_Loader load_errors (per-file require failures).
        if (class_exists('\\Linked3\\Includes\\DependencyLoader') && !empty(\Linked3\Includes\DependencyLoader::$load_errors)) {
            foreach (\Linked3\Includes\DependencyLoader::$load_errors as $err) {
                echo '<li><code>' . esc_html($err) . '</code></li>';
            }
        }
        echo '</ul></div>';
    }

    /**
     * Register the admin "Tools → Linked3 Security Audit" submenu.
     *
     * @return void
     */
    public static function register_admin_menu()
    : void {
                add_submenu_page('linked3-dashboard', '安全审计', '安全审计', 'manage_options', 'linked3-security-audit', [__CLASS__, 'render_security_audit']);
    }

    /**
     * @return void
     */
    public static function render_security_audit()
    : void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $auditor = new \Linked3\Classes\Security\AjaxAuditor();
        include LINKED3_DIR . 'admin/views/security/ajax-audit.php';
    }

    /**
     * Emit security response headers on admin + AJAX endpoints.
     *
     * Constitution §1 hardening: harden Linked3 admin pages and AJAX
     * responses with browser-level defences. Public-site headers are
     * left to the theme/cache layer to avoid surprising integrators.
     *
     * Filterable: `linked3/security_headers` (bool) — return false to disable.
     *
     * @return void
     */
    public static function send_security_headers()
    : void {
        if (headers_sent()) {
            return;
        }
        if (!apply_filters('linked3/security_headers', true)) {
            return;
        }
        // Only emit on admin pages + AJAX endpoints so we don't fight the
        // theme's own headers on the public site.
        $is_admin = is_admin();
        $is_ajax  = defined('DOING_AJAX') && DOING_AJAX;
        if (!$is_admin && !$is_ajax) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // X-XSS-Protection is deprecated in modern browsers but still
        // useful for legacy IE — keep for defence in depth.
        header('X-XSS-Protection: 1; mode=block');
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        // Disable the most-abused device APIs by default on admin screens.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Register custom cron intervals.
     *
     * @param array $schedules
     * @return array
     */
    public static function register_cron_schedules(array $schedules): array
    {
        $schedules['linked3_every_10min'] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => __('每 10 分钟(Linked3)', 'linked3'),
        ];
        $schedules['linked3_every_30min'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display'  => __('每 30 分钟(Linked3)', 'linked3'),
        ];
        return $schedules;
    }

    /**
     * Daily health check — probes for missing tables and self-heals.
     * Backup defence for sites that go days without an admin visit
     * (admin_init::check_for_updates is the primary path).
     *
     * @return void
     */
    public static function daily_health_check()
    : void {
        if (class_exists('Linked3\\Includes\\Activator')) {
            Activator::check_for_updates();
        }
    }

    /**
     * Daily SSE cache cleanup — delete expired rows.
     *
     * @return void
     */
    public static function cleanup_sse_cache()
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_sse_message_cache';
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s", $now)); // phpcs:ignore
    }

    /**
     * Daily log prune — delete files older than 30 days.
     *
     * @return void
     */
    public static function prune_logs()
    : void {
        if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
            \Linked3\Includes\Log\Logger::instance()->prune(30);
        }
    }

    /**
     * Daily push-log prune (v0.4.1) — delete linked3_push_logs rows
     * older than 30 days. Delegates to the SEO push repository so the
     * schema class remains the single owner of the table.
     *
     * @return void
     */
    public static function prune_push_logs()
    : void {
        if (class_exists('\\Linked3\\Classes\\SEO\\Push\\PushLogRepository')) {
            \Linked3\Classes\SEO\Push\PushLogRepository::prune_older_than(30);
        }
    }

    /**
     * v4.9.4: Daily billing events prune — delete rows older than 90 days.
     *
     * @return void
     */
    public static function prune_billing_events()
    : void {
        if (class_exists('\\Linked3\\Classes\\Billing\\BillingEventRepository')) {
            $repo = new \Linked3\Classes\Billing\BillingEventRepository();
            $repo->prune_older_than(90);
        }
    }

    /**
     * v5.2.4: 定时热词采集 + 长尾词生成。
     *
     * 自动采集 7 源热词 → AI 生成长尾词 → 追加到长尾词库 (option)。
     *
     * @return void
     */
    public static function kw_cron_run()
    : void {
        // 1. 采集热词
        $hot_words = [];
        if (class_exists('\\Linked3\\Classes\\SEO\\Keyword\\KeywordManager')) {
            $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
            $hot_words = $mgr->fetch_all_sources('', 30);
        }

        if (empty($hot_words)) {
            if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
                \Linked3\Includes\Log\Logger::instance()->warning('cron', '定时热词采集失败: 无结果');
            }
            return;
        }

        // 保存到热词库 (合并已有)
        $existing_hot = (array) get_option(LINKED3_OPTION_PREFIX . 'kw_hot_library', []);
        $merged_hot = array_values(array_unique(array_merge($existing_hot, $hot_words)));
        update_option(LINKED3_OPTION_PREFIX . 'kw_hot_library', array_slice($merged_hot, 0, 200));

        // 2. AI 生成长尾词
        $count = (int) get_option(LINKED3_OPTION_PREFIX . 'kw_cron_count', 30);
        $tail_words = [];
        if (class_exists('\\Linked3\\Classes\\SEO\\Keyword\\KeywordManager')) {
            $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
            $seed = implode("\n", array_slice($merged_hot, 0, 5));
            $tail_words = $mgr->generate_tail_keywords($seed, $count);
        }

        if (!empty($tail_words)) {
            // 追加到长尾词库 (合并去重)
            $existing_tail = (array) get_option(LINKED3_OPTION_PREFIX . 'kw_tail_library', []);
            $merged_tail = array_values(array_unique(array_merge($existing_tail, $tail_words)));
            update_option(LINKED3_OPTION_PREFIX . 'kw_tail_library', array_slice($merged_tail, 0, 500));
        }

        if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
            \Linked3\Includes\Log\Logger::instance()->info('cron', sprintf(
                '定时关键词任务完成: 采集 %d 热词, 生成 %d 长尾词',
                count($hot_words),
                count($tail_words)
            ));
        }
    }
}

