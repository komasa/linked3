<?php

declare(strict_types=1);
/**
 * External link processor — applies rel attributes (nofollow / sponsored /
 * noopener) to outbound <a> tags in post content.
 *
 * Mirrors v2.9.6 process_external_links. Hardening over v2.9.6:
 *   - Whitelist-driven dofollow assignment (filterable)
 *   - Sponsored hosts list (affiliate / monetised links)
 *   - Skip internal links (same host as site_url)
 *   - Skip already-explicit rels (don't double-process user-written rels)
 *   - Always add `noopener noreferrer` for target=_blank links (security)
 *
 * Configuration (SEOConfig::get('external')):
 *   - nofollow_default: true (treat unknown hosts as nofollow)
 *   - whitelist: array of host substrings/regexes that get dofollow
 *   - sponsored_hosts: array of host substrings for rel=sponsored
 *
 * @package Linked3
 * @subpackage Classes\SEO\Links
 */

namespace Linked3\Classes\SEO\Links;

use Linked3\Classes\SEO\SEOConfig;



if (!defined('ABSPATH')) {
    exit;
}
final class ExternalLinkProcessor
{
    /** @var string 当前站点主机名 (避免闭包 use) */
    private static $ctx_site_host = '';
    /** @var bool 默认 nofollow */
    private static $ctx_nofollow_default = true;
    /** @var array dofollow 白名单 */
    private static $ctx_whitelist = [];
    /** @var array sponsored 主机列表 */
    private static $ctx_sponsored = [];

    /**
     * @param string $content
     * @return string
     */
    public static function process(string $content) : mixed {
        $content = (string) $content;
        if ($content === '') {
            return $content;
        }
        // Early exit: no <a> tags → nothing to do.
        if (stripos($content, '<a ') === false && stripos($content, '<a>') === false) {
            return $content;
        }

        self::$ctx_site_host = strtolower((string) wp_parse_url(site_url(), PHP_URL_HOST));
        self::$ctx_nofollow_default = (bool) SEOConfig::get('external.nofollow_default', true);
        self::$ctx_whitelist = (array) SEOConfig::get('external.whitelist', []);
        self::$ctx_sponsored = (array) SEOConfig::get('external.sponsored_hosts', []);

        $pattern = '#<a\b([^>]*)>(.*?)</a>#isu';
        return preg_replace_callback($pattern, [self::class, 'replace_link_callback'], $content);
    }

    /**
     * preg_replace_callback 回调: 处理单个 <a> 标签的 rel 属性 (替代闭包 use)。
     *
     * @param array $m 正则匹配
     * @return string 替换后的 HTML
     */
    private static function replace_link_callback(array $m) : mixed     {
        $attrs = $m[1];
        $inner = $m[2];
        $site_host = self::$ctx_site_host;
        $nofollow_default = self::$ctx_nofollow_default;
        $whitelist = self::$ctx_whitelist;
        $sponsored = self::$ctx_sponsored;

        // Extract href.
        if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/iu', $attrs, $h)) {
            return $m[0]; // no href → leave alone
        }
        $href = $h[2];
        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));

        // Internal link → skip (leave rel alone, but ensure noopener if target=_blank).
        if ($host === '' || $host === $site_host || (substr($host, -strlen('.' . $site_host)) === '.' . $site_host)) {
            return self::ensure_target_blank_safety($m[0], $attrs, $inner);
        }

        // Parse existing rel attribute and strip it from attrs.
        $rel_parts = self::extract_existing_rel($attrs, $attrs);

        // Apply sponsored / nofollow / noopener rules.
        $rel_parts = self::apply_rel_rules($host, $rel_parts, $sponsored, $whitelist, $nofollow_default, $attrs);

        $rel_str = implode(' ', array_unique(array_filter($rel_parts)));
        if ($rel_str !== '') {
            $attrs .= ' rel="' . esc_attr($rel_str) . '"';
        }
        return '<a' . $attrs . '>' . $inner . '</a>';
    }

    /**
     * Extract the existing rel attribute from $attrs and return its parts.
     * Modifies $attrs in place to remove the rel="..." fragment.
     *
     * @param string $attrs_in   Original attributes string.
     * @param string &$attrs_out Attributes string with rel stripped.
     * @return array  Existing rel parts.
     */
    private static function extract_existing_rel(string $attrs_in, string &$attrs_out): array
    {
        $existing_rel = '';
        if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/iu', $attrs_in, $rel_match)) {
            $existing_rel = $rel_match[2];
            $attrs_out = preg_replace('/\s*\brel\s*=\s*(["\']).*?\1/iu', '', $attrs_in);
        }
        return $existing_rel === '' ? [] : preg_split('/\s+/', trim($existing_rel));
    }

    /**
     * Apply sponsored / nofollow / noopener rules to build the final rel parts.
     *
     * @param string $host
     * @param array  $rel_parts
     * @param array  $sponsored
     * @param array  $whitelist
     * @param bool   $nofollow_default
     * @param string $attrs
     * @return array
     */
    private static function apply_rel_rules(
        string $host,
        array $rel_parts,
        array $sponsored,
        array $whitelist,
        bool $nofollow_default,
        string $attrs
    ): array {
        // Sponsored hosts.
        if (self::host_matches_any($host, $sponsored) && !in_array('sponsored', $rel_parts, true)) {
            $rel_parts[] = 'sponsored';
        }

        // Whitelisted dofollow hosts → no nofollow.
        $is_whitelisted = self::host_matches_any($host, $whitelist);
        if (!$is_whitelisted && $nofollow_default && !in_array('nofollow', $rel_parts, true)) {
            $rel_parts[] = 'nofollow';
        }

        // Add noopener/noreferrer for target=_blank.
        if (preg_match('/\btarget\s*=\s*(["\'])_blank\1/iu', $attrs)) {
            if (!in_array('noopener', $rel_parts, true)) {
                $rel_parts[] = 'noopener';
            }
            if (!in_array('noreferrer', $rel_parts, true)) {
                $rel_parts[] = 'noreferrer';
            }
        }

        return $rel_parts;
    }

    /**
     * Check if $host matches any needle in $list (case-insensitive substring).
     *
     * @param string $host
     * @param array  $list
     * @return bool
     */
    private static function host_matches_any(string $host, array $list): bool
    {
        foreach ($list as $needle) {
            if ($needle !== '' && stripos($host, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * For internal links with target=_blank, still add noopener+noreferrer.
     *
     * @param string $original
     * @param string $attrs
     * @param string $inner
     * @return string
     */
    private static function ensure_target_blank_safety(string $original, string $attrs, string $inner) : mixed {
        if (!preg_match('/\btarget\s*=\s*(["\'])_blank\1/iu', $attrs)) {
            return $original;
        }
        if (preg_match('/\brel\s*=\s*(["\']).*?\1/iu', $attrs, $rel_match) && stripos($rel_match[2], 'noopener') !== false) {
            return $original;
        }
        $new_attrs = preg_replace('/\s*\brel\s*=\s*(["\']).*?\1/iu', '', $attrs);
        $existing = isset($rel_match[2]) ? trim($rel_match[2]) : '';
        $parts = $existing === '' ? [] : preg_split('/\s+/', $existing);
        if (!in_array('noopener', $parts, true)) {
            $parts[] = 'noopener';
        }
        if (!in_array('noreferrer', $parts, true)) {
            $parts[] = 'noreferrer';
        }
        $rel_str = implode(' ', array_unique(array_filter($parts)));
        $new_attrs .= ' rel="' . esc_attr($rel_str) . '"';
        return '<a' . $new_attrs . '>' . $inner . '</a>';
    }
}
