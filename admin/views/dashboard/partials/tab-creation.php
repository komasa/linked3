<?php
/**
 * Dashboard partial: ✍️ 创作中心 Hub v17.2.0
 *
 * G1 演化结晶 (5-Super-Tab 重组):
 *   公理α: 信息熵减 — 14顶层Tab坍缩为5，决策熵 3.81→2.32 bit
 *   公理β: 系统降维 — 按用户意图分组，不按代码模块分组
 *   公理γ: 搭积木 — 同类归一Tab，子面板像积木插入
 *
 * 创作中心 Hub 结构:
 *   ✍️ 创作中心 Tab
 *   ├── 🚀 写作生态 (cr_sub=ecosystem) — 1+4融合, 全功能链
 *   ├── 🎨 视觉生态 (cr_sub=visual)    — 图示/漫画/视频3合1
 *   └── ☁ 云模版   (cr_sub=cloud)      — 跨生态母版总控
 *
 * 路由策略:
 *   - Hub层用 cr_sub 路由到3个已整合的子Tab
 *   - 每个子Tab内部继续用自己的 eco_sub / vs_sub 路由子面板
 *   - 零业务代码改动: 仅include现有partial
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-24
 */

if (!defined('ABSPATH')) {
    exit;
}

$cr_sub = isset($_GET['cr_sub']) ? sanitize_key($_GET['cr_sub']) : 'ecosystem';

// Hub子面板注册表 (slug => [label, icon, color, desc, partial_file])
// v12.0: 增加色标+描述, 参照Linear/Notion的子导航视觉区分
$cr_panels = [
    'ecosystem' => ['写作生态', '🚀', '#0F172A', '关键词→模版→内容→图片→SEO→标题→摘要→改写', 'tab-ecosystem.php'],
    'visual'    => ['视觉生态', '🎨', '#7C3AED', '图示脚本 + 漫画脚本 + 视频脚本 + 小红书图文', 'tab-visual.php'],
    'cloud'     => ['云模版',   '☁',  '#059669', '跨生态母版总控 · Fork到本地', 'tab-cloud.php'],
    'seed'      => ['SEED DNA', '🧬', '#DB2777', '角色/场景/道具/风格 DNA库', 'tab-seed-dna.php'],
];

// 守卫: 未知cr_sub回退到默认
if (!isset($cr_panels[$cr_sub])) {
    $cr_sub = 'ecosystem';
}

$current_label = $cr_panels[$cr_sub][0];
$current_icon  = $cr_panels[$cr_sub][1];
$current_color = $cr_panels[$cr_sub][2] ?? '#0F172A';
$current_desc  = $cr_panels[$cr_sub][3] ?? '';

// v11.4.0: 跨Hub面包屑 + 跳转栏
require_once __DIR__ . '/helper-hub-nav.php';
linked3_render_breadcrumb('creation', $cr_sub, $current_label);
linked3_render_hub_jumper('creation');
?>

<!-- v12.0: 创作中心 — 参照Linear/Notion子导航视觉区分 -->
<div class="linked3-tab-breadcrumb" style="border-left-color:<?php echo esc_attr($current_color); ?>;">
    <span style="font-size:18px;line-height:1;"><?php echo esc_html($current_icon); ?></span>
    <div style="display:flex;flex-direction:column;gap:1px;">
        <strong style="font-size:14px;color:<?php echo esc_attr($current_color); ?>;font-weight:600;letter-spacing:-0.01em;"><?php echo esc_html($current_label); ?></strong>
        <span style="font-size:12px;color:#71717A;line-height:1.4;"><?php echo esc_html($current_desc); ?></span>
    </div>
</div>

<!-- Hub子面板切换 (v12.0: 色标+图标+描述) -->
<div class="linked3-eco-subtabs">
    <?php foreach ($cr_panels as $slug => $meta) :
        $is_active = ($cr_sub === $slug);
        $m_color = $meta[2] ?? '#0F172A';
    ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=' . $slug)); ?>"
           class="linked3-eco-subtab <?php echo $is_active ? 'active' : ''; ?>"
           style="<?php echo $is_active ? 'border-bottom-color:' . esc_attr($m_color) . ';color:' . esc_attr($m_color) . ';' : ''; ?>">
            <?php echo esc_html($meta[1] . ' ' . $meta[0]); ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- v11.9.1: 长尾词库快捷入口 (v12.0: 极简化) -->
<?php
$saved_tail_count = count((array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []));
if ($saved_tail_count > 0) :
?>
<div style="margin:0 0 12px 0;padding:8px 12px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="font-size:13px;">📋</span>
    <strong style="font-size:12px;color:#3F3F46;">长尾词库:</strong>
    <span style="font-size:12px;color:#52525B;font-variant-numeric:tabular-nums;"><?php echo (int)$saved_tail_count; ?> 个词</span>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=keywords')); ?>" style="font-size:12px;color:#0F172A;text-decoration:none;">→ 管理</a>
</div>
<?php endif; ?>

<?php
// 路由到子Tab partial (子Tab内部继续处理自己的子面板)
$cr_partial = __DIR__ . '/' . $cr_panels[$cr_sub][4];
if (file_exists($cr_partial)) {
    try {
        include $cr_partial;
    } catch (\Throwable $e) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($e->getMessage()) . '</p></div>';
    } catch (\Throwable $e) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
} else {
    echo '<div class="notice notice-error inline"><p>子面板文件缺失: ' . esc_html($cr_panels[$cr_sub][2]) . '</p></div>';
}
