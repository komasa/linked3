<?php
/**
 * BlogPosting schema builder — inherits Article fields, adds wordCount.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Schema_BlogPosting implements Linked3_Schema_Builder
{
    public function type()
    : string {
        return 'BlogPosting';
    }

    public function build($post) : mixed {
        $base = (new Linked3_Schema_Article())->build($post);
        if (!is_array($base)) {
            return null;
        }
        $base['@type'] = 'BlogPosting';
        $base['wordCount'] = (int) str_word_count(wp_strip_all_tags($post->post_content));
        return $base;
    }
}
