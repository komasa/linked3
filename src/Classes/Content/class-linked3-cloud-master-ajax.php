<?php
/**
 * Linked3 Cloud Template Master AJAX Handler v10.7.1
 *
 * 云模版总控AJAX endpoint:
 *   - linked3_cloud_fork: Fork母版到写作生态本地 (隔离修改)
 *   - linked3_cloud_preview: 预览母版
 *   - linked3_cloud_master_save: 保存/编辑自定义母版
 *   - linked3_cloud_master_delete: 删除自定义母版
 *   - linked3_cloud_fork_delete: 删除本地Fork副本
 *
 * 数据隔离:
 *   - linked3_cloud_master_templates: 母版库 (只读真源, 内置+自定义)
 *   - linked3_cloud_templates: 本地Fork副本 (写作生态可修改)
 *
 * @package Linked3\Content
 * @version 10.7.1
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class Linked3_Cloud_Master_Ajax {

    public static function register() : void {
        add_action('wp_ajax_linked3_cloud_fork', [__CLASS__, 'ajax_fork']);
        add_action('wp_ajax_linked3_cloud_preview', [__CLASS__, 'ajax_preview']);
        add_action('wp_ajax_linked3_cloud_master_save', [__CLASS__, 'ajax_master_save']);
        add_action('wp_ajax_linked3_cloud_master_delete', [__CLASS__, 'ajax_master_delete']);
        add_action('wp_ajax_linked3_cloud_fork_delete', [__CLASS__, 'ajax_fork_delete']);
        // v10.8.1: 双向同步
        add_action('wp_ajax_linked3_cloud_promote', [__CLASS__, 'ajax_promote_to_master']);
        add_action('wp_ajax_linked3_cloud_sync_to_local', [__CLASS__, 'ajax_sync_to_local']);

        if (function_exists('error_log')) {
            error_log('[linked3 v10.8.1] Cloud Master AJAX registered (7 endpoints)');
        }
    }

    /**
     * Fork母版到写作生态本地 (隔离修改, 不污染母版)
     */
    public static function ajax_fork() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $category = sanitize_key($_POST['category'] ?? '');
        $source = sanitize_key($_POST['source'] ?? 'builtin'); // builtin | custom
        $master_id = sanitize_text_field($_POST['master_id'] ?? '');

        if (empty($category)) wp_send_json_error(['message' => __('分类不能为空', 'linked3-ai')]);

        // 加载母版
        $master = null;
        if ($source === 'custom' && !empty($master_id)) {
            $custom_masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);
            $master = $custom_masters[$master_id] ?? null;
        } else {
            // 内置母版从工厂加载
            if (class_exists('\Linked3\Classes\Content\Linked3_Cloud_Template_Factory')) {
                try {
                    $factory = new \Linked3_Cloud_Template_Factory();
                    $master = $factory->load_template_by_category($category);
                    $master['name'] = $master['name'] ?? $category . '_default';
                } catch (\Throwable $e) {
                    wp_send_json_error(['message' => __('母版加载失败: ', 'linked3-ai') . $e->getMessage()]);
                }
            }
        }

        if (!$master) wp_send_json_error(['message' => __('母版不存在', 'linked3-ai')]);

        // 创建本地Fork副本 (隔离修改)
        $option_key = LINKED3_OPTION_PREFIX . 'cloud_templates';
        $local_forks = (array) get_option($option_key, []);

        $fork_id = 'fork_' . sanitize_title($master['name'] ?? $category) . '_' . wp_rand(1000, 9999);
        $local_forks[$fork_id] = [
            'id'            => $fork_id,
            'name'          => ($master['name'] ?? $category) . ' (本地副本)',
            'type'          => $category,
            'config'        => $master['config'] ?? $master,
            'source_master' => $source === 'custom' ? $master_id : 'builtin_' . $category,
            'forked_at'     => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
            'shared'        => false, // 本地副本, 非共享母版
        ];
        update_option($option_key, $local_forks, false);

        wp_send_json_success([
            'fork_id' => $fork_id,
            'message' => __('已Fork到写作生态本地: ', 'linked3-ai') . $local_forks[$fork_id]['name'],
        ]);
    }

    /**
     * 预览母版 (内置或自定义)
     */
    public static function ajax_preview() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $category = sanitize_key($_POST['category'] ?? '');
        $master_id = sanitize_text_field($_POST['master_id'] ?? '');

        // 自定义母版
        if (!empty($master_id)) {
            $custom_masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);
            if (isset($custom_masters[$master_id])) {
                wp_send_json_success(['template' => $custom_masters[$master_id]]);
            }
        }

        // 内置母版
        if (!empty($category) && class_exists('\Linked3\Classes\Content\Linked3_Cloud_Template_Factory')) {
            try {
                $factory = new \Linked3_Cloud_Template_Factory();
                $tpl = $factory->load_template_by_category($category);
                wp_send_json_success(['template' => $tpl]);
            } catch (\Throwable $e) {
                wp_send_json_error(['message' => __('加载失败: ', 'linked3-ai') . $e->getMessage()]);
            }
        }

        wp_send_json_error(['message' => __('母版不存在', 'linked3-ai')]);
    }

    /**
     * 保存/编辑自定义母版 (写入母版库, 跨生态可见)
     */
    public static function ajax_master_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $edit_id = sanitize_text_field($_POST['master_id'] ?? '');
        $template_json = wp_unslash($_POST['template'] ?? '');
        $template = json_decode($template_json, true);
        if (!is_array($template)) wp_send_json_error(['message' => __('数据格式错误', 'linked3-ai')]);

        $name = sanitize_text_field($template['name'] ?? '');
        $type = sanitize_key($template['type'] ?? 'content');
        if (empty($name)) wp_send_json_error(['message' => __('名称不能为空', 'linked3-ai')]);

        // 安全清洗10字段
        $config = [
            'profile'   => sanitize_textarea_field($template['config']['profile'] ?? ''),
            'role'      => sanitize_textarea_field($template['config']['role'] ?? ''),
            'scene'     => sanitize_textarea_field($template['config']['scene'] ?? ''),
            'background'=> sanitize_textarea_field($template['config']['background'] ?? ''),
            'goals'     => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['goals'] ?? ''))),
            'skills'    => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['skills'] ?? ''))),
            'style'     => sanitize_text_field($template['config']['style'] ?? ''),
            'limit'     => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['limit'] ?? ''))),
            'step'      => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['step'] ?? ''))),
            'output'    => sanitize_textarea_field($template['config']['output'] ?? ''),
        ];

        $option_key = LINKED3_OPTION_PREFIX . 'cloud_master_templates';
        $masters = (array) get_option($option_key, []);

        if (!empty($edit_id) && isset($masters[$edit_id])) {
            // 编辑现有
            $masters[$edit_id]['name'] = $name;
            $masters[$edit_id]['type'] = $type;
            $masters[$edit_id]['config'] = $config;
            $masters[$edit_id]['updated_at'] = current_time('mysql');
            $master_id = $edit_id;
            $msg = '母版已更新';
        } else {
            // 新增
            $master_id = 'master_' . sanitize_title($name) . '_' . wp_rand(1000, 9999);
            $masters[$master_id] = [
                'id'         => $master_id,
                'name'       => $name,
                'type'       => $type,
                'config'     => $config,
                'is_master'  => true, // 标记为母版
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            $msg = '自定义母版已添加到母版库';
        }

        update_option($option_key, $masters, false);
        wp_send_json_success(['master_id' => $master_id, 'message' => $msg]);
    }

    /**
     * 删除自定义母版 (内置母版不可删)
     */
    public static function ajax_master_delete() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $master_id = sanitize_text_field($_POST['master_id'] ?? '');
        if (empty($master_id)) wp_send_json_error(['message' => __('ID不能为空', 'linked3-ai')]);

        $option_key = LINKED3_OPTION_PREFIX . 'cloud_master_templates';
        $masters = (array) get_option($option_key, []);

        if (!isset($masters[$master_id])) wp_send_json_error(['message' => __('母版不存在', 'linked3-ai')]);
        if (!empty($masters[$master_id]['is_builtin'])) wp_send_json_error(['message' => __('内置母版不可删除', 'linked3-ai')]);

        unset($masters[$master_id]);
        update_option($option_key, $masters, false);

        wp_send_json_success(['message' => __('自定义母版已删除 (已Fork的本地副本不受影响)', 'linked3-ai')]);
    }

    /**
     * 删除本地Fork副本 (不影响母版)
     */
    public static function ajax_fork_delete() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $fork_id = sanitize_text_field($_POST['fork_id'] ?? '');
        if (empty($fork_id)) wp_send_json_error(['message' => __('ID不能为空', 'linked3-ai')]);

        $option_key = LINKED3_OPTION_PREFIX . 'cloud_templates';
        $forks = (array) get_option($option_key, []);

        if (!isset($forks[$fork_id])) wp_send_json_error(['message' => __('本地实例不存在', 'linked3-ai')]);

        unset($forks[$fork_id]);
        update_option($option_key, $forks, false);

        wp_send_json_success(['message' => __('本地实例已删除 (母版不受影响)', 'linked3-ai')]);
    }

    /**
     * v10.8.1 本地→生产同步: 将本地Fork副本提升为自定义母版
     * 场景: 用户在本地Fork上反复修改后, 觉得满意, 想收录到母版库供其他生态使用
     */
    public static function ajax_promote_to_master() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $fork_id = sanitize_text_field($_POST['fork_id'] ?? '');
        if (empty($fork_id)) wp_send_json_error(['message' => __('Fork ID不能为空', 'linked3-ai')]);

        $forks = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
        if (!isset($forks[$fork_id])) wp_send_json_error(['message' => __('本地实例不存在', 'linked3-ai')]);

        $fork = $forks[$fork_id];

        // 创建自定义母版 (不标记is_builtin, 可再编辑/删除)
        $master_id = 'master_' . sanitize_title($fork['name'] ?? 'promoted') . '_' . wp_rand(1000, 9999);
        $masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);
        $masters[$master_id] = [
            'id'          => $master_id,
            'name'        => ($fork['name'] ?? 'Promoted') . ' (收录)',
            'type'        => $fork['type'] ?? 'content',
            'config'      => $fork['config'] ?? $fork,
            'source_fork' => $fork_id,
            'promoted_at' => current_time('mysql'),
            'is_builtin'  => false,
        ];
        update_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', $masters, false);

        wp_send_json_success([
            'master_id' => $master_id,
            'message'   => __('已收录为自定义母版: ', 'linked3-ai') . $masters[$master_id]['name'],
        ]);
    }

    /**
     * v10.8.1 生产→本地同步: 将母版最新内容覆盖回本地Fork (重新Fork)
     * 场景: 母版被更新后, 本地Fork想拉取最新版
     */
    public static function ajax_sync_to_local() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $fork_id = sanitize_text_field($_POST['fork_id'] ?? '');
        if (empty($fork_id)) wp_send_json_error(['message' => __('Fork ID不能为空', 'linked3-ai')]);

        $forks = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
        if (!isset($forks[$fork_id])) wp_send_json_error(['message' => __('本地实例不存在', 'linked3-ai')]);

        $fork = $forks[$fork_id];
        $source_master = $fork['source_master'] ?? '';

        // 获取母版最新内容
        $master = null;
        if (strpos($source_master, 'builtin_') === 0) {
            $cat = substr($source_master, 8);
            if (class_exists('\Linked3\Classes\Content\Linked3_Cloud_Template_Factory')) {
                try {
                    $factory = new \Linked3_Cloud_Template_Factory();
                    $master = $factory->load_template_by_category($cat);
                } catch (\Throwable $e) {
                    wp_send_json_error(['message' => __('母版加载失败: ', 'linked3-ai') . $e->getMessage()]);
                }
            }
        } else {
            $masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);
            $master = $masters[$source_master] ?? null;
        }

        if (empty($master)) wp_send_json_error(['message' => __('源母版不存在', 'linked3-ai')]);

        // 覆盖本地Fork的config (保留fork_id/name/source_master)
        $forks[$fork_id]['config'] = $master['config'] ?? $master;
        $forks[$fork_id]['synced_at'] = current_time('mysql');
        $forks[$fork_id]['updated_at'] = current_time('mysql');
        update_option(LINKED3_OPTION_PREFIX . 'cloud_templates', $forks, false);

        wp_send_json_success([
            'fork_id' => $fork_id,
            'message' => __('本地实例已从母版同步最新内容', 'linked3-ai'),
        ]);
    }
}

// 注册
add_action('init', ['\Linked3\Classes\Content\Linked3_Cloud_Master_Ajax', 'register']);
