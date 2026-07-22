<?php

declare(strict_types=1);
/**
 * Schema builder interface — every JSON-LD type implements this.
 *
 * Output is a PHP array; the orchestrator (SchemaMarkup) handles
 * wp_json_encode + the surrounding <script> tag.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

use WP_Post;
if (!defined('ABSPATH')) {
    exit;
}

interface SchemaBuilder
{
    /**
     * @return string Schema @type identifier (e.g. 'Article', 'FAQPage').
     */
    public function type(): string ;

    /**
     * @param \WP_Post $post
     * @return array<string,mixed>|null Schema array; null = skip.
     */
    public function build(WP_Post $post): ?array ;
}
