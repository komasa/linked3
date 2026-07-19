<?php
/**
 * Schema builder interface — every JSON-LD type implements this.
 *
 * Output is a PHP array; the orchestrator (Linked3_Schema_Markup) handles
 * wp_json_encode + the surrounding <script> tag.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Schema_Builder
{
    /**
     * @return string Schema @type identifier (e.g. 'Article', 'FAQPage').
     */
    public function type();

    /**
     * @param \WP_Post $post
     * @return array<string,mixed>|null Schema array; null = skip.
     */
    public function build($post);
}
