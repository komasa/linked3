<?php
/**
 * Dashboard partial: 🎨 视觉生态 v17.2.0
 *
 * v17.2.0: 修复子面板链接路由 + 色彩区分三模块
 *
 * @package Linked3
 * @version 17.2.0
 */
if (!defined('ABSPATH')) exit;

$vs_sub = isset($_GET['vs_sub']) ? sanitize_key($_GET['vs_sub']) : 'charts';
$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');

// v12.0: 三模块色彩+描述定义 (参照Linear/Notion子导航)
$vs_panels = [
    'charts'  => ['label' => '📊 图示脚本', 'color' => '#0F172A', 'desc' => '8种结构信息图, SEED先行, 数据可视化'],
    'genesis' => ['label' => '🎨 漫画脚本', 'color' => '#7C3AED', 'desc' => '分镜+角色DNA, 多格漫画, 故事叙事'],
    'video'   => ['label' => '🎬 视频脚本', 'color' => '#EF4444', 'desc' => '9页SOP分镜, 旁白+画面, 短视频脚本'],
    'xhs'     => ['label' => '📕 小红书图文', 'color' => '#FF2E4D', 'desc' => '爆款图文笔记, 多页+配图提示词, V15品牌'],
];
if (!isset($vs_panels[$vs_sub])) $vs_sub = 'charts';
$current = $vs_panels[$vs_sub];
?>

<!-- v12.0: 视觉生态 — 参照Linear/Notion子导航视觉区分 -->
<div class="linked3-tab-breadcrumb" style="border-left-color:<?php echo esc_attr($current['color']); ?>;">
    <span style="font-size:18px;line-height:1;">🎨</span>
    <div style="display:flex;flex-direction:column;gap:1px;">
        <strong style="font-size:14px;color:<?php echo esc_attr($current['color']); ?>;font-weight:600;letter-spacing:-0.01em;">视觉生态</strong>
        <span style="font-size:12px;color:#71717A;line-height:1.4;"><?php echo esc_html($current['desc']); ?> · 消费 <a href="<?php echo esc_url($cloud_master_url); ?>" style="color:#0F172A;text-decoration:none;">☁ 云模版</a> 母版</span>
    </div>
</div>

<!-- v17.2.0: 子面板切换 (修复路由 + 色彩区分) -->
<div class="linked3-eco-subtabs">
    <?php foreach ($vs_panels as $slug => $meta) : ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=' . $slug)); ?>"
       class="linked3-eco-subtab <?php echo $vs_sub === $slug ? 'active' : ''; ?>"
       style="<?php echo $vs_sub === $slug ? 'border-left:3px solid ' . $meta['color'] . ';' : ''; ?>">
        <?php echo esc_html($meta['label']); ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- v17.2.0: 当前模块描述条 (色彩区分) -->
<div style="background:<?php echo esc_attr($current['color']); ?>0d;border-left:3px solid <?php echo esc_attr($current['color']); ?>;padding:10px 14px;border-radius:6px;margin:0 0 16px 0;">
    <strong style="color:<?php echo esc_attr($current['color']); ?>;"><?php echo esc_html($current['label']); ?></strong>
    <span style="color:#71717A;font-size:12px;margin-left:8px;"><?php echo esc_html($current['desc']); ?></span>
</div>

<?php
// 子面板路由
$vs_partial = __DIR__ . '/tab-' . $vs_sub . '.php';
if (file_exists($vs_partial)) {
    include $vs_partial;
} else {
    echo '<div class="notice notice-error inline"><p>未知子面板: ' . esc_html($vs_sub) . '</p></div>';
}
