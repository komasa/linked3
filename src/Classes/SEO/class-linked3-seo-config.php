<?php
/**
 * SEO module configuration registry.
 *
 * Central, filterable source of truth for SEO module tuning parameters.
 * Mirrors the pattern of Linked3_Plan_Definitions — defaults declared
 * statically, overridable via the `linked3/seo_config` filter so EX-side
 * A/B tests can tune without a release.
 *
 * Keys (all hot-updatable):
 *   - interlink.max_links        Max auto-injected internal links per post.
 *   - interlink.min_length       Minimum post body length before interlinking.
 *   - interlink.priority         Strategy: frequent|recent|popular.
 *   - interlink.density_guard    Hard ceiling: 1 link per N words (default 150).
 *   - external.nofollow_default  Treat unknown hosts as nofollow (default true).
 *   - external.whitelist         dofollow hosts (array).
 *   - external.sponsored_hosts   Hosts to mark rel=sponsored (affiliate).
 *   - schema.default_type        Default JSON-LD type when post-type has none.
 *   - push.daily_cap.free        Free plan: max push calls / engine / day.
 *   - push.daily_cap.pro         Pro plan: -1 = unlimited.
 *   - push.indexnow.min_gap      Min seconds between Indexnow pushes per URL (10 min dedup, v0.5.0).
 *   - push.engine_cooldown       Min seconds before retrying a failed engine.
 *   - scoring.weights            Subscore weights (must sum to 1.0 — clamped at runtime).
 *
 * @package Linked3
 * @subpackage Classes\SEO
 */

namespace Linked3\Classes\SEO;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_SEO_Config
{
    /**
     * @return array<string,mixed>
     */
    public static function defaults()
    : array {
        return [
            'interlink' => [
                'max_links'     => 5,
                'min_length'    => 200,
                'priority'      => 'frequent', // frequent|recent|popular
                'density_guard' => 150,         // 1 link per 150 words hard ceiling
                'excluded_post_types' => ['attachment', 'revision', 'nav_menu_item'],
            ],
            'external' => [
                'nofollow_default'  => true,
                'whitelist'          => [],     // dofollow hosts
                'sponsored_hosts'    => [],     // affiliate/affiliate-program hosts
            ],
            'schema' => [
                'default_type'    => 'Article',
                'enabled_types'   => ['Article', 'BlogPosting', 'FAQPage', 'Product', 'HowTo'],
                'output_hook'     => 'wp_head', // wp_head|wp_footer
                'output_priority' => 10,
            ],
            'push' => [
                'daily_cap' => [
                    'free'     => 100,
                    'pro'      => -1, // unlimited
                    'premium'  => -1,
                ],
                'indexnow' => [
                    // v0.5.0 hardening: 10-minute minimum gap between pushes
                    // to the same URL — Indexnow anti-abuse threshold (the
                    // task spec mandates >=10 min; previously defaulted to
                    // DAY_IN_SECONDS which was over-restrictive and broke
                    // re-push on legitimate post updates).
                    'min_gap'        => 10 * MINUTE_IN_SECONDS,
                    // v0.5.0: the Indexnow engine (api.indexnow.org) fans out
                    // to Bing / Yandex / Naver / Seznam automatically, so a
                    // single entry here covers all of them. Earlier default
                    // ['bing','yandex','naver'] referenced engine slugs that
                    // were never registered with the factory (only bing +
                    // indexnow actually exist), so 'yandex'/'naver' were
                    // silently dropped.
                    'engines'        => ['indexnow'],
                ],
                'engine_cooldown' => HOUR_IN_SECONDS, // 5 fails → 1h cooldown
                'circuit_threshold' => 5,
            ],
            'scoring' => [
                'weights' => [
                    'keyword_density' => 0.15,
                    'title_length'    => 0.10,
                    'meta_description' => 0.15,
                    'internal_links'  => 0.10,
                    'external_links'  => 0.05,
                    'image_alt'       => 0.15,
                    'readability'     => 0.15,
                    'content_length'  => 0.10,
                    'schema_present'  => 0.05,
                ],
            ],
            'keyword' => [
                'algorithm'    => 'textrank', // tfidf|textrank|combined
                'max_keywords' => 10,
                'min_word_len' => 2,
                'stopwords_zh' => self::default_zh_stopwords(),
                'stopwords_en' => self::default_en_stopwords(),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function all() : mixed {
        $defaults = self::defaults();
        $override = (array) apply_filters('linked3/seo_config', []);
        return self::array_merge_recursive_distinct($defaults, $override);
    }

    /**
     * @param string $path Dot-path, e.g. 'interlink.max_links'.
     * @param mixed  $fallback
     * @return mixed
     */
    public static function get($path, $fallback = null) : mixed     {
        $cfg = self::all();
        $node = $cfg;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                return $fallback;
            }
            $node = $node[$seg];
        }
        return $node;
    }

    /**
     * @return string[]
     */
    public static function default_zh_stopwords()
    : array {
        return [
            '的', '了', '和', '是', '在', '我', '有', '这', '个', '不', '也', '都',
            '就', '要', '与', '及', '或', '为', '以', '于', '可', '能', '会', '对',
            '上', '下', '中', '里', '外', '前', '后', '一', '二', '三', '但', '而',
            '如', '若', '则', '其', '之', '着', '过', '已', '将', '已', '等', '们',
            '你', '他', '她', '它', '我们', '你们', '他们', '这个', '那个', '什么',
            '怎么', '为什么', '如何', '可以', '应该', '需要', '没有', '不能', '不会',
        ];
    }

    /**
     * @return string[]
     */
    public static function default_en_stopwords()
    : array {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'if', 'then', 'else', 'for',
            'of', 'to', 'in', 'on', 'at', 'by', 'with', 'from', 'as', 'is',
            'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may',
            'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i',
            'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us',
            'them', 'my', 'your', 'his', 'its', 'our', 'their', 'what', 'which',
            'who', 'whom', 'whose', 'when', 'where', 'why', 'how', 'all', 'any',
            'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some',
            'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than',
            'too', 'very', 'just', 'also',
        ];
    }

    /**
     * Recursively merge two arrays without losing nested keys.
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    private static function array_merge_recursive_distinct(array $base, array $override) : mixed {
        $out = $base;
        foreach ($override as $k => $v) {
            if (is_array($v) && isset($out[$k]) && is_array($out[$k])) {
                $out[$k] = self::array_merge_recursive_distinct($out[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
