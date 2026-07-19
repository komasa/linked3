<?php

declare(strict_types=1);
/**
 * Popular interlink strategy — favours posts with the highest comment /
 * view counts. Falls back to comment_count when no view-count plugin is
 * installed (which is the default WP-only path).
 *
 * Mirrors v2.9.6's `interlink_priority='popular'` mode.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Interlink
 */

namespace Linked3\Classes\SEO\Interlink;

if (!defined('ABSPATH')) {
    exit;
}

final class InterlinkStrategyPopular implements InterlinkStrategy
{
    public function candidates($source_post_id, array $keywords, $limit, $max_per_target) : mixed {
        $q = new \WP_Query([
            'post_type'        => 'any',
            'post_status'      => 'publish',
            'posts_per_page'   => (int) ($limit * 3),
            'orderby'          => 'comment_count',
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
            $candidates[] = [
                'post_id' => (int) $post->ID,
                'title'   => $post->post_title,
                'url'     => (string) get_permalink($post->ID),
                'anchor'  => $anchor,
                'score'   => (float) ($post->comment_count + 1),
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
    private function pick_anchor($post, array $keywords) : mixed     {
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
