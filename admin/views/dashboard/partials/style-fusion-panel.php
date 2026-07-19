<?php
/**
 * [DEPRECATED v16.1.0] 风格库融合面板 v1.2 已升级为 v2.0
 *
 * v16.1.0 全插件举一反三审计修复:
 *   - v1.2 存在双按钮冗余(AI自动适配≈AI推荐)、视图过滤清空auto选项、硬编码DOM耦合
 *   - v2.0 已修复全部7处冲突, 三实例(charts/genesis/video)统一引用 v2.0
 *   - 本文件保留为废弃存根, 防止第三方代码 include 时 404
 *
 * 重定向策略: 自动 include v2.0, 透传全部参数, 保证向后兼容
 *
 * @package Linked3
 * @deprecated since v16.1.0
 * @see style-fusion-panel-v2.php
 */

if (!defined('ABSPATH')) exit;

// v16.1.0: 透传所有参数, 自动升级到 v2.0
// 调用方无需修改任何代码, include 本文件即自动获得 v2.0 能力
include __DIR__ . '/style-fusion-panel-v2.php';
