<?php
/**
 * Dashboard partial: 📤 分发中心 Hub v17.2.0
 *
 * G1 演化结晶 (5-Super-Tab 重组):
 *   公理β: 按用户意图分组 — "内容生成后去哪"统一归入分发中心
 *
 * 分发中心 Hub 结构:
 *   📤 分发中心 Tab
 *   ├── 📤 发布与采集 (di_sub=publish)    — 多目标发布 + URL采集改写
 *   ├── 🌐 社交分发   (di_sub=distribute) — 微博/小红书/Reddit等15+平台
 *   └── 📦 电商与表单 (di_sub=commerce)   — WooCommerce商品AI + AI表单
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-24
 */

if (!defined('ABSPATH')) {
    exit;
}

$di_sub = isset($_GET['di_sub']) ? sanitize_key($_GET['di_sub']) : 'publish';

$di_panels = [
    'publish'    => ['发布与采集', '📤', 'tab-publish.php'],
    'distribute' => ['社交分发',   '🌐', 'tab-distribute.php'],
    'commerce'   => ['电商与表单', '📦', 'tab-commerce.php'],
];

if (!isset($di_panels[$di_sub])) {
    $di_sub = 'publish';
}

$current_label = $di_panels[$di_sub][0];
$current_icon  = $di_panels[$di_sub][1];

// v11.4.0: 跨Hub面包屑 + 跳转栏
require_once __DIR__ . '/helper-hub-nav.php';
linked3_render_breadcrumb('distribution', $di_sub, $current_label);
linked3_render_hub_jumper('distribution');
?>

<h2><?php echo esc_html($current_icon); ?> 分发中心
    <span style="font-size:12px;color:#71717A;font-weight:normal;">v11.4.0 · 发布 + 社交 + 电商</span>
</h2>

<div class="notice notice-info inline"><p><strong>分发中心:</strong> 内容生成后的所有出口统一管理。发布到WordPress/微信公众号/百家号，同步到15+社交平台，或包装成WooCommerce商品和AI智能表单变现。</p></div>

<!-- Hub子面板切换 -->
<div class="linked3-eco-subtabs">
    <?php foreach ($di_panels as $slug => $meta) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=' . $slug)); ?>"
           class="linked3-eco-subtab <?php echo $di_sub === $slug ? 'active' : ''; ?>">
            <?php echo esc_html($meta[1] . ' ' . $meta[0]); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
$di_partial = __DIR__ . '/' . $di_panels[$di_sub][2];
if (file_exists($di_partial)) {
    try {
        include $di_partial;
    } catch (\Throwable $e) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
} else {
    echo '<div class="notice notice-error inline"><p>子面板文件缺失: ' . esc_html($di_panels[$di_sub][2]) . '</p></div>';
}
