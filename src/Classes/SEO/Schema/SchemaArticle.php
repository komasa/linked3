<?php

declare(strict_types=1);
/**
 * Article schema builder.
 *
 * Migrates v2.9.6 add_schema_markup 'Article' branch. Pulls title, excerpt,
 * author, datePublished, dateModified, publisher from WP post + site config.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

use WP_Post;
if (!defined('ABSPATH')) {
    exit;
}

final class SchemaArticle implements SchemaBuilder
{
    public function type(): string
    {
        return 'Article';
    }

    public function build(WP_Post $post): ?array
    {
        if (!$post) {
            return null;
        }
        $author = get_userdata((int) $post->post_author);
        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => (string) $post->post_title,
            'description'      => (string) wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30)),
            'datePublished'    => mysql2date('c', $post->post_date_gmt, false),
            'dateModified'     => mysql2date('c', $post->post_modified_gmt, false),
            'author'           => [
                '@type' => 'Person',
                'name'  => $author ? (string) $author->display_name : '',
            ],
            'publisher'        => $this->publisher(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => (string) get_permalink($post->ID)],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function publisher() : mixed {
        $name = get_bloginfo('name');
        $logo = (int) get_option('site_logo', 0);
        $logo_url = $logo ? (string) wp_get_attachment_image_url($logo, 'full') : '';
        $out = ['@type' => 'Organization', 'name' => $name];
        if ($logo_url !== '') {
            $out['logo'] = ['@type' => 'ImageObject', 'url' => $logo_url];
        }
        return $out;
    }
}
