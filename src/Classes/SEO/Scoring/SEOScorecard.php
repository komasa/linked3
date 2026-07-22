<?php

declare(strict_types=1);
/**
 * SEO scorecard — 0-100 composite score with subscores + improvement tips.
 *
 * Dimensions (per SEOConfig::get('scoring.weights')):
 *   1. Keyword density (0-1.0) — primary keyword occurrence rate.
 *   2. Title length (0-1.0) — 30-60 chars optimal.
 *   3. Meta description (0-1.0) — presence + 120-160 chars optimal.
 *   4. Internal links (0-1.0) — 3+ intra-site links.
 *   5. External links (0-1.0) — 1+ outbound link.
 *   6. Image alt (0-1.0) — ratio of <img> tags with alt text.
 *   7. Readability (0-1.0) — avg sentence length proxy.
 *   8. Content length (0-1.0) — 600+ words optimal.
 *   9. Schema present (0-1.0) — 1 if any JSON-LD emitted for this post.
 *
 * Weights are normalised at runtime so they sum to 1.0 (defensive: a
 * filter override that drops a weight or doubles it doesn't break the
 * 0-100 scale).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Scoring
 */

namespace Linked3\Classes\SEO\Scoring;

use Linked3\Classes\SEO\SEOConfig;
use Linked3\Classes\SEO\Keyword\KeywordExtractor;
use Linked3\Classes\SEO\Schema\SchemaMarkup;



if (!defined('ABSPATH')) {
    exit;
}
final class SEOScorecard
{
    /**
     * @param \WP_Post $post
     * @return array{
     *     score:int,
     *     grade:string,
     *     subscores:array<string,float>,
     *     tips:string[]
     * }
     */
    public function evaluate(WP_Post $post): array {
        $text = wp_strip_all_tags((string) $post->post_content);
        $title = (string) $post->post_title;
        $meta = $this->meta_description($post);

        $primary_keyword = $this->primary_keyword($title . ' ' . $text);
        $subscores = [
            'keyword_density'  => $this->score_keyword_density($text, $primary_keyword),
            'title_length'     => $this->score_title_length($title),
            'meta_description' => $this->score_meta_description($meta),
            'internal_links'   => $this->score_internal_links($post->post_content),
            'external_links'   => $this->score_external_links($post->post_content),
            'image_alt'        => $this->score_image_alt($post->post_content),
            'readability'      => $this->score_readability($text),
            'content_length'   => $this->score_content_length($text),
            'schema_present'   => $this->score_schema_present($post),
        ];
        $weights = (array) SEOConfig::get('scoring.weights', []);
        $total_weight = 0.0;
        foreach ($weights as $w) {
            $total_weight += (float) $w;
        }
        if ($total_weight <= 0) {
            // Equal weights fallback.
            $weights = array_fill_keys(array_keys($subscores), 1.0 / count($subscores));
            $total_weight = 1.0;
        }
        $weighted = 0.0;
        foreach ($subscores as $key => $val) {
            $w = isset($weights[$key]) ? (float) $weights[$key] / $total_weight : 0.0;
            $weighted += $val * $w;
        }
        $score = (int) round($weighted * 100);
        $score = max(0, min(100, $score));

        return [
            'score'         => $score,
            'grade'         => self::grade($score),
            'primary_keyword' => $primary_keyword,
            'subscores'     => $subscores,
            'tips'          => $this->tips($subscores, $title, $meta, $text, $primary_keyword),
        ];
    }

    /**
     * @param \WP_Post $post
     * @return string
     */
    private function meta_description(WP_Post $post) : mixed {
        $meta = (string) get_post_meta($post->ID, '_linked3_meta_description', true);
        if ($meta === '') {
            $meta = (string) $post->post_excerpt;
        }
        // Yoast / RankMath / AIOSEO compatibility is handled by the
        // Adapter layer; if an adapter is active, it overrides this
        // via the linked3/seo_meta_description filter.
        return (string) apply_filters('linked3/seo_meta_description', $meta, $post);
    }

    /**
     * @param string $text
     * @return string
     */
    private function primary_keyword(string $text) : mixed     {
        $kw = (new \Linked3\Classes\SEO\Keyword\KeywordExtractor())->extract_keywords($text, 1);
        return $kw[0] ?? '';
    }

    /**
     * @return float
     */
    private function score_keyword_density($text, $keyword) : mixed {
        if ($keyword === '' || $text === '') {
            return 0.0;
        }
        $count = function_exists('mb_substr_count') ? mb_substr_count($text, $keyword, 'UTF-8') : substr_count($text, $keyword);
        if ($count === 0) {
            return 0.0;
        }
        $words = str_word_count($text);
        if ($words === 0) {
            return 0.0;
        }
        $density = ($count / $words) * 100;
        // Optimal range: 0.5% – 2.5%.
        if ($density >= 0.5 && $density <= 2.5) {
            return 1.0;
        }
        if ($density < 0.5) {
            return max(0.0, $density / 0.5);
        }
        // Above 2.5% — penalise over-stuffing.
        return max(0.0, 1.0 - ($density - 2.5) / 5.0);
    }

    /**
     * @return float
     */
    private function score_title_length($title) : mixed     {
        $len = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($len === 0) {
            return 0.0;
        }
        if ($len >= 30 && $len <= 60) {
            return 1.0;
        }
        if ($len < 30) {
            return $len / 30.0;
        }
        return max(0.0, 1.0 - ($len - 60) / 60.0);
    }

    /**
     * @return float
     */
    private function score_meta_description($meta) : mixed {
        if ($meta === '') {
            return 0.0;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($meta, 'UTF-8') : strlen($meta);
        if ($len >= 120 && $len <= 160) {
            return 1.0;
        }
        if ($len < 120) {
            return $len / 120.0;
        }
        return max(0.0, 1.0 - ($len - 160) / 160.0);
    }

    /**
     * @return float
     */
    private function score_internal_links($content) : mixed     {
        $site_host = (string) wp_parse_url(site_url(), PHP_URL_HOST);
        $count = preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\']#i', (string) $content, $m) ? count($m[1]) : 0;
        $internal = 0;
        foreach ($m[1] ?? [] as $href) {
            $host = (string) wp_parse_url($href, PHP_URL_HOST);
            if ($host === '' || $host === $site_host) {
                $internal++;
            }
        }
        if ($internal === 0) {
            return 0.0;
        }
        return min(1.0, $internal / 3.0);
    }

    /**
     * @return float
     */
    private function score_external_links($content): float
    {
        $site_host = (string) wp_parse_url(site_url(), PHP_URL_HOST);
        $external = 0;
        if (preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\']#i', (string) $content, $m)) {
            foreach ($m[1] as $href) {
                $host = (string) wp_parse_url($href, PHP_URL_HOST);
                if ($host !== '' && $host !== $site_host) {
                    $external++;
                }
            }
        }
        return $external >= 1 ? 1.0 : 0.0;
    }

    /**
     * @return float
     */
    private function score_image_alt($content): float
    {
        $total = preg_match_all('#<img\b#i', (string) $content);
        if ($total === 0 || $total === false) {
            return 1.0; // no images — no penalty.
        }
        $with_alt = preg_match_all('#<img\b[^>]*\balt\s*=\s*["\'][^"\']+["\']#i', (string) $content);
        $with_alt = $with_alt === false ? 0 : $with_alt;
        return $with_alt / max(1, $total);
    }

    /**
     * @return float
     */
    private function score_readability($text): float
    {
        $text = trim((string) $text);
        if ($text === '') {
            return 0.0;
        }
        // Avg sentence length (split on . ! ? 。!?). Shorter = better.
        $sentences = preg_split('/[.!?。!?]+/u', $text);
        $sentences = array_filter(array_map('trim', $sentences));
        if (empty($sentences)) {
            return 0.0;
        }
        $total_words = 0;
        foreach ($sentences as $s) {
            $total_words += str_word_count($s);
        }
        $avg = $total_words / count($sentences);
        // Optimal: 12-20 words/sentence. Above 30 = bad.
        if ($avg <= 20) {
            return 1.0;
        }
        if ($avg <= 30) {
            return 1.0 - ($avg - 20) / 20.0;
        }
        return max(0.0, 0.5 - ($avg - 30) / 30.0);
    }

    /**
     * @return float
     */
    private function score_content_length($text): float
    {
        $words = str_word_count($text);
        if ($words === 0) {
            return 0.0;
        }
        if ($words >= 600 && $words <= 2500) {
            return 1.0;
        }
        if ($words < 600) {
            return $words / 600.0;
        }
        return max(0.5, 1.0 - ($words - 2500) / 5000.0);
    }

    /**
     * @return float
     */
    private function score_schema_present($post): float
    {
        $json = SchemaMarkup::instance()->for_post($post);
        return $json !== '' ? 1.0 : 0.0;
    }

    /**
     * @param int $score
     * @return string
     */
    public static function grade(int $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    /**
     * @return string[]
     */
    private function tips(array $subscores, $title, $meta, $text, $keyword): array
    {
        $tips = [];
        if ($subscores['keyword_density'] < 0.6) {
            $tips[] = $keyword === ''
                ? __('确定主关键词并在内容中自然使用。', 'linked3')
                : sprintf(__('关键词「%s」密度偏低,建议再使用 2-3 次。', 'linked3'), $keyword);
        } elseif ($subscores['keyword_density'] > 0.9 && $subscores['keyword_density'] < 1.0) {
            $tips[] = __('关键词密度过高,避免堆砌以免被惩罚。', 'linked3');
        }
        if ($subscores['title_length'] < 0.7) {
            $tips[] = __('标题长度建议 30-60 字符以优化搜索结果展示。', 'linked3');
        }
        if ($subscores['meta_description'] < 0.7) {
            $tips[] = __('添加 120-160 字符的 Meta 描述。', 'linked3');
        }
        if ($subscores['internal_links'] < 0.7) {
            $tips[] = __('添加 3 个或以上内链到相关文章。', 'linked3');
        }
        if ($subscores['external_links'] < 0.5) {
            $tips[] = __('至少添加 1 个外链到权威来源。', 'linked3');
        }
        if ($subscores['image_alt'] < 1.0) {
            $tips[] = __('为所有图片添加描述性 alt 文本。', 'linked3');
        }
        if ($subscores['readability'] < 0.7) {
            $tips[] = __('缩短句子,平均每句 12-20 词。', 'linked3');
        }
        if ($subscores['content_length'] < 0.7) {
            $tips[] = __('扩充内容至至少 600 字以提升排名潜力。', 'linked3');
        }
        if ($subscores['schema_present'] < 1.0) {
            $tips[] = __('添加结构化数据(FAQ/HowTo/Article JSON-LD)。', 'linked3');
        }
        return $tips;
    }
}
