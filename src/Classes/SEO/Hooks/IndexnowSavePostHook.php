<?php

declare(strict_types=1);
/**
 * Indexnow instant-push hook (save_post).
 *
 * v0.4.8: whenever a post is published or updated, fire an Indexnow push
 * to Bing/Yandex/Naver immediately (sub-minute crawl latency vs ~daily
 * batch). Hardening over v2.9.6's "always push on save":
 *   - min_gap dedup per (URL, engine): skip if linked3_push_logs already
 *     shows a successful push for this URL on ANY of the configured
 *     Indexnow-family engines in the last `min_gap` seconds (default 10
 *     minutes per the v0.5.0 anti-ban spec; previously 24h which was
 *     over-restrictive).
 *   - Skip drafts / pending / trash / auto-drafts / revisions.
 *   - Skip unconfigured engines silently (no log spam).
 *   - run on priority 100 so other save_post handlers (Yoast sitemap
 *     ping, etc.) have already fired.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Hooks
 */

namespace Linked3\Classes\SEO\Hooks;

use Linked3\Classes\SEO\Push\PushManager;
use Linked3\Classes\SEO\Push\PushLogRepository;
use Linked3\Classes\SEO\SEOConfig;



if (!defined('ABSPATH')) {
    exit;
}
final class IndexnowSavePostHook
{
    const HOOK_PRIORITY = 100;

    /**
     * @return void
     */
    public static function register(): void {
        add_action('save_post', [__CLASS__, 'on_save_post'], self::HOOK_PRIORITY, 3);
    }

    /**
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     * @return void
     */
    public static function on_save_post(int $post_id, WP_Post $post, bool $update): void {
        // Skip autosaves + revisions + non-public types.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        $excluded = (array) SEOConfig::get('interlink.excluded_post_types', []);
        if (in_array($post->post_type, $excluded, true)) {
            return;
        }
        $url = (string) get_permalink($post_id);
        if ($url === '') {
            return;
        }

        // Dedup: skip if ANY of the configured Indexnow-family engines
        // already received a successful push for this URL within min_gap.
        // The loop is correct because push_to_engines() silently skips
        // null (unregistered) slugs, so changing the engines list never
        // breaks the dedup.
        $min_gap = (int) SEOConfig::get('push.indexnow.min_gap', 10 * MINUTE_IN_SECONDS);
        $engines = (array) SEOConfig::get('push.indexnow.engines', ['indexnow']);
        foreach ($engines as $slug) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '') {
                continue;
            }
            if (PushLogRepository::count_recent_success($url, $slug, $min_gap) > 0) {
                return; // recent push on this engine → anti-ban backoff
            }
        }

        // Don't block the save flow: schedule an async one-shot if possible.
        // (WordPress cron needs an event; for simplicity here we push inline
        // since Safe_Remote has a 15s timeout and engines typically respond
        // in <2s. If the push fails, it just logs to push_logs.)
        $manager = PushManager::instance();
        foreach ($engines as $slug) {
            $manager->push_url($url, sanitize_key((string) $slug));
        }
    }
}
