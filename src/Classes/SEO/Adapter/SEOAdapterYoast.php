<?php

declare(strict_types=1);
/**
 * Yoast SEO adapter.
 *
 * Yoast stores its meta description in the `_yoast_wpseo_metadesc` post
 * meta key. When Yoast is active, it emits its own JSON-LD (Article /
 * BlogPosting / WebPage) so Linked3 defers.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOAdapterYoast implements SEOAdapter
{
    public function slug(): string
    {
        return 'yoast';
    }

    public function label() : string {
        return __('Yoast SEO', 'linked3');
    }

    public function is_active(): bool {
        return defined('WPSEO_VERSION')
            || class_exists('WPSEO_Meta')
            || class_exists('Yoast\WP\SEO\Main');
    }

    public function handles_schema(): bool {
        return $this->is_active();
    }

    public function handles_meta_description(): bool {
        return $this->is_active();
    }

}
