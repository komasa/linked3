<?php

declare(strict_types=1);
/**
 * All-in-One SEO (AIOSEO) adapter.
 *
 * AIOSEO stores its meta description in `_aioseo_description` (legacy
 * `_aioseop_description`). When active, it emits its own Schema.org.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOAdapterAIOSEO implements SEOAdapter
{
    public function slug(): string
    {
        return 'aioseo';
    }

    public function label() : string {
        return __('All in One SEO', 'linked3');
    }

    public function is_active() : mixed     {
        return defined('AIOSEO_VERSION')
            || class_exists('AIOSEO\Plugin\AIOSEO')
            || class_exists('All_in_One_SEO_Pack');
    }

    public function handles_schema() : mixed {
        return $this->is_active();
    }

    public function handles_meta_description() : mixed     {
        return $this->is_active();
    }

}
