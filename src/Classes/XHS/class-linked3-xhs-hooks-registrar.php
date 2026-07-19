<?php
/**
 * 小红书钩子注册器 — v19.2.
 *
 * @package Linked3
 * @subpackage Classes\XHS
 */

namespace Linked3\Classes\XHS;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_XHS_Hooks_Registrar
{
    public static function register()
    : void {
        // AJAX 处理器
        Linked3_XHS_Ajax_Actions::register();

        // 在 linked3/init 钩子中注册视觉脚本生成器
        add_action('linked3/init', [__CLASS__, 'register_visual_generator'], 20);

        // v19.50.1: linked3_xhs_system_prompt 钩子由 MetaLever_Hooks_Registrar 统一注册
        // 不再需要在此处手动 add_filter
    }

    /**
     * v19.40: 用元提示词杠杆增强 XHS 的 system_prompt.
     *
     * 绞杀模式：如果注册表中有适用于 xhs_generate 任务的杠杆，
     * 将杠杆的 system_prompt 拼接到基础 prompt 后面。
     */
    public static function enhance_with_meta_levers($base_prompt, $params) : mixed {
        if (class_exists('\\Linked3\\Classes\\MetaLever\\Linked3_Meta_Lever_Registry')) {
            return \Linked3\Classes\MetaLever\Linked3_Meta_Lever_Registry::enhance_prompt('xhs_generate', $base_prompt);
        }
        return $base_prompt;
    }

    /**
     * 向视觉脚本注册表注册小红书生成器。
     */
    public static function register_visual_generator($version)
    : void {
        if (class_exists('Linked3\\Classes\\Visual\\VisualScriptRegistry')) {
            \Linked3\Classes\Visual\VisualScriptRegistry::register_generator(
                new Linked3_XHS_Generator()
            );
        }
    }
}
