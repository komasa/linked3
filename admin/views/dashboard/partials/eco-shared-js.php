<?php
/**
 * 生态共享JS工具库 v1.0 — 收敛 eco-content/eco-synergy/eco-images 的重复AJAX逻辑
 *
 * ============================================================================
 * v16.1.0 全插件举一反三审计修复:
 *
 * [冲突] 同源AJAX多入口 — linked3_eco_generate_images / linked3_eco_save_draft
 *   在 eco-content.php 和 eco-synergy.php 各自独立实现 fetch+渲染, 代码重复约120行
 *   v1.0: 抽取为 Linked3EcoShared 命名空间, 两处共用
 *
 * [冲突] innerHTML='' 清空模式分散
 *   v1.x: 9处文件各自 innerHTML='', 部分有选项丢失风险
 *   v1.0: 提供 safeClear() 统一封装, 重建下拉时保留指定首选项
 *
 * [冲突] HTML转义函数重复
 *   v1.x: escHtml/escapeHtml 在 eco-content/eco-synergy/eco-images 各定义一遍
 *   v1.0: 统一为 Linked3EcoShared.escapeHtml()
 *
 * 用法 (在需要JS的partial末尾引入一次):
 *   <?php include __DIR__ . '/eco-shared-js.php'; ?>
 * ============================================================================
 */
if (!defined('ABSPATH')) exit;
// 本文件只输出 <?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-shared-js.js ?>
