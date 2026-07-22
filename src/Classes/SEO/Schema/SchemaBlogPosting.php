<?php

declare(strict_types=1);
/**
 * BlogPosting schema builder — inherits Article fields, adds wordCount.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

use WP_Post;
if (!defined('ABSPATH')) {
    exit;
}

final class SchemaBlogPosting implements SchemaBuilder
{
    public function type(): string
    {
        return 'BlogPosting';
    }

    public function build(WP_Post $post) : ?array {
        $base = (new SchemaArticle())->build($post);
        if (!is_array($base)) {
            return null;
        }
        $base['@type'] = 'BlogPosting';
        $base['wordCount'] = (int) str_word_count(wp_strip_all_tags($post->post_content));
        return $base;
    }
}
