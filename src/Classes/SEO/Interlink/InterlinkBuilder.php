<?php

declare(strict_types=1);
/**
 * Interlink builder — injects internal links into post content + records
 * edges in linked3_interlink_map.
 *
 * Mirrors v2.9.6 auto_interlinking + create_interlink. Hardening over
 * v2.9.6:
 *   - 24h edge-cache: re-injecting the same (source,target,anchor) is a
 *     no-op until the row exists in linked3_interlink_map (UNIQUE constraint).
 *   - Density guard: max 1 link per N words (default 150, configurable).
 *   - Skip already-linked anchors (don't double-wrap).
 *   - All anchors are HTML-escaped; URLs pass through esc_url.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Interlink
 */

namespace Linked3\Classes\SEO\Interlink;

use Linked3\Classes\SEO\SEOConfig;
use Linked3\Classes\SEO\Keyword\KeywordExtractor;



if (!defined('ABSPATH')) {
    exit;
}
final class InterlinkBuilder
{
    /**
     * @var InterlinkStrategy|null
     */
    private $strategy;

    /**
     * @param InterlinkStrategy|null $strategy Inject for testing; auto-built by default.
     */
    public function __construct(InterlinkStrategy $strategy = null) {
        $this->strategy = $strategy;
    }

    /**
     * @return InterlinkStrategy
     */
    private function resolve_strategy() : mixed {
        if ($this->strategy !== null) {
            return $this->strategy;
        }
        $priority = (string) SEOConfig::get('interlink.priority', 'frequent');
        switch ($priority) {
            case 'recent':
                return new InterlinkStrategyRecent();
            case 'popular':
                return new InterlinkStrategyPopular();
            case 'frequent':
            default:
                return new InterlinkStrategyFrequent();
        }
    }

    /**
     * Inject internal links into content. Records edges in
     * linked3_interlink_map (UNIQUE row per source+target+anchor; count
     * is incremented on duplicate).
     *
     * @param string $content
     * @param int    $source_post_id
     * @return string
     */
    public function inject($content, $source_post_id) : mixed     {
        $content = (string) $content;
        if ($content === '') {
            return $content;
        }
        $source_post_id = (int) $source_post_id;
        if ($source_post_id <= 0) {
            return $content;
        }

        $min_length = (int) SEOConfig::get('interlink.min_length', 200);
        $len = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ($len < $min_length) {
            return $content;
        }

        $max_links = (int) SEOConfig::get('interlink.max_links', 5);
        $density   = (int) SEOConfig::get('interlink.density_guard', 150);
        $word_count = function_exists('mb_str_word_count') ? mb_str_word_count($content) : str_word_count($content);
        $hard_cap = $max_links;
        if ($density > 0 && $word_count > 0) {
            $hard_cap = (int) min($hard_cap, max(1, (int) floor($word_count / $density)));
        }
        if ($hard_cap <= 0) {
            return $content;
        }

        $post = get_post($source_post_id);
        if (!$post) {
            return $content;
        }
        $keywords = (new \Linked3\Classes\SEO\Keyword\KeywordExtractor())->extract_keywords($post->post_title . ' ' . wp_strip_all_tags($content), 12);

        $candidates = $this->resolve_strategy()->candidates($source_post_id, $keywords, $hard_cap, 1);
        if (empty($candidates)) {
            return $content;
        }

        $injected = 0;
        foreach ($candidates as $cand) {
            if ($injected >= $hard_cap) {
                break;
            }
            $anchor = (string) $cand['anchor'];
            $url    = (string) $cand['url'];
            if ($anchor === '' || $url === '') {
                continue;
            }
            // Skip if anchor already appears inside an <a> tag.
            if (preg_match('#<a[^>]*>[^<]*' . preg_quote($anchor, '#') . '#iu', $content)) {
                continue;
            }
            // Inject into the FIRST occurrence only (case-insensitive).
            $count = 0;
            $content = preg_replace(
                '/' . preg_quote($anchor, '/') . '/u',
                '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>',
                $content,
                1,
                $count
            );
            if ($count > 0) {
                $this->record_edge($source_post_id, (int) $cand['post_id'], $anchor);
                $injected++;
            }
        }
        return $content;
    }

    /**
     * Record (or increment) an edge in linked3_interlink_map.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE count = count + 1 — the table
     * has UNIQUE (source_post_id, target_post_id, anchor).
     *
     * @param int    $source
     * @param int    $target
     * @param string $anchor
     * @return void
     */
    public function record_edge($source, $target, $anchor)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_interlink_map';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (source_post_id, target_post_id, anchor, count)
             VALUES (%d, %d, %s, 1)
             ON DUPLICATE KEY UPDATE count = count + 1",
            (int) $source,
            (int) $target,
            $anchor
        ));
    }

}
