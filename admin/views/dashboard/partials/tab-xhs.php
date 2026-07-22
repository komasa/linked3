<?php
/**
 * Dashboard partial: 📕 小红书图文 v19.2
 *
 * @package Linked3
 */
if (!defined('ABSPATH')) exit;

// Include the XHS generator UI
$xhs_partial = __DIR__ . '/eco-xhs.php';
if (file_exists($xhs_partial)) {
    include $xhs_partial;
} else {
    echo '<div class="notice notice-error inline"><p>小红书图文模块文件缺失: eco-xhs.php</p></div>';
}
