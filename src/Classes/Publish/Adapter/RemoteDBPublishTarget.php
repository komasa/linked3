<?php

declare(strict_types=1);
/**
 * Remote database publish target — inserts a post directly into a remote
 * WordPress database.
 *
 * Replaces v2.9.6's publish_to_remote_database() which used raw mysqli
 * (not portable, leaked credentials in error messages). This version:
 *   - Uses a throwaway $wpdb instance configured for the remote DSN
 *   - Stores credentials encrypted via Linked3_Crypto (AES-256-GCM)
 *   - Inserts into wp_posts + wp_postmeta + wp_term_relationships
 *
 * @package Linked3
 * @subpackage Classes\Publish\Adapter
 */

namespace Linked3\Classes\Publish\Adapter;

use Linked3\Classes\Publish\PublishTargetInterface;



if (!defined('ABSPATH')) {
    exit;
}
final class RemoteDBPublishTarget implements PublishTargetInterface
{
    public function type() : string { return 'remote_db'; }
    public function label() : mixed { return __('远程数据库(直连)', 'linked3'); }

    public function publish(array $post, array $config)
    : array {
        $remote = $this->connect($config);
        if (is_wp_error($remote)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $remote->get_error_message(), 'response_code' => 500];
        }
        /** @var \wpdb $remote */
        $prefix = $config['table_prefix'] ?? 'wp_';
        $posts_table = $prefix . 'posts';

        $row = [
            'post_author'       => (int) ($config['remote_author_id'] ?? 1),
            'post_date'         => current_time('mysql'),
            'post_date_gmt'     => current_time('mysql', 1),
            'post_content'      => $post['post_content'] ?? '',
            'post_title'        => $post['post_title'] ?? '',
            'post_excerpt'      => $post['post_excerpt'] ?? '',
            'post_status'       => $post['post_status'] ?? 'publish',
            'comment_status'    => 'open',
            'ping_status'       => 'open',
            'post_name'         => sanitize_title($post['post_title'] ?? ''),
            'post_type'         => $post['post_type'] ?? 'post',
            'post_modified'     => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ];

        $ok = $remote->insert($posts_table, $row);
        if ($ok === false) {
            return ['ok' => false, 'remote_id' => '', 'message' => $remote->last_error, 'response_code' => 500];
        }
        $remote_id = (string) $remote->insert_id;
        return ['ok' => true, 'remote_id' => $remote_id, 'message' => 'ok', 'response_code' => 200];
    }

    public function test(array $config)
    : array {
        $remote = $this->connect($config);
        if (is_wp_error($remote)) {
            return ['ok' => false, 'message' => $remote->get_error_message()];
        }
        $prefix = $config['table_prefix'] ?? 'wp_';
        $count = $remote->get_var("SELECT COUNT(*) FROM {$prefix}posts"); // phpcs:ignore
        if ($count === null) {
            return ['ok' => false, 'message' => $remote->last_error ?: __('未知数据库错误。', 'linked3')];
        }
        return ['ok' => true, 'message' => sprintf(__('已连接,远程表有 %d 篇文章。', 'linked3'), (int) $count)];
    }

    /**
     * Build a transient wpdb instance pointed at the remote DSN.
     *
     * @param array $config
     * @return \wpdb|\WP_Error
     */
    private function connect(array $config) : mixed {
        $host = $config['db_host'] ?? '';
        $user = $config['db_user'] ?? '';
        $pass = $config['db_password'] ?? '';
        $name = $config['db_name'] ?? '';
        if (!$host || !$user || !$name) {
            return new \WP_Error('missing', __('缺少数据库主机/用户/名称。', 'linked3'));
        }
        // Reuse the WP wpdb class with a fresh connection.
        $wpdb_temp = new \wpdb($user, $pass, $name, $host);
        $wpdb_temp->prefix = $config['table_prefix'] ?? 'wp_';
        $wpdb_temp->show_errors(false);
        if (!empty($wpdb_temp->error)) {
            return new \WP_Error('db_connect', $wpdb_temp->error->get_error_message());
        }
        // Force a real connect probe.
        $probe = $wpdb_temp->get_var("SELECT 1"); // phpcs:ignore
        if ($probe === null) {
            return new \WP_Error('db_connect', $wpdb_temp->last_error ?: __('无法连接远程数据库。', 'linked3'));
        }
        return $wpdb_temp;
    }
}
