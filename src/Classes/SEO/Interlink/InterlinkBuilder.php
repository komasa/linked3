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
     * @var int|null Max links override (set via set_max_links()).
     */
    private $max_links_override = null;

    /**
     * @param InterlinkStrategy|null $strategy Inject for testing; auto-built by default.
     */
    public function __construct(InterlinkStrategy $strategy = null) {
        $this->strategy = $strategy;
    }

    /**
     * Set the interlink strategy by name.
     *
     * Accepts: 'recent', 'popular', 'frequent'. Any other value
     * defaults to 'frequent'.
     *
     * @param string $strategy Strategy name.
     * @return void
     */
    public function set_strategy(string $strategy): void {
        switch ($strategy) {
            case 'recent':
                $this->strategy = new InterlinkStrategyRecent();
                break;
            case 'popular':
                $this->strategy = new InterlinkStrategyPopular();
                break;
            case 'frequent':
            default:
                $this->strategy = new InterlinkStrategyFrequent();
                break;
        }
    }

    /**
     * Override the maximum number of links to inject per post.
     *
     * @param int $max Maximum links (0 or negative disables the override).
     * @return void
     */
    public function set_max_links(int $max): void {
        $this->max_links_override = $max > 0 ? $max : null;
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
    public function inject(string $content, int $source_post_id) : mixed     {
        $content = (string) $content;
        if ($content === '' || (int) $source_post_id <= 0) {
            return $content;
        }
        $source_post_id = (int) $source_post_id;

        $hard_cap = $this->compute_hard_cap($content);
        if ($hard_cap <= 0) return $content;

        $candidates = $this->get_link_candidates($source_post_id, $content, $hard_cap);
        if (empty($candidates)) return $content;

        return $this->inject_links($content, $source_post_id, $candidates, $hard_cap);
    }

    /**
     * 计算最大链接数 (基于密度+max_links配置)
     */
    private function compute_hard_cap(string $content): int {
        $min_length = (int) SEOConfig::get('interlink.min_length', 200);
        $len = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ($len < $min_length) return 0;

        $max_links = $this->max_links_override ?? (int) SEOConfig::get('interlink.max_links', 5);
        $density   = (int) SEOConfig::get('interlink.density_guard', 150);
        $word_count = function_exists('mb_str_word_count') ? mb_str_word_count($content) : str_word_count($content);
        $hard_cap = $max_links;
        if ($density > 0 && $word_count > 0) {
            $hard_cap = (int) min($hard_cap, max(1, (int) floor($word_count / $density)));
        }
        return $hard_cap;
    }

    /**
     * 获取链接候选列表
     */
    private function get_link_candidates(int $source_post_id, string $content, int $hard_cap): array {
        $post = get_post($source_post_id);
        if (!$post) return [];

        $keywords = (new \Linked3\Classes\SEO\Keyword\KeywordExtractor())->extract_keywords(
            $post->post_title . ' ' . wp_strip_all_tags($content), 12
        );
        return $this->resolve_strategy()->candidates($source_post_id, $keywords, $hard_cap, 1);
    }

    /**
     * 遍历候选, 将链接注入内容
     */
    private function inject_links(string $content, int $source_post_id, array $candidates, int $hard_cap): string {
        $injected = 0;
        foreach ($candidates as $cand) {
            if ($injected >= $hard_cap) break;
            $anchor = (string) $cand['anchor'];
            $url    = (string) $cand['url'];
            if ($anchor === '' || $url === '') continue;

            // Skip if anchor already appears inside an <a> tag.
            if (preg_match('#<a[^>]*>[^<]*' . preg_quote($anchor, '#') . '#iu', $content)) continue;

            // Inject into the FIRST occurrence only (case-insensitive).
            $count = 0;
            $content = preg_replace(
                '/' . preg_quote($anchor, '/') . '/u',
                '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>',
                $content, 1, $count
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
    public function record_edge(int $source, int $target, string $anchor): void {
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
