<?php

declare(strict_types=1);
/**
 * SEO adapter detector — picks the active 3rd-party SEO plugin (if any).
 *
 * Detection order mirrors the typical install base priority: Yoast →
 * RankMath → AIOSEO. The first adapter whose is_active() returns true
 * wins. The result is cached per-request via a static.
 *
 * When no 3rd-party plugin is active, the detector returns a null
 * adapter (SEOAdapter_None) so callers can do uniform
 * `$adapter->handles_schema()` checks without null-guarding everywhere.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOAdapterDetector
{
    /** @var SEOAdapter|null */
    private static $resolved;

    /**
     * @return SEOAdapter
     */
    public static function resolve() : mixed {
        if (self::$resolved !== null) {
            return self::$resolved;
        }
        $candidates = [
            new SEOAdapterYoast(),
            new SEOAdapterRankMath(),
            new SEOAdapterAIOSEO(),
        ];
        /**
         * Allow Pro / third-party adapters to register.
         */
        $candidates = (array) apply_filters('linked3/seo_adapter_candidates', $candidates);

        foreach ($candidates as $adapter) {
            if ($adapter instanceof SEOAdapter && $adapter->is_active()) {
                self::$resolved = $adapter;
                return $adapter;
            }
        }
        self::$resolved = new SEOAdapter_None();
        return self::$resolved;
    }

    /**
     * @return string[]
     */
    public static function available() : mixed     {
        $out = [];
        foreach ([
            new SEOAdapterYoast(),
            new SEOAdapterRankMath(),
            new SEOAdapterAIOSEO(),
        ] as $a) {
            $out[] = $a->slug() . '|' . $a->label() . '|' . ($a->is_active() ? '1' : '0');
        }
        return $out;
    }
}

/**
 * Null-object adapter used when no 3rd-party SEO plugin is active.
 * Linked3 emits its own schema / meta in this case.
 */
final class SEOAdapter_None implements SEOAdapter
{
    public function slug()
    : string {
        return 'none';
    }

    public function label() : mixed {
        return __('Linked3 原生 SEO(无第三方适配器)', 'linked3');
    }

    public function is_active()
    : bool {
        return true; // always — represents the default path.
    }

    public function handles_schema()
    : bool {
        return false;
    }

    public function handles_meta_description()
    : bool {
        return false;
    }

    public function get_meta_description($post)
    : string {
        return '';
    }

    public function set_meta_description($post, $description) : mixed     {
        if (!$post) {
            return false;
        }
        return (bool) update_post_meta($post->ID, '_linked3_meta_description', sanitize_text_field($description));
    }
}
