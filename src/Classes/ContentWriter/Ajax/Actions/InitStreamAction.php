<?php

declare(strict_types=1);
/**
 * Init Stream Action — starts an SSE streaming generation, caches chunks
 * for resume. The actual chunk flushing is handled by a dedicated SSE endpoint.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Ajax\Actions
 */

namespace Linked3\Classes\ContentWriter\Ajax\Actions;

use Linked3\Classes\ContentWriter\Ajax\ContentWriterBaseAjaxAction;



if (!defined('ABSPATH')) {
    exit;
}
final class InitStreamAction extends ContentWriterBaseAjaxAction
{
    public function handle() : mixed {
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        if (!$keyword && !$title) {
            $this->send_error(__('关键词或标题至少填一个。', 'linked3'), 400);
        }

        // Generate a unique cache key for this stream session.
        $cache_key = 'linked3_stream_' . wp_generate_password(24, false);
        $session_id = sanitize_text_field($_POST['session_id'] ?? wp_generate_password(16, false));

        // Seed the SSE cache with an empty buffer + 10-min expiry.
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_sse_message_cache';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (cache_key, payload, expires_at) VALUES (%s, %s, %s)",
            $cache_key, wp_json_encode(['chunks' => [], 'done' => false]), gmdate('Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS)
        ));

        // Schedule the background generation (v0.3.9 cron-based processor).
        // For v0.3.8 we return the cache_key and let the frontend poll.
        $this->send_success([
            'cache_key' => $cache_key,
            'session_id' => $session_id,
            'poll_url' => rest_url('linked3/v1/stream/' . $cache_key),
        ]);
    }
}
