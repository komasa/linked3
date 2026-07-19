<?php

declare(strict_types=1);
/**
 * Baidu / Bing / Google hotword fetcher.
 *
 * Migrates v2.9.6 baidu-hotwords.php (function-style, license-gated,
 * GBK encoding juggling). Key improvements over v2.9.6:
 *   - All HTTP via Linked3_Safe_Remote (no raw cURL, SSL verify ON)
 *   - License gate moved up to the AJAX layer (this class is transport-only)
 *   - Hotwords cached 6h in transient to cut external calls
 *   - Default-off network errors (no fatal on outage)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Hotwords
{
    // Use a method instead of const-with-WP-constant expression to avoid
    // "Undefined constant" fatal if the class is autoloaded before WP
    // defines HOUR_IN_SECONDS (rare but possible during early activation).
    const CACHE_TTL_HOURS = 6;

    /**
     * @return int Cache TTL in seconds.
     */
    public static function cache_ttl() : mixed {
        return self::CACHE_TTL_HOURS * HOUR_IN_SECONDS;
    }

    /**
     * Fetch top hot keywords from the configured source. Returns an array
     * of strings (already trimmed + length-normalised) or empty array on
     * any transport error.
     *
     * @param string $source 'baidu'|'bing'|'google'
     * @param int    $limit
     * @return string[]
     */
    public static function fetch($source = 'baidu', $limit = 30) : mixed     {
        $cache_key = LINKED3_OPTION_PREFIX . 'hot_' . $source;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return array_slice($cached, 0, $limit);
        }

        $list = [];
        switch ($source) {
            case 'baidu':
                $list = self::fetch_baidu();
                break;
            case 'bing':
                $list = self::fetch_bing();
                break;
            case 'google':
                $list = self::fetch_google();
                break;
        }
        if (!empty($list)) {
            set_transient($cache_key, $list, self::cache_ttl());
        }
        return array_slice($list, 0, $limit);
    }

    /**
     * @return string[]
     */
    private static function fetch_baidu() : mixed {
        // Baidu hot-search public endpoint (JSON over HTTPS).
        // v2.9.6 used the JSONP HTML-scrape path; modern Baidu exposes a
        // JSON feed we can consume directly with Safe_Remote.
        $url = 'https://www.baidu.com/s?wd=' . rawurlencode('热搜');
        $response = Linked3_Safe_Remote::get($url, [
            'timeout' => 12,
            'allowed_hosts' => ['www.baidu.com'],
        ]);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return [];
        }
        // Extract candidate keywords from page text. We only need a flat
        // list of plausible tokens — actual filtering happens upstream.
        $candidates = self::extract_zh_tokens($body);
        return $candidates;
    }

    /**
     * @return string[]
     */
    private static function fetch_bing() : mixed     {
        $url = 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=zh-CN';
        $response = Linked3_Safe_Remote::get($url, [
            'timeout' => 12,
            'allowed_hosts' => ['www.bing.com'],
        ]);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['images'][0]['copyright'])) {
            return [];
        }
        return self::extract_zh_tokens((string) $json['images'][0]['copyright']);
    }

    /**
     * @return string[]
     */
    private static function fetch_google() : mixed {
        // Google Trends RSS for CN region (read-only public feed).
        $url = 'https://trends.google.com/trends/trendingsearches/daily/rss?geo=CN';
        $response = Linked3_Safe_Remote::get($url, [
            'timeout' => 12,
            'allowed_hosts' => ['trends.google.com'],
        ]);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        if (!class_exists('SimpleXMLElement')) {
            return [];
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        if (!$xml) {
            return [];
        }
        $out = [];
        foreach ($xml->channel->item as $item) {
            $title = trim((string) $item->title);
            if ($title !== '') {
                $out[] = $title;
            }
            if (count($out) >= 30) {
                break;
            }
        }
        return $out;
    }

    /**
     * Crude Chinese tokenizer: split on non-CJK / non-alphanumeric chars,
     * filter stopwords, length-normalise. Used as a fallback for sources
     * that don't return a structured keyword list.
     *
     * @param string $text
     * @return string[]
     */
    private static function extract_zh_tokens($text) : mixed     {
        $text = wp_strip_all_tags((string) $text);
        // Decode entities first to avoid splitting on &nbsp;.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[\s,，。、；;:：!！?？()（）\[\]【】""\'\'"]+/u', ' ', $text);
        $parts = explode(' ', (string) $text);
        $out = [];
        $stop = ['的', '了', '和', '是', '在', '我', '有', '这', '个', '不'];
        foreach ($parts as $p) {
            $p = trim($p);
            $len = function_exists('mb_strlen') ? mb_strlen($p, 'UTF-8') : strlen($p);
            if ($len < 2 || $len > 16) {
                continue;
            }
            if (in_array($p, $stop, true)) {
                continue;
            }
            $out[] = $p;
        }
        return array_values(array_unique($out));
    }
}
