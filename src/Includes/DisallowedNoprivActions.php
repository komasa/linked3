<?php

declare(strict_types=1);
/**
 * Linked3 AJAX endpoints registry for the v0.0.x foundation phase.
 *
 * Design intent (fixes linked v2.9.6's 22+ nopriv AJAX epidemic):
 *   - Every privileged endpoint uses wp_ajax_* (NOT nopriv) + TraitCheckAdminPermissions.
 *   - The only nopriv endpoints permitted are the floating chat widget's
 *     public entry, guarded by TraitCheckFrontendPermissions::verify_public()
 *     (nonce + IP rate-limit, no privileged work).
 *   - This file is the single place where v0.0.x endpoints live. Modules
 *     starting in v0.1.1 register their own via `linked3/hook_registrars`.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disallow-list for known-bad nopriv actions inherited from linked v2.9.6
 * during any future migration import. If a module attempts to register any
 * of these as nopriv, an admin notice fires.
 *
 * These are the exact actions that were exploitable in v2.9.6:
 *   - download_image           (anyone → upload to media library)
 *   - truncate_tail_keywords   (anyone → wipe keyword table)
 *   - generate_articles_async  (anyone → drain AI API quota)
 *   - wc_ai_save_review        (anyone → post fake reviews)
 *   - proxy                    (anyone → SSRF proxy)
 *   - auto_seo_answer_question (public AI chat without quota)
 */
final class DisallowedNoprivActions
{
    // NOTE: cannot use `const LIST` — `list` is a PHP reserved keyword
    // (language construct for array destructuring) and case-insensitive,
    // so even uppercase `LIST` is a parse error in PHP 7.4+.
    // Renamed to BLOCKED_ACTIONS (v1.0.0 FINAL-AUDIT fix).
    const BLOCKED_ACTIONS = [
        'download_image',
        'truncate_tail_keywords',
        'generate_articles_async',
        'generate_articles',
        'wc_ai_save_review',
        'proxy',
        'auto_seo_answer_question',
        'fetch_images_from_station',
        'scrape_article',
        'generate_tail_keywords',
        'push_batch',
        'save_hotwords',
        'test_database_connection',
        'publish_to_remote_database',
        'publish_to_remote_wordpress',
        'publish_to_custom_api',
        'rewrite_article',
        'bulk_rewrite_articles',
        'save_rewritten_article',
        'load_more_posts',
        'save_ai_content',
        'async_save_product_content',
    ];

    /**
     * Register the watchdog hook.
     *
     * @return void
     */
    public static function register(): void {
        add_action('admin_notices', [__CLASS__, 'warn_if_registered']);
    }

    /**
     * @return void
     */
    public static function warn_if_registered(): void {
        global $wp_filter;
        if (empty($wp_filter)) {
            return;
        }
        foreach (self::BLOCKED_ACTIONS as $action) {
            $hook = 'wp_ajax_nopriv_' . $action;
            if (isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks)) {
                echo '<div class="notice notice-error"><p>'
                    . esc_html(sprintf(
                        /* translators: %s: action name. */
                        __('Security: wp_ajax_nopriv_%s is registered but is on the Linked3 disallow-list (inherited from linked v2.9.6 vulnerability). Remove or convert to wp_ajax_%s immediately.', 'linked3'),
                        $action,
                        $action
                    ))
                    . '</p></div>';
            }
        }
    }
}

// Register the watchdog as soon as this file loads.
DisallowedNoprivActions::register();
