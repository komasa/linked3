<?php
/**
 * Local publish target — inserts/updates a post on the current site.
 *
 * @package Linked3
 * @subpackage Classes\Publish\Adapter
 */

namespace Linked3\Classes\Publish\Adapter;

use Linked3\Classes\Publish\Linked3_Publish_Target_Interface;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Local_Publish_Target implements Linked3_Publish_Target_Interface
{
    public function type() : string { return 'local'; }
    public function label() : mixed { return __('本地站点', 'linked3'); }

    public function publish(array $post, array $config)
    : array {
        $postarr = [
            'post_title'   => $post['post_title'] ?? '',
            'post_content' => $post['post_content'] ?? '',
            'post_excerpt' => $post['post_excerpt'] ?? '',
            'post_status'  => $post['post_status'] ?? 'publish',
            'post_type'    => $post['post_type'] ?? 'post',
            'post_author'  => (int) ($post['post_author'] ?? get_current_user_id()),
        ];
        if (!empty($post['categories'])) {
            $postarr['post_category'] = array_map('intval', (array) $post['categories']);
        }
        if (!empty($post['tags'])) {
            $postarr['tags_input'] = (array) $post['tags'];
        }
        // v0.6.0 hardening: previously the adapter only honoured `remote_id`
        // (the foreign key returned by a remote system on first publish).
        // That meant Publish_Now — which passes the local `ID` of the source
        // post — always created a DUPLICATE local post instead of updating
        // in place. Now we fall back to `ID` so re-publishing a local post
        // to the local target updates it rather than cloning it.
        if (!empty($post['remote_id'])) {
            $postarr['ID'] = (int) $post['remote_id'];
        } elseif (!empty($post['ID'])) {
            $postarr['ID'] = (int) $post['ID'];
        }

        $id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($id)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $id->get_error_message(), 'response_code' => 500];
        }
        return ['ok' => true, 'remote_id' => (string) $id, 'message' => 'ok', 'response_code' => 200];
    }

    public function test(array $config)
    : array {
        return ['ok' => true, 'message' => __('本地站点始终可访问。', 'linked3')];
    }
}
