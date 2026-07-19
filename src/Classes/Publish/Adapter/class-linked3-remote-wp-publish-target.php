<?php
/**
 * Remote WordPress REST publish target — v3.0.0 完整版
 *
 * 修复点:
 *   1. 分类同步: 先 GET /wp/v2/categories?slug= 映射,不存在则 POST 创建
 *   2. 标签同步: 同分类
 *   3. 特色图上传: 先 POST /wp/v2/media 上传图片得到 ID,再设 featured_media
 *   4. remote_id 映射: 通过 Linked3_Publish_Remote_Id_Map 查表,重发=更新而非新建
 *   5. 字段补全: slug / author / date / excerpt
 *
 * @package Linked3
 * @subpackage Classes\Publish\Adapter
 */

namespace Linked3\Classes\Publish\Adapter;

use Linked3\Classes\Publish\Linked3_Publish_Target_Interface;
use Linked3\Includes\Http\Linked3_Safe_Remote;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Remote_WP_Publish_Target implements Linked3_Publish_Target_Interface
{
    public function type() : string { return 'remote_wp'; }
    public function label() : mixed { return __('远程 WordPress(REST)', 'linked3'); }

    public function publish(array $post, array $config)
    : array {
        $url = rtrim($config['site_url'] ?? '', '/');
        $user = $config['username'] ?? '';
        $app_pass = $config['app_password'] ?? '';
        if (!$url || !$user || !$app_pass) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少站点 URL/用户名/应用密码。', 'linked3'), 'response_code' => 400];
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        $auth = 'Basic ' . base64_encode($user . ':' . $app_pass);
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => $auth,
        ];

        // v3.0.0: 查 remote_id 映射表 (重发=更新)
        $remote_id = '';
        if (!empty($post['local_post_id']) && !empty($config['target_id'])) {
            $remote_id = $this->lookup_remote_id((int) $post['local_post_id'], (int) $config['target_id']);
        }
        // 兼容旧调用: post['remote_id'] 优先
        if (!empty($post['remote_id'])) {
            $remote_id = (string) $post['remote_id'];
        }

        $endpoint = $url . '/wp-json/wp/v2/posts';
        $body = [
            'title'   => $post['post_title'] ?? '',
            'content' => $post['post_content'] ?? '',
            'excerpt' => $post['post_excerpt'] ?? '',
            'status'  => $post['post_status'] ?? 'publish',
        ];
        // v3.0.0: 补全字段
        if (!empty($post['post_name'])) $body['slug'] = $post['post_name'];
        if (!empty($post['post_author'])) $body['author'] = (int) $post['post_author'];
        if (!empty($post['post_date'])) $body['date'] = $post['post_date'];

        // v3.0.0: 分类同步
        if (!empty($post['post_category_names'])) {
            $cat_ids = $this->sync_terms($url, $headers, $host, 'categories', $post['post_category_names']);
            if (!empty($cat_ids)) $body['categories'] = $cat_ids;
        } elseif (!empty($post['post_category'])) {
            // 如果直接传了分类 ID 数组(同站点假设)
            $body['categories'] = array_map('intval', (array) $post['post_category']);
        }

        // v3.0.0: 标签同步
        if (!empty($post['tags_input_names'])) {
            $tag_ids = $this->sync_terms($url, $headers, $host, 'tags', $post['tags_input_names']);
            if (!empty($tag_ids)) $body['tags'] = $tag_ids;
        } elseif (!empty($post['tags_input'])) {
            $body['tags'] = array_map('intval', (array) $post['tags_input']);
        }

        // v3.0.0: 特色图上传
        if (!empty($post['featured_image_url'])) {
            $media_id = $this->upload_media($url, $auth, $host, $post['featured_image_url'], $post['post_title'] ?? 'featured');
            if ($media_id) $body['featured_media'] = $media_id;
        }

        // POST (新建) 或 PUT (更新)
        if ($remote_id) {
            $endpoint .= '/' . (int) $remote_id;
            $resp = Linked3_Safe_Remote::put($endpoint, [
                'timeout' => 30,
                'headers' => $headers,
                'body'    => wp_json_encode($body),
                'allowed_hosts' => [$host],
            ]);
        } else {
            $resp = Linked3_Safe_Remote::post($endpoint, [
                'timeout' => 30,
                'headers' => $headers,
                'body'    => wp_json_encode($body),
                'allowed_hosts' => [$host],
            ]);
        }
        if (is_wp_error($resp)) {
            return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message(), 'response_code' => 0];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) {
            $msg = is_array($json) && isset($json['message']) ? $json['message'] : __('远程发布失败。', 'linked3');
            return ['ok' => false, 'remote_id' => '', 'message' => $msg, 'response_code' => $code];
        }
        $new_remote_id = (string) ($json['id'] ?? '');

        // v3.0.0: 保存 remote_id 映射
        if ($new_remote_id && !empty($post['local_post_id']) && !empty($config['target_id'])) {
            $this->store_remote_id((int) $post['local_post_id'], (int) $config['target_id'], $new_remote_id);
        }

        return ['ok' => true, 'remote_id' => $new_remote_id, 'message' => 'ok', 'response_code' => $code];
    }

    public function test(array $config)
    : array {
        $url = rtrim($config['site_url'] ?? '', '/');
        $user = $config['username'] ?? '';
        $app_pass = $config['app_password'] ?? '';
        if (!$url || !$user || !$app_pass) {
            return ['ok' => false, 'message' => __('缺少站点 URL/用户名/应用密码。', 'linked3')];
        }
        $auth = 'Basic ' . base64_encode($user . ':' . $app_pass);
        $resp = Linked3_Safe_Remote::get($url . '/wp-json/wp/v2/users/me', [
            'timeout' => 15,
            'headers' => ['Authorization' => $auth],
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return ['ok' => false, 'message' => sprintf(__('认证失败(HTTP %d)。', 'linked3'), $code)];
        }
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $name = $json['name'] ?? $json['slug'] ?? '';
        return ['ok' => true, 'message' => sprintf(__('连接正常 (用户: %s)。', 'linked3'), $name)];
    }

    /**
     * v3.0.0: 同步分类/标签 — 按 name 查找,不存在则创建。
     */
    private function sync_terms($url, $headers, $host, $taxonomy, $names) : mixed {
        $ids = [];
        foreach ((array) $names as $name) {
            $name = trim((string) $name);
            if (!$name) continue;
            // 按 slug 查找 (slug = sanitize_title)
            $slug = sanitize_title($name);
            $resp = Linked3_Safe_Remote::get($url . '/wp-json/wp/v2/' . $taxonomy . '?slug=' . urlencode($slug), [
                'timeout' => 10,
                'headers' => $headers,
                'allowed_hosts' => [$host],
            ]);
            if (is_wp_error($resp)) continue;
            $existing = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($existing) && !empty($existing[0]['id'])) {
                $ids[] = (int) $existing[0]['id'];
                continue;
            }
            // 不存在,创建
            $create_resp = Linked3_Safe_Remote::post($url . '/wp-json/wp/v2/' . $taxonomy, [
                'timeout' => 10,
                'headers' => $headers,
                'body' => wp_json_encode(['name' => $name]),
                'allowed_hosts' => [$host],
            ]);
            if (is_wp_error($create_resp)) continue;
            $created = json_decode(wp_remote_retrieve_body($create_resp), true);
            if (!empty($created['id'])) $ids[] = (int) $created['id'];
        }
        return $ids;
    }

    /**
     * v3.0.0: 上传媒体文件到远端 WP。
     */
    private function upload_media($url, $auth, $host, $image_url, $title = '') : void     {
        // 下载图片到临时文件
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) return 0;

        $ext = pathinfo(wp_parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'linked3-' . time() . '.' . $ext;
        $file = ['name' => $filename, 'tmp_name' => $tmp];

        // multipart 上传
        $boundary = wp_generate_password(24, false);
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . "\"\r\n";
        $body .= "Content-Type: " . $this->guess_mime($ext) . "\r\n\r\n";
        $body .= file_get_contents($tmp) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        @unlink($tmp);

        $resp = Linked3_Safe_Remote::post($url . '/wp-json/wp/v2/media', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => $auth,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ],
            'body' => $body,
            'allowed_hosts' => [$host],
        ]);
        if (is_wp_error($resp)) return 0;
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        return !empty($json['id']) ? (int) $json['id'] : 0;
    }

    private function guess_mime($ext) : mixed {
        $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        return $map[strtolower($ext)] ?? 'image/jpeg';
    }

    // ----- v3.0.0 remote_id 映射表 -----

    private function lookup_remote_id($local_post_id, $target_id) : mixed     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_publish_remote_id_map';
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT remote_id FROM {$table} WHERE local_post_id = %d AND target_id = %d LIMIT 1",
            $local_post_id, $target_id
        ));
        return $row ? (string) $row : '';
    }

    private function store_remote_id($local_post_id, $target_id, $remote_id)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_publish_remote_id_map';
        // UPSERT
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE local_post_id = %d AND target_id = %d LIMIT 1",
            $local_post_id, $target_id
        ));
        if ($exists) {
            $wpdb->update($table, ['remote_id' => $remote_id, 'updated_at' => current_time('mysql')], ['id' => $exists], ['%s', '%s'], ['%d']);
        } else {
            $wpdb->insert($table, [
                'local_post_id' => $local_post_id,
                'target_id'     => $target_id,
                'remote_id'     => $remote_id,
                'created_at'    => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s']);
        }
    }
}
