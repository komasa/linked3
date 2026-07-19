<?php

declare(strict_types=1);
/**
 * Publish Target Interface — pluggable destination for article publishing.
 *
 * 4 implementations: local / remote-wp / remote-db / custom-api.
 * Each takes a WP-style post array and returns a result with remote_id.
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;

if (!defined('ABSPATH')) {
    exit;
}

interface PublishTargetInterface
{
    /**
     * @return string Target type slug.
     */
    public function type();

    /**
     * @return string Human-readable label.
     */
    public function label();

    /**
     * Publish a single post to this target.
     *
     * @param array $post  {post_title, post_content, post_excerpt, post_status, post_type, post_author, categories, tags}
     * @param array $config Target-specific config (decrypted).
     * @return array{ok:bool, remote_id:string, message:string, response_code:int}
     */
    public function publish(array $post, array $config);

    /**
     * Test the connection / credentials. Non-destructive.
     *
     * @param array $config
     * @return array{ok:bool, message:string}
     */
    public function test(array $config);
}
