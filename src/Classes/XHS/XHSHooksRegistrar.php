<?php

declare(strict_types=1);
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

final class XHSHooksRegistrar
{
    static function register(): void {
        // AJAX 处理器
        XHSAjaxActions::register();

        // 在 linked3/init 钩子中注册视觉脚本生成器
        add_action('linked3/init', [__CLASS__, 'register_visual_generator'], 20);

        // v19.50.1: linked3_xhs_system_prompt 钩子由 MetaLever_Hooks_Registrar 统一注册
        // 不再需要在此处手动 add_filter
    }

    /**
     * 向视觉脚本注册表注册小红书生成器。
     */
    static function register_visual_generator($version): void {
        if (class_exists('Linked3\\Classes\\Visual\\VisualScriptRegistry')) {
            \Linked3\Classes\Visual\VisualScriptRegistry::register_generator(
                new XHSGenerator()
            );
        }
    }
}
