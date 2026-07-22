<?php

declare(strict_types=1);
/**
 * SEO hooks registrar — binds save_post, admin menu, AJAX actions, and
 * the wp_head schema-output hook.
 *
 * Mirrors ContentWriterHooksRegistrar:
 *   - register() registers everything statically; safe to call multiple times.
 *   - Admin menu renders via LINKED3_DIR/admin/views/seo/*.
 *
 * @package Linked3
 * @subpackage Classes\SEO
 */

namespace Linked3\Classes\SEO;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOHooksRegistrar
{
    /**
     * @return void
     */
    public static function register(): void {
        // Register AJAX actions (all admin-only, nonce+cap gated via base class).
        $actions = [
            'linked3_push_retry' => Ajax\Actions\PushRetryAction::class,
            'linked3_push_now'   => Ajax\Actions\PushNowAction::class,
            'linked3_seo_score'  => Ajax\Actions\SEOScoreAction::class,
        ];
        foreach ($actions as $action => $class) {
            if (!class_exists($class)) {
                continue;
            }
            add_action('wp_ajax_' . $action, [new $class(), 'dispatch']);
        }

        // Admin menu + metabox + settings.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('add_meta_boxes', [__CLASS__, 'register_metaboxes']);

        // JSON-LD output on public pages.
        $output_hook = (string) SEOConfig::get('schema.output_hook', 'wp_head');
        $output_prio = (int) SEOConfig::get('schema.output_priority', 10);
        add_action($output_hook, [__CLASS__, 'output_schema_markup'], $output_prio);

        // External link processor + auto-interlink filter on the_content.
        // Runs at priority 20 so other content filters (do_blocks, wptexturize,
        // shortcode rendering) have already completed.
        add_filter('the_content', [__CLASS__, 'filter_the_content'], 20);

        // v0.4.8 — Indexnow instant push on save_post.
        if (class_exists(__NAMESPACE__ . '\\Hooks\\IndexnowSavePostHook')) {
            Hooks\IndexnowSavePostHook::register();
        }

        // v0.4.9 — scorecard metabox assets (only on post-edit screens).
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_metabox_assets']);

        // v4.7.0 — GEO Enhancer (AI search engine optimization).
        // Generates /llms.txt + injects AI-friendly meta tags + boosts FAQ schema.
        if (class_exists(__NAMESPACE__ . '\\GEOEnhancer')) {
            GEOEnhancer::init();
        }
    }

    /**
     * @return void
     */
    public static function register_admin_menu(): void {
        add_submenu_page('linked3-dashboard', 'SEO 概览', 'SEO 优化', 'edit_posts', 'linked3-seo', [__CLASS__, 'render_dashboard']);
        add_submenu_page('linked3-dashboard', '推送日志', 'SEO › 推送日志', 'edit_posts', 'linked3-seo-push-logs', [__CLASS__, 'render_push_logs']);
        add_submenu_page('linked3-dashboard', 'SEO 设置', 'SEO › 设置', 'manage_options', 'linked3-seo-settings', [__CLASS__, 'render_settings']);
    }

    /**
     * @return void
     */
    public static function register_metaboxes(): void {
        $excluded = (array) SEOConfig::get('interlink.excluded_post_types', []);
        $screen = get_current_screen();
        if ($screen && in_array($screen->post_type, $excluded, true)) {
            return;
        }
        $types = get_post_types(['public' => true], 'names');
        foreach ($types as $type) {
            if (in_array($type, $excluded, true)) {
                continue;
            }
            add_meta_box(
                'linked3_seo_scorecard',
                __('SEO 评分', 'linked3'),
                [__CLASS__, 'render_scorecard_metabox'],
                $type,
                'side',
                'default'
            );
        }
    }

    /**
     * @return void
     */
    public static function render_dashboard(): void {
        if (!current_user_can('edit_posts')) {
            return;
        }
        include LINKED3_DIR . 'admin/views/seo/dashboard.php';
    }

    /**
     * @return void
     */
    public static function render_push_logs(): void {
        if (!current_user_can('edit_posts')) {
            return;
        }
        include LINKED3_DIR . 'admin/views/seo/push-logs.php';
    }

    /**
     * @return void
     */
    public static function render_settings(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        include LINKED3_DIR . 'admin/views/seo/settings.php';
    }

    /**
     * @param \WP_Post $post
     * @return void
     */
    public static function render_scorecard_metabox(WP_Post $post): void {
        $nonce = wp_create_nonce('linked3_seo');
        $post_id = (int) $post->ID;
        include LINKED3_DIR . 'admin/views/seo/scorecard-metabox.php';
    }

    /**
     * Output JSON-LD schema markup on the public site.
     *
     * @return void
     */
    public static function output_schema_markup(): void {
        if (is_admin()) {
            return;
        }
        if (!class_exists(__NAMESPACE__ . '\\Schema\\SchemaMarkup')) {
            return;
        }
        // Don't double-emit if an SEO plugin adapter has taken over.
        $adapter = Adapter\SEOAdapterDetector::resolve();
        if ($adapter && $adapter->handles_schema()) {
            return;
        }
        $markup = Schema\SchemaMarkup::instance();
        $json = $markup->for_current_request();
        if ($json === '') {
            return;
        }
        echo '<script type="application/ld+json" class="linked3-schema-markup">' . "\n"
            . $json . "\n"
            . '</script>' . "\n";
    }

    /**
     * Filter the_content — apply external link processing + auto-interlinking.
     *
     * @param string $content
     * @return string
     */
    public static function filter_the_content(string $content) : mixed {
        if (is_admin() || is_feed()) {
            return $content;
        }
        // v3.1.0: 读 seo_enhance 配置
        $seo_enhance = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'seo_enhance', []), [
            'interlink_enabled' => 1,
            'interlink_strategy' => 'popular',
            'interlink_max_per_post' => 5,
            'external_link_nofollow' => 1,
            'external_link_target_blank' => 1,
            'external_link_whitelist' => '',
        ]);

        // 外链处理
        if (!empty($seo_enhance['external_link_nofollow']) || !empty($seo_enhance['external_link_target_blank'])) {
            if (class_exists(__NAMESPACE__ . '\\Links\\ExternalLinkProcessor')) {
                $whitelist = array_filter(array_map('trim', explode("\n", $seo_enhance['external_link_whitelist'])));
                $content = Links\ExternalLinkProcessor::process($content, [
                    'nofollow' => !empty($seo_enhance['external_link_nofollow']),
                    'target_blank' => !empty($seo_enhance['external_link_target_blank']),
                    'whitelist' => $whitelist,
                ]);
            }
        }
        // 智能内链
        if (!empty($seo_enhance['interlink_enabled']) && class_exists(__NAMESPACE__ . '\\Interlink\\InterlinkBuilder') && is_singular()) {
            $post_id = get_the_ID();
            if ($post_id) {
                $builder = new Interlink\InterlinkBuilder();
                // 用 seo_enhance 配置覆盖 SEO_Config 默认值
                if (method_exists($builder, 'set_strategy')) {
                    $builder->set_strategy($seo_enhance['interlink_strategy']);
                }
                if (method_exists($builder, 'set_max_links')) {
                    $builder->set_max_links((int) $seo_enhance['interlink_max_per_post']);
                }
                $content = $builder->inject($content, $post_id);
            }
        }
        return $content;
    }

    /**
     * Enqueue metabox JS on post-edit screens.
     *
     * @param string $hook
     * @return void
     */
    public static function enqueue_metabox_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_script(
            'linked3-seo-scorecard',
            LINKED3_URL . 'admin/js/seo-scorecard.js',
            [],
            LINKED3_VERSION,
            true
        );
    }
}
