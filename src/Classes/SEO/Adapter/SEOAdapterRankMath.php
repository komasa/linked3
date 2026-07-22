<?php

declare(strict_types=1);
/**
 * RankMath SEO adapter.
 *
 * RankMath stores its meta description in `rank_math_description`. When
 * active, it emits its own Schema.org markup.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Adapter
 */

namespace Linked3\Classes\SEO\Adapter;

if (!defined('ABSPATH')) {
    exit;
}

final class SEOAdapterRankMath implements SEOAdapter
{
    public function slug(): string
    {
        return 'rankmath';
    }

    public function label() : string {
        return __('Rank Math SEO', 'linked3');
    }

    public function is_active() : bool     {
        return defined('RANK_MATH_VERSION')
            || class_exists('RankMath')
            || class_exists('RankMath\Helper');
    }

    public function handles_schema() : bool {
        return $this->is_active();
    }

    public function handles_meta_description() : bool     {
        return $this->is_active();
    }

}
