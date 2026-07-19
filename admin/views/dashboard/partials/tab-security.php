<?php
/**
 * Dashboard partial: security tab.
 *
 * Extracted from tabs.php in v4.4.1 to keep the router file under
 * 100 lines. Each partial owns its own HTML fragment and is
 * included by tabs.php inside the .linked3-tab-content wrapper.
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

                echo '<h2>安全审计</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>扫描所有 AJAX 端点,检查 nonce/capability/匿名访问。红色端点需手动检查。</p></div>';
                // 内联显示审计结果
                if (class_exists('\\Linked3\\Classes\\Security\\Linked3_Ajax_Auditor')) {
                    $auditor = new \Linked3\Classes\Security\Linked3_Ajax_Auditor();
                    $rows = $auditor->scan();
                    $summary = $auditor->summary();
                    echo '<p>总端点: ' . (int)$summary['total_count'] . ' | 匿名(nopriv): ' . (int)$summary['nopriv_count'] . '</p>';
                    echo '<table class="widefat striped"><thead><tr><th>端点</th><th>匿名?</th><th>回调</th></tr></thead><tbody>';
                    foreach ($rows as $r) {
                        $bg = $r['is_nopriv'] ? ' style="background:#FEF2F2;"' : '';
                        echo '<tr' . $bg . '><td><code>' . esc_html($r['endpoint']) . '</code></td><td>' . ($r['is_nopriv'] ? '⚠ 是' : '✓ 否') . '</td><td><code>' . esc_html($r['callback']) . '</code></td></tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    // v11.3.6: Auditor类未加载时给出引导
                    echo '<div class="notice notice-error inline"><p>⚠️ 安全审计模块未加载。可能原因:插件文件不完整。请重新安装插件。</p></div>';
                }
