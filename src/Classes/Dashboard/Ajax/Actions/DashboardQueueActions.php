<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard queue actions.
 *
 * Migrated from DashboardAjaxRegistrar (God Class) in G2.1.
 * Owns the 4 task-queue management AJAX endpoints.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 * @migrated   G2.1 (2026-07-18)
 */

class DashboardQueueActions extends DashboardBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_autogpt';
    const REQUIRED_CAP = 'manage_options';

    public static function register(): void {
        add_action('wp_ajax_linked3_queue_list', [__CLASS__, 'queue_list']);
        add_action('wp_ajax_linked3_queue_retry', [__CLASS__, 'queue_retry']);
        add_action('wp_ajax_linked3_queue_delete', [__CLASS__, 'queue_delete']);
        add_action('wp_ajax_linked3_queue_bulk_delete', [__CLASS__, 'queue_bulk_delete']);
    }

    /**
     * AJAX: List task queue items (latest 50).
     */
    public static function queue_list(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_task_queue';
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
        // v19.3.1: 白名单校验表名
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            wp_send_json_error(['message' => __('表名校验失败', 'linked3')], 500);
        }
        $where = $status ? $wpdb->prepare("WHERE status = %s", $status) : '';
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM %s %s ORDER BY added_at DESC LIMIT 50", $table, $where), ARRAY_A);
        // v3.1.0: 解析 payload
        if (is_array($items)) {
            foreach ($items as &$item) {
                $payload = json_decode($item['payload'] ?? '{}', true) ?: [];
                $item['payload_type']       = $payload['type'] ?? '';
                $item['payload_target_id']  = $payload['target_id'] ?? '';
                $item['payload_platform']   = $payload['platform'] ?? '';
                $item['payload_post_id']    = $payload['post_id'] ?? '';
                $item['payload_comment_id'] = $payload['comment_id'] ?? '';
                $item['payload_reason']     = $payload['reason'] ?? '';
                $item['payload'] = mb_substr($item['payload'] ?? '', 0, 200);
            }
        }
        wp_send_json_success(['items' => $items ?: []]);
    }

    /**
     * AJAX: Retry a failed task.
     */
    public static function queue_retry(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_task_queue';
        $id = (int) ($_POST['id'] ?? 0);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, error_message = %s WHERE id = %d",
            'pending', '', $id
        ));
        wp_send_json_success(['retried' => true]);
    }

    /**
     * AJAX: Delete a single task.
     */
    public static function queue_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_task_queue';
        $id = (int) ($_POST['id'] ?? 0);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE id = %d",
            $id
        ));
        wp_send_json_success(['deleted' => true]);
    }

    /**
     * AJAX: Bulk delete tasks by status (done/error/skipped).
     */
    public static function queue_bulk_delete(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_task_queue';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE status IN (%s, %s, %s)", "done", "error", "skipped"));
        wp_send_json_success(['deleted' => $wpdb->rows_affected]);
    }
}
