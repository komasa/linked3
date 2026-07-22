<?php

declare(strict_types=1);
/**
 * Seed Admin — AJAX Handlers (G4.1 split from SeedAdmin).
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @since      27.5.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SeedAdminAjax
{
    static function ajax_save_seed(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('无效的 nonce', 'linked3')], 403);
        }

        $data = self::collect_seed_data_from_request();

        // 简单校验
        if (empty($data['title'])) {
            wp_send_json_error(['message' => __('名称必填', 'linked3')], 400);
        }
        if (empty($data['seed_category'])) {
            wp_send_json_error(['message' => __('分类必填', 'linked3')], 400);
        }

        // 自动生成 seed_id (若空)
        if (empty($data['seed_id'])) {
            $prefix = strtoupper(substr($data['seed_category'], 0, 1));
            $data['seed_id'] = $prefix . rand(1000, 9999) . '_v1';
        }

        // 唯一性检查
        $existing = GenesisSeedCPT::get_by_seed_id($data['seed_id']);
        if ($existing && (empty($data['post_id']) || (int) $existing['post_id'] !== (int) $data['post_id'])) {
            wp_send_json_error(['message' => sprintf(__('Seed ID %s 已存在', 'linked3'), $data['seed_id'])], 409);
        }

        if (!empty($data['post_id'])) {
            // 编辑
            $post_id = (int) $data['post_id'];
            wp_update_post(['ID' => $post_id, 'post_title' => $data['title']]);
            GenesisSeedCPT::update_meta($post_id, $data);
        } else {
            // 新建
            $post_id = GenesisSeedCPT::create($data);
            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => $post_id->get_error_message()], 500);
            }
        }

        wp_send_json_success([
            'post_id' => $post_id,
            'seed_id' => $data['seed_id'],
            'message' => __('保存成功', 'linked3'),
        ]);
    }

    static function ajax_trash_all(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION_TRASH)) {
            wp_send_json_error(['message' => __('无效的 nonce', 'linked3')], 403);
        }
        $confirm = isset($_POST['confirm']) ? sanitize_text_field(wp_unslash($_POST['confirm'])) : '';
        if ($confirm !== 'CONFIRM') {
            wp_send_json_error(['message' => __('需输入 CONFIRM 确认', 'linked3')], 400);
        }

        $count = GenesisSeedCPT::trash_all();
        wp_send_json_success(['count' => $count, 'message' => sprintf(__('已软删除 %d 个 Seed', 'linked3'), $count)]);
    }

    static function ajax_download_seed(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('无效的 nonce', 'linked3')], 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $format  = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'md';
        if (!$post_id) {
            wp_send_json_error(['message' => __('缺少 post_id', 'linked3')], 400);
        }

        $seed = GenesisSeedCPT::get($post_id);
        if (!$seed) {
            wp_send_json_error(['message' => __('Seed 不存在', 'linked3')], 404);
        }

        if ($format === 'json') {
            $content = self::export_json($post_id);
        } else {
            $content = self::export_md($post_id);
        }

        // 返回 base64 让前端触发下载 (避免 admin-ajax 直接 stream 的复杂性)
        wp_send_json_success([
            'filename' => sprintf('seed-%s.%s', $seed['seed_id'] ?: $post_id, $format),
            'mime'     => $format === 'json' ? 'application/json' : 'text/markdown',
            'content'  => $content,
            'b64'      => base64_encode($content),
        ]);
    }

    static function ajax_export_batch(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('无效的 nonce', 'linked3')], 403);
        }

        $filter = [
            'category'   => isset($_POST['category']) ? sanitize_key($_POST['category']) : '',
            'project'    => isset($_POST['project']) ? sanitize_text_field(wp_unslash($_POST['project'])) : '',
            'post_ids'   => isset($_POST['post_ids']) ? array_map('absint', (array) $_POST['post_ids']) : [],
            'format'     => isset($_POST['format']) ? sanitize_key($_POST['format']) : 'md',
        ];

        $files = self::export_batch($filter);
        if (empty($files)) {
            wp_send_json_error(['message' => __('没有可导出的 Seed', 'linked3')], 404);
        }

        // 合并为单个文件并 base64 编码返回
        $format = $filter['format'];
        if ($format === 'json') {
            $merged = [];
            foreach ($files as $f) {
                if (pathinfo($f, PATHINFO_EXTENSION) === 'json') {
                    $merged[] = json_decode(file_get_contents($f), true);
                    @unlink($f);
                }
            }
            $content = wp_json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $filename = 'linked3-seeds-batch.json';
            $mime = 'application/json';
        } else {
            $content = '';
            foreach ($files as $f) {
                if (pathinfo($f, PATHINFO_EXTENSION) === 'md') {
                    $content .= file_get_contents($f) . "\n\n---\n\n";
                    @unlink($f);
                }
            }
            $filename = 'linked3-seeds-batch.md';
            $mime = 'text/markdown';
        }

        wp_send_json_success([
            'filename' => $filename,
            'mime'     => $mime,
            'content'  => $content,
            'b64'      => base64_encode($content),
            'count'    => count($files),
        ]);
    }

}
