<?php

declare(strict_types=1);
/**
 * Scraper — anti-ban content collector with dedup.
 *
 * Replaces v2.9.6's scrape_article() which had:
 *   - No User-Agent rotation
 *   - No delay between requests (got IP-banned)
 *   - No dedup (re-scraped same URLs)
 *   - No depth/quantity limits on image station recursion
 *
 * @package Linked3
 * @subpackage Classes\Collect
 */

namespace Linked3\Classes\Collect;

use Linked3\Classes\Publish\PublishConfig;
use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class Scraper
{
    /** @var Logger */
    private $log;

    public function __construct() {
        $this->log = Logger::instance();
    }

    /**
     * Fetch a single URL with anti-ban measures.
     *
     * @param string $url
     * @return array{ok:bool, html:string, title:string, text:string, message:string}
     */
    public function fetch(string $url): array {
        $url = esc_url_raw($url);
        if (!$url) return ['ok' => false, 'html' => '', 'title' => '', 'text' => '', 'message' => __('无效 URL。', 'linked3')];

        // Rate limit: min N seconds between requests to same host.
        $this->throttle($url);

        $ua = $this->pick_ua();
        $resp = SafeRemote::get($url, [
            'timeout' => 25,
            'headers' => [
                'User-Agent'      => $ua,
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            ],
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
            'skip_ssrf' => true, // 采集场景跳过 SSRF (用户主动输入 URL)
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'html' => '', 'title' => '', 'text' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return ['ok' => false, 'html' => '', 'title' => '', 'text' => '', 'message' => sprintf(__('HTTP %d', 'linked3'), $code)];
        }
        $html = wp_remote_retrieve_body($resp);
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(wp_strip_all_tags($m[1]));
        }
        $text = $this->extract_text($html);
        return ['ok' => true, 'html' => $html, 'title' => $title, 'text' => $text, 'message' => 'ok'];
    }

    /**
     * Check whether content was already scraped (URL hash + content simhash).
     *
     * Two call patterns are supported:
     *   1. Pre-fetch URL-only probe:   is_duplicate($url, '')
     *      → returns true only if the URL was seen before AND the previously
     *        stored content hash equals the empty-content sentinel ('0').
     *        In practice this returns false on first call and does NOT mutate
     *        stored state, so a subsequent post-fetch call with real content
     *        still detects true duplicates correctly.
     *   2. Post-fetch dedup:            is_duplicate($url, $real_content)
     *      → returns true if the URL was seen before with the SAME simhash,
     *        false otherwise. Updates the stored hash on miss.
     *
     * v0.6.0 hardening: previously a pre-fetch call with empty content would
     * overwrite the stored hash with simhash('')=='0', corrupting state and
     * causing false negatives on the subsequent post-fetch check. The fix is
     * to skip the set_transient when content is empty.
     *
     * @param string $url
     * @param string $content
     * @return bool True if duplicate.
     */
    public function is_duplicate(string $url, string $content): bool {
        $url_hash = md5($url);
        $content_hash = $this->simhash($content);
        $key = 'linked3_dedup_' . $url_hash;
        $existing = get_transient($key);
        if ($existing && $existing === $content_hash && $content !== '') {
            return true;
        }
        // v3.8.0: 不在此写入,改由 mark_collected() 显式调用
        return false;
    }

    /**
     * Extract readable text from HTML (strip scripts/styles/tags).
     *
     * @param string $html
     * @return string
     */
    private function extract_text(string $html) : mixed {
        $body = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $body = preg_replace('#<style[^>]*>.*?</style>#is', '', $body);
        $body = preg_replace('#<noscript[^>]*>.*?</noscript>#is', '', $body);
        $body = wp_strip_all_tags($body);
        return trim(preg_replace('/\s+/', ' ', $body));
    }

    /**
     * Lightweight simhash — 64-bit fingerprint of content for near-dup detection.
     *
     * @param string $text
     * @return string Hex string.
     */
    private function simhash(string $text) : mixed     {
        $text = mb_strtolower($text);
        // Tokenise by CJK char + ASCII word.
        $tokens = [];
        if (preg_match_all('/[\x{4e00}-\x{9fa5}]|[a-z0-9]+/u', $text, $m)) {
            $tokens = $m[0];
        }
        if (empty($tokens)) return '0';
        $v = array_fill(0, 64, 0);
        foreach ($tokens as $t) {
            $h = md5($t);
            for ($i = 0; $i < 64; $i++) {
                $bit = (hexdec($h[intdiv($i, 4)]) >> ($i % 4)) & 1;
                $v[$i] += $bit ? 1 : -1;
            }
        }
        $out = '';
        for ($i = 0; $i < 64; $i++) {
            $out .= $v[$i] >= 0 ? '1' : '0';
        }
        // Binary → hex.
        $hex = '';
        for ($i = 0; $i < 64; $i += 4) {
            $hex .= dechex(bindec(substr($out, $i, 4)));
        }
        return $hex;
    }

    /**
     * Per-host rate limit — min N seconds between requests.
     *
     * v0.6.0 hardening: previously the transient was set to $now captured
     * BEFORE the sleep, which made rapid back-to-back calls observe a stale
     * "last request" timestamp and violate the min-gap on the second hop.
     * Now we set the transient to time() AFTER the sleep, so the stored
     * timestamp reflects when the upcoming HTTP request will actually fire.
     *
     * @param string $url
     * @return void
     */
    private function throttle(string $url): void {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) return;
        $min_gap = (int) PublishConfig::get('collect.rate_limit_seconds', 2);
        $key = 'linked3_rl_host_' . md5($host);
        $last = (int) get_transient($key);
        $now = time();
        if ($last > 0 && ($now - $last) < $min_gap) {
            sleep($min_gap - ($now - $last));
        }
        // Record the time the HTTP request will actually fire (post-sleep).
        set_transient($key, time(), HOUR_IN_SECONDS);
    }

    /**
     * Pick a random User-Agent from the rotation pool.
     *
     * @return string
     */
    private function pick_ua() : mixed {
        $pool = (array) PublishConfig::get('collect.ua_rotate_pool', ['Mozilla/5.0']);
        return $pool[array_rand($pool)];
    }
}
