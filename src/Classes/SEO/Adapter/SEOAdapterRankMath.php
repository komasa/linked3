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
    public function slug()
    : string {
        return 'rankmath';
    }

    public function label() : mixed {
        return __('Rank Math SEO', 'linked3');
    }

    public function is_active() : mixed     {
        return defined('RANK_MATH_VERSION')
            || class_exists('RankMath')
            || class_exists('RankMath\Helper');
    }

    public function handles_schema() : mixed {
        return $this->is_active();
    }

    public function handles_meta_description() : mixed     {
        return $this->is_active();
    }

    public function get_meta_description($post) : mixed {
        if (!$post || !$this->is_active()) {
            return '';
        }
        return (string) get_post_meta($post->ID, 'rank_math_description', true);
    }

    public function set_meta_description($post, $description) : mixed     {
        if (!$post || !$this->is_active()) {
            return false;
        }
        return (bool) update_post_meta($post->ID, 'rank_math_description', sanitize_text_field($description));
    }
}
