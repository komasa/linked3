<?php

declare(strict_types=1);
/**
 * Recent interlink strategy — favours freshly-published posts.
 *
 * Mirrors v2.9.6's `interlink_priority='recent'` mode. Useful for news
 * sites that want internal links to point at the latest coverage of a topic.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Interlink
 */

namespace Linked3\Classes\SEO\Interlink;

if (!defined('ABSPATH')) {
    exit;
}

final class InterlinkStrategyRecent implements InterlinkStrategy
{
    public function candidates($source_post_id, array $keywords, $limit, $max_per_target) : array {
        $q = new \WP_Query([
            'post_type'        => 'any',
            'post_status'      => 'publish',
            'posts_per_page'   => (int) ($limit * 3),
            'orderby'          => 'date',
            'order'            => 'DESC',
            'post__not_in'     => [(int) $source_post_id],
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ]);
        $candidates = [];
        foreach ($q->posts as $post) {
            $anchor = $this->pick_anchor($post, $keywords);
            if ($anchor === '') {
                continue;
            }
            $age_days = max(0, (int) round((time() - strtotime($post->post_date_gmt)) / DAY_IN_SECONDS));
            // Score: newer posts score higher (1 / (age + 1)).
            $candidates[] = [
                'post_id' => (int) $post->ID,
                'title'   => $post->post_title,
                'url'     => (string) get_permalink($post->ID),
                'anchor'  => $anchor,
                'score'   => 1.0 / ($age_days + 1),
            ];
            if (count($candidates) >= $limit) {
                break;
            }
        }
        return $candidates;
    }

    /**
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
            if ($kw !== '' && function_exists('mb_stripos') && mb_stripos($title, $kw) !== false) {
                return $kw;
            }
        }
        return $title;
    }
}
