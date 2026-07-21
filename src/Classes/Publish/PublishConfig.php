<?php

declare(strict_types=1);
/**
 * Publish module configuration registry (v0.5.1+).
 *
 * Central, filterable source of truth for publish-target tuning parameters.
 * Mirrors the pattern of SEOConfig — defaults declared statically,
 * overridable via the `linked3/publish_config` filter so EX-side A/B tests
 * can tune without a release.
 *
 * Keys (all hot-updatable):
 *   - targets.max_per_plan.{free,pro,premium}  Hard ceiling on targets per user.
 *   - targets.default_type                    Default new-target type slug.
 *   - retry.max_attempts                      Per-target publish retry ceiling.
 *   - retry.backoff_base                      Seconds; exponential backoff base.
 *   - webhook.signature_algo                  HMAC algo (default sha256).
 *   - webhook.tolerance_seconds               Replay-window tolerance for ts header.
 *   - collect.rate_limit_seconds              Min seconds between scrape requests.
 *   - collect.max_depth                       Image-station recursion depth ceiling.
 *   - collect.max_images                      Image-station per-run image ceiling.
 *   - collect.license_filter                  CC0-only filter toggle.
 *   - alert.admin_email                       Failure alert recipient (default site admin).
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;

if (!defined('ABSPATH')) {
    exit;
}

final class PublishConfig
{
    /**
     * @return array<string,mixed>
     */
    public static function defaults()
    : array {
        return [
            'targets' => [
                'max_per_plan' => [
                    'free'    => 1,
                    'pro'     => 5,
                    'premium' => -1, // unlimited
                ],
                'default_type' => 'local',
            ],
            'retry' => [
                'max_attempts' => 3,
                'backoff_base' => 60, // seconds, exponential
            ],
            'webhook' => [
                'signature_algo'   => 'sha256',
                'tolerance_seconds' => 300, // 5-min replay window
            ],
            'collect' => [
                'rate_limit_seconds' => 2,   // 1 req / 2s — task spec
                'max_depth'          => 2,   // image-station recursion
                'max_images'         => 50,  // per run
                'license_filter'     => ['cc0', 'publicdomain', 'pdm'],
                'ua_rotate_pool'     => self::default_ua_pool(),
            ],
            'alert' => [
                'admin_email' => get_option('admin_email'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function all() : mixed {
        $defaults = self::defaults();
        $override = (array) apply_filters('linked3/publish_config', []);
        return self::merge_distinct($defaults, $override);
    }

    /**
     * @param string $path Dot-path, e.g. 'targets.max_per_plan.free'.
     * @param mixed  $fallback
     * @return mixed
     */
    public static function get(string $path, $fallback = null) : mixed     {
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
     * Default User-Agent rotation pool — modern desktop browsers + a few
     * mobile strings to avoid naive per-UA blocks. Filterable via
     * `linked3/publish_config` → collect.ua_rotate_pool.
     *
     * @return string[]
     */
    public static function default_ua_pool()
    : array {
        return [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36',
        ];
    }

    /**
     * Recursively merge two arrays without losing nested keys.
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    private static function merge_distinct(array $base, array $override) : mixed {
        $out = $base;
        foreach ($override as $k => $v) {
            if (is_array($v) && isset($out[$k]) && is_array($out[$k])) {
                $out[$k] = self::merge_distinct($out[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
