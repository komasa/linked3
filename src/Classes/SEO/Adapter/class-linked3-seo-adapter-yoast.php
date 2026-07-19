<?php
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

final class Linked3_SEO_Adapter_Yoast implements Linked3_SEO_Adapter
{
    public function slug()
    : string {
        return 'yoast';
    }

    public function label() : mixed {
        return __('Yoast SEO', 'linked3');
    }

    public function is_active() : mixed     {
        return defined('WPSEO_VERSION')
            || class_exists('WPSEO_Meta')
            || class_exists('Yoast\WP\SEO\Main');
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
        return (string) get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
    }

    public function set_meta_description($post, $description) : mixed     {
        if (!$post || !$this->is_active()) {
            return false;
        }
        return (bool) update_post_meta($post->ID, '_yoast_wpseo_metadesc', sanitize_text_field($description));
    }
}
