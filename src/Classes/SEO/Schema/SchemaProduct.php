<?php

declare(strict_types=1);
/**
 * Product schema builder.
 *
 * Only fires on WooCommerce product post_type (or any post_type flagged
 * via the `linked3/seo_product_post_types` filter). Pulls price, currency,
 * availability, image from WC product meta when available.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

use WP_Post;
if (!defined('ABSPATH')) {
    exit;
}

final class SchemaProduct implements SchemaBuilder
{
    public function type(): string
    {
        return 'Product';
    }

    public function build(WP_Post $post) : ?array {
        if (!$post) {
            return null;
        }
        $allowed = (array) apply_filters('linked3/seo_product_post_types', ['product']);
        if (!in_array($post->post_type, $allowed, true)) {
            return null;
        }
        $product = null;
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
        }
        $out = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'description' => (string) wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30)),
        ];
        if ($product) {
            $price = (float) $product->get_price();
            if ($price > 0) {
                $out['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => number_format($price, 2, '.', ''),
                    'priceCurrency' => (string) (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD'),
                    'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url'           => (string) get_permalink($post->ID),
                ];
            }
            $img_id = (int) $product->get_image_id();
            if ($img_id) {
                $img_url = (string) wp_get_attachment_image_url($img_id, 'full');
                if ($img_url !== '') {
                    $out['image'] = $img_url;
                }
            }
            if (method_exists($product, 'get_average_rating')) {
                $rating = (float) $product->get_average_rating();
                if ($rating > 0) {
                    $out['aggregateRating'] = [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => $rating,
                        'reviewCount' => (int) $product->get_review_count(),
                    ];
                }
            }
        }
        return $out;
    }
}
