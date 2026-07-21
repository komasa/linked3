<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Templates\TemplateManager;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard template actions.
 *
 * Migrated from DashboardAjaxRegistrar (God Class) in G2.1.
 * Owns the 4 template CRUD AJAX endpoints.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 * @migrated   G2.1 (2026-07-18)
 */

class DashboardTemplateActions extends DashboardBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_template';
    const REQUIRED_CAP = 'manage_options';

    public static function register()
    : void {
        add_action('wp_ajax_linked3_template_add', [__CLASS__, 'template_add']);
        add_action('wp_ajax_linked3_template_update', [__CLASS__, 'template_update']);
        add_action('wp_ajax_linked3_template_delete', [__CLASS__, 'template_delete']);
        add_action('wp_ajax_linked3_template_get', [__CLASS__, 'template_get']);
    }

    /**
     * AJAX: 添加模板
     * Action: wp_ajax_linked3_template_add
     */
    public static function template_add()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $mgr = new \Linked3\Classes\Templates\TemplateManager();
        $config = [
            'tone'      => sanitize_text_field(wp_unslash($_POST['tone'] ?? 'professional')),
            'complexity'=> sanitize_text_field(wp_unslash($_POST['complexity'] ?? 'intermediate')),
            'word_count'=> (int) ($_POST['word_count'] ?? 1200),
            'sections'  => array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['sections'] ?? ''))))),
        ];
        $mgr->add(
            sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['type'] ?? 'article')),
            $config
        );
        wp_send_json_success(['added' => true]);
    }

    /**
     * AJAX: 更新模板
     * Action: wp_ajax_linked3_template_update
     */
    public static function template_update()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $mgr = new \Linked3\Classes\Templates\TemplateManager();
        $config = [
            'tone'      => sanitize_text_field(wp_unslash($_POST['tone'] ?? 'professional')),
            'complexity'=> sanitize_text_field(wp_unslash($_POST['complexity'] ?? 'intermediate')),
            'word_count'=> (int) ($_POST['word_count'] ?? 1200),
            'sections'  => array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['sections'] ?? ''))))),
        ];
        $ok = $mgr->update(
            (int) ($_POST['index'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['type'] ?? 'article')),
            $config
        );
        wp_send_json_success(['updated' => $ok]);
    }

    /**
     * AJAX: 删除模板
     * Action: wp_ajax_linked3_template_delete
     */
    public static function template_delete()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $mgr = new \Linked3\Classes\Templates\TemplateManager();
        $ok = $mgr->delete((int) ($_POST['index'] ?? 0));
        wp_send_json_success(['deleted' => $ok]);
    }

    /**
     * AJAX: 获取单个模板
     * Action: wp_ajax_linked3_template_get
     */
    public static function template_get()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $idx = (int) ($_POST['index'] ?? 0);
        $mgr = new \Linked3\Classes\Templates\TemplateManager();
        // v2.9.0: 支持 get_all() 完整索引 (内置 + 自定义)
        // 若 index 在自定义范围内 (>= 内置数量),用 get_custom 取
        // 否则用 get_all() 取全局索引
        $all = $mgr->get_all();
        if (isset($all[$idx])) {
            wp_send_json_success(['template' => $all[$idx]]);
        }
        // 旧式调用: index 是自定义索引
        $tpl = $mgr->get_custom($idx);
        if (!$tpl) {
            wp_send_json_error(['message' => __('模板未找到', 'linked3')], 404);
        }
        wp_send_json_success(['template' => $tpl]);
    }
}
