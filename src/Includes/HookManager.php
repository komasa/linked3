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
    static function register_hooks(string $version): void {
        // i18n — always on.
        $i18n = new I18n();
        add_action('init', [$i18n, 'load_textdomain'], 0);

        // v8.1.0: Seed DNA CPT 注册
        if (class_exists('\Linked3\Includes\GenesisSeedCPT')) {
            add_action('init', ['\Linked3\Classes\Genesis\GenesisSeedCPT', 'register'], 5);
        }

        // v8.2.0: Story Pipeline + Scene Axis AJAX hooks (M3 + M4)
        // 所有 handler 内部自带 check_ajax_referer + current_user_can('edit_posts').
        if (class_exists('\Linked3\Includes\StoryPipeline')) {
            add_action('wp_ajax_linked3_import_script',   ['\Linked3\Classes\Genesis\StoryPipeline', 'ajax_import_script']);
            add_action('wp_ajax_linked3_parse_story',     ['\Linked3\Classes\Genesis\StoryPipeline', 'ajax_parse_story']);
            add_action('wp_ajax_linked3_detect_characters',['\Linked3\Classes\Genesis\StoryPipeline', 'ajax_detect_characters']);
        }
        if (class_exists('\Linked3\Includes\SceneAxis')) {
            add_action('wp_ajax_linked3_get_scene_axes',  ['\Linked3\Classes\Genesis\SceneAxis', 'ajax_get_axes']);
            add_action('wp_ajax_linked3_route_skeleton',  ['\Linked3\Classes\Genesis\SceneAxis', 'ajax_route_skeleton']);
        }

        // v8.1.0 M1.2+M1.3: Seed DNA Admin UI + Export 层
        // G4.1 split: SeedAdmin delegates to SeedAdminRender/SeedAdminAjax.
        // Call the split classes directly.
        if (class_exists('\Linked3\Classes\Genesis\SeedAdminRender')) {
            add_action('admin_menu', ['\Linked3\Classes\Genesis\SeedAdminRender', 'register_menu'], 20);
            add_action('admin_post_linked3_seed_bulk', ['\Linked3\Classes\Genesis\SeedAdminRender', 'handle_bulk_post']);
        }
        if (class_exists('\Linked3\Classes\Genesis\SeedAdminAjax')) {
            add_action('wp_ajax_linked3_save_seed', ['\Linked3\Classes\Genesis\SeedAdminAjax', 'ajax_save_seed']);
            add_action('wp_ajax_linked3_trash_all_seeds', ['\Linked3\Classes\Genesis\SeedAdminAjax', 'ajax_trash_all']);
            add_action('wp_ajax_linked3_download_seed', ['\Linked3\Classes\Genesis\SeedAdminAjax', 'ajax_download_seed']);
            add_action('wp_ajax_linked3_export_batch_seeds', ['\Linked3\Classes\Genesis\SeedAdminAjax', 'ajax_export_batch']);
        }

        // Activation check — DB version alignment, self-heal.
        add_action('admin_init', ['Linked3\\Includes\\Activator', 'check_for_updates'], 10);

        // Tools menu — AJAX security audit (v0.0.8).
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);

        // Custom cron schedules (v0.1.10).
        add_filter('cron_schedules', [__CLASS__, 'register_cron_schedules']);

        // Cron handlers (v0.1.10).
        add_action('linked3_daily_health_check', [__CLASS__, 'daily_health_check']);
        add_action('linked3_token_reset', ['\\Linked3\\Classes\\Core\\TokenManager', 'daily_reset']);
        add_action('linked3_sse_cache_cleanup', [__CLASS__, 'cleanup_sse_cache']);
        add_action('linked3_log_prune', [__CLASS__, 'prune_logs']);
        // SEO push-log prune (v0.4.1).
        add_action('linked3_push_log_prune', [__CLASS__, 'prune_push_logs']);
        // v4.9.4: billing events prune (90-day retention).
        add_action('linked3_billing_events_prune', [__CLASS__, 'prune_billing_events']);
        // v5.2.4: 定时热词采集 + 长尾词生成
        add_action('linked3_kw_cron_run', [__CLASS__, 'kw_cron_run']);
        // License + billing crons (v0.2.1-v0.2.10).
        add_action('linked3_license_heartbeat', ['\\Linked3\\Classes\\License\\LicenseService', 'daily_heartbeat']);
        add_action('linked3_subscription_check', ['\\Linked3\\Classes\\Billing\\SubscriptionManagerV2', 'daily_check']);
        add_action('linked3_business_optimize', ['\\Linked3\\Classes\\Billing\\BusinessOptimizer', 'daily_analyze']);
        // AutoGPT cron (every 10 min).
        add_action('linked3_autogpt_run', ['\\Linked3\\Classes\\AutoGPT\\Cron\\AutoGPTCron', 'run']);

        // Security response headers (v0.1.0 hardening — C+O constitution §1).
        add_action('send_headers', [__CLASS__, 'send_security_headers']);

        /**
         * Allow modules to register their own hooks via a Registrar class.
         * Filter returns: [ ['handler' => $instance, 'registrar' => 'Class::method'], ... ]
         */
        $registrations = (array) apply_filters('linked3/hook_registrars', []);
        foreach ($registrations as $entry) {
            if (empty($entry['handler']) || empty($entry['registrar'])) {
                continue;
            }
            if (is_string($entry['registrar']) && strpos($entry['registrar'], '::') !== false) {
                call_user_func($entry['registrar'], $entry['handler']);
            } elseif (is_callable($entry['registrar'])) {
                call_user_func($entry['registrar'], $entry['handler']);
            }
        }

        /**
         * Generic action so any module can hook without polluting this class.
         */
        do_action('linked3/register_hooks', $version);

        // v19.2: 小红书模块注册
        if (class_exists('Linked3\\Classes\\XHS\\XHSHooksRegistrar')) {
            \Linked3\Classes\XHS\XHSHooksRegistrar::register();
        }

        // ============================================================
        // v16.0.0: V18子系统统一注册 (Facade模式)
        // 来源: v16.0.0全量重铸方案J — V18模块与原linked3深度整合
        // ============================================================
        if (class_exists('\Linked3\Includes\V18')) {
            // 注册V18 AJAX/REST/短代码/Widget/Admin/DB
            // ── FIX v16.0.1: Guard method_exists — register_all() was missing
            // from the Facade, causing "method does not exist" fatal on init.
            $v18_method = method_exists('\Linked3\Includes\V18', 'register_all') ? 'register_all' : 'register';
            add_action('init', ['\Linked3\Classes\OS\V18', $v18_method], 5);
        }

        // v8.3.0 M5: 质量闭环 AJAX (PQS / 批量一致性 / 劣化诊断)
        if (class_exists('\Linked3\Includes\QualityLoop')) {
            add_action('wp_ajax_linked3_pqs_check', ['\Linked3\Classes\Genesis\QualityLoop', 'ajax_pqs_check']);
            add_action('wp_ajax_linked3_batch_check', ['\Linked3\Classes\Genesis\QualityLoop', 'ajax_batch_check']);
            add_action('wp_ajax_linked3_diagnose', ['\Linked3\Classes\Genesis\QualityLoop', 'ajax_diagnose']);
        }

        // v8.3.0 M6: 多平台适配 AJAX (切换平台预览)
        if (class_exists('\Linked3\Includes\PlatformAdapter')) {
            add_action('wp_ajax_linked3_switch_platform', ['\Linked3\Classes\AI\Pipeline\PlatformAdapter', 'ajax_switch_platform']);
        }

        // Hard-registered module hooks. Each wrapped in try/catch so a single
        // module fatal cannot take down the entire admin menu.
        $registrars = [
            'ContentWriter' => '\\Linked3\\Classes\\ContentWriter\\ContentWriterHooksRegistrar',
            'SEO'           => '\\Linked3\\Classes\\SEO\\SEOHooksRegistrar',
            'Publish'       => '\\Linked3\\Classes\\Publish\\PublishCollectHooksRegistrar',
            'Chat'          => '\\Linked3\\Classes\\Chat\\ChatHooksRegistrar',
            'AutoGPT'       => '\\Linked3\\Classes\\AutoGPT\\AutoGPTHooksRegistrar',
            'WC/Forms/Speech' => '\\Linked3\\Classes\\WooCommerce\\WcFormsSpeechHooksRegistrar',
            'Dashboard'       => '\\Linked3\\Classes\\Dashboard\\DashboardHooksRegistrar',
            'Distribute'      => '\\Linked3\\Classes\\Distribute\\DistributeHooksRegistrar',
            'Metabox'         => '\\Linked3\\Classes\\Admin\\PostMetabox',
            'UpdateChecker'   => '\\Linked3\\Classes\\Admin\\UpdateChecker',
        ];
        foreach ($registrars as $label => $class) {
            if (!class_exists($class)) {
                continue;
            }
            try {
                call_user_func([$class, 'register']);
            } catch (\Throwable $e) {
                // Log + show admin notice so the site owner knows which module
                // failed without losing all other menus.
                if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
                    \Linked3\Includes\Log\Logger::instance()->critical('general', "Module {$label} register() failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                }
                self::$registrar_errors[] = sprintf('[%s] %s', $label, $e->getMessage());
            }
        }

        // v4.7.1: Belt-and-suspenders — ensure Addon_Manager::init_all() is
        // scheduled even if the Dashboard registrar failed to load. The
        // Dashboard facade also schedules this, but add_action is idempotent
        // so a double registration is harmless. This fixes P0-2 from the
        // v4.6.1 audit (addons were dead code because init_all() was never
        // guaranteed to fire).
        if (class_exists('\\Linked3\\Classes\\Addons\\AddonManager')) {
            try {
                $addon_mgr = \Linked3\Classes\Addons\AddonManager::instance();
                if (class_exists('\\Linked3\\Classes\\Addons\\IPAnonymizationAddon')) {
                    $addon_mgr->register(new \Linked3\Classes\Addons\IPAnonymizationAddon());
                }
                if (class_exists('\\Linked3\\Classes\\Addons\\ConsentComplianceAddon')) {
                    $addon_mgr->register(new \Linked3\Classes\Addons\ConsentComplianceAddon());
                }
                add_action('init', [$addon_mgr, 'init_all'], 20);
            } catch (\Throwable $e) {
                if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
                    \Linked3\Includes\Log\Logger::instance()->error('general', 'Addon_Manager init failed: ' . $e->getMessage());
                }
            }
        }
        if (!empty(self::$registrar_errors) || (class_exists('\\Linked3\\Includes\\DependencyLoader') && !empty(\Linked3\Includes\DependencyLoader::$load_errors))) {
            add_action('admin_notices', [__CLASS__, 'show_registrar_errors']);
        }
    }

    /** @var string[] */
    private static $registrar_errors = [];

    /**
     * @return void
     */
    static function show_registrar_errors(): void {
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
    static function register_admin_menu(): void {
                add_submenu_page('linked3-dashboard', '安全审计', '安全审计', 'manage_options', 'linked3-security-audit', [__CLASS__, 'render_security_audit']);
    }

    /**
     * @return void
     */
    static function render_security_audit(): void {
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
    static function send_security_headers(): void {
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
    static function daily_health_check(): void {
        if (class_exists('Linked3\\Includes\\Activator')) {
            Activator::check_for_updates();
        }
    }

    /**
     * Daily SSE cache cleanup — delete expired rows.
     *
     * @return void
     */
    static function cleanup_sse_cache(): void {
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
    static function prune_logs(): void {
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
    static function prune_push_logs(): void {
        if (class_exists('\\Linked3\\Classes\\SEO\\Push\\PushLogRepository')) {
            \Linked3\Classes\SEO\Push\PushLogRepository::prune_older_than(30);
        }
    }

    /**
     * v4.9.4: Daily billing events prune — delete rows older than 90 days.
     *
     * @return void
     */
    static function prune_billing_events(): void {
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
    static function kw_cron_run(): void {
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
