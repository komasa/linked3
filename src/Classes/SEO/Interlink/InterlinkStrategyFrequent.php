<?php

declare(strict_types=1);
/**
 * Frequent interlink strategy — favours posts that already receive many
 * inbound interlinks (highest target_post_id count in linked3_interlink_map).
 *
 * Mirrors v2.9.6's `interlink_priority='frequent'` mode: posts with the
 * most existing inbound edges get prioritised, which compounds PageRank
 * toward pillar content.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Interlink
 */

namespace Linked3\Classes\SEO\Interlink;

if (!defined('ABSPATH')) {
    exit;
}

final class InterlinkStrategyFrequent implements InterlinkStrategy
{
    public function candidates($source_post_id, array $keywords, $limit, $max_per_target) : mixed {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_interlink_map';

        // Aggregate inbound edge counts per target, exclude self.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT target_post_id, SUM(count) AS inbound
             FROM {$table}
             WHERE target_post_id != %d
             GROUP BY target_post_id
             ORDER BY inbound DESC
             LIMIT %d",
            (int) $source_post_id,
            (int) ($limit * 3) // over-fetch so we can filter by keyword match
        ));

        $candidates = [];
        foreach ($rows as $r) {
            $post = get_post((int) $r->target_post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }
            $anchor = $this->pick_anchor($post, $keywords);
            if ($anchor === '') {
                continue;
            }
            $candidates[] = [
                'post_id' => (int) $post->ID,
                'title'   => $post->post_title,
                'url'     => (string) get_permalink($post->ID),
                'anchor'  => $anchor,
                'score'   => (float) $r->inbound,
            ];
            if (count($candidates) >= $limit) {
                break;
            }
        }
        return $candidates;
    }

    /**
     * Prefer a keyword that appears in the target post title; fall back to title.
     *
     * @param \WP_Post $post
     * @param string[] $keywords
     * @return string
     */
    private function pick_anchor(WP_Post $post, array $keywords) : mixed     {
        $title = (string) $post->post_title;
        if ($title === '') {
            return '';
        }
        foreach ($keywords as $kw) {
            if ($kw !== '' && mb_stripos($title, $kw) !== false) {
                return $kw;
            }
        }
        return $title;
    }
}
