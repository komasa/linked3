<?php
/**
 * Dashboard Hub Helper v11.4.0 — 跨Hub面包屑 + 跳转栏
 *
 * G3方案①: 增强5大Hub间联系, 打破模块孤岛
 *   - render_breadcrumb(): 面包屑导航 (总览 > Hub > 子面板)
 *   - render_hub_jumper():  Hub间快速跳转栏 (工作流引导)
 *
 * 公理ε: 跨Hub互链密度提升 — 每个Hub顶部+底部都有跨Hub出口
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard
 * @version 17.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 渲染面包屑导航
 *
 * @param string $hub_slug    当前Hub slug (creation/distribution/automation/system)
 * @param string $sub_slug    当前子面板slug
 * @param string $sub_label   子面板显示名
 * @return void
 */
function linked3_render_breadcrumb($hub_slug = '', $sub_slug = '', $sub_label = '')
: void {
    $hub_labels = [
        'creation'     => '✍️ 创作中心',
        'distribution' => '📤 分发中心',
        'automation'   => '🤖 自动化',
        'system'       => '⚙️ 系统设置',
    ];

    $hub_label = $hub_labels[$hub_slug] ?? '';

    echo '<nav class="linked3-breadcrumb" style="font-size:12px;color:#71717A;margin:0 0 12px 0;padding:6px 0;" aria-label="面包屑">';
    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard')) . '" style="color:#71717A;text-decoration:none;">🏠 总览</a>';
    if (!empty($hub_label)) {
        echo ' <span style="margin:0 4px;">›</span> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $hub_slug)) . '" style="color:#2563eb;text-decoration:none;">' . esc_html($hub_label) . '</a>';
    }
    if (!empty($sub_label)) {
        echo ' <span style="margin:0 4px;">›</span> ';
        echo '<span style="color:#111827;font-weight:500;">' . esc_html($sub_label) . '</span>';
    }
    echo '</nav>';
}

/**
 * 渲染Hub间跳转栏 (工作流引导: 创作→分发→自动化→系统)
 *
 * @param string $current_hub 当前Hub slug (高亮当前)
 * @return void
 */
function linked3_render_hub_jumper($current_hub = '')
: void {
    $hubs = [
        'creation'     => ['✍️ 创作中心', '生成内容、图示、视频'],
        'distribution' => ['📤 分发中心', '发布到多平台'],
        'automation'   => ['🤖 自动化',   '定时Agent + AI对话'],
        'system'       => ['⚙️ 系统设置', 'API/SEO/授权/安全'],
    ];

    echo '<div class="linked3-hub-jumper" style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">';
    echo '<span style="font-size:11px;color:#9ca3af;align-self:center;margin-right:4px;">工作流:</span>';
    foreach ($hubs as $slug => $meta) {
        $is_current = ($slug === $current_hub);
        $style = $is_current
            ? 'background:#2563eb;color:#fff;border:1px solid #2563eb;'
            : 'background:#fff;color:#3F3F46;border:1px solid #d1d5db;';
        echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $slug)) . '" '
            . 'style="padding:4px 10px;border-radius:4px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:4px;' . $style . '" '
            . 'title="' . esc_attr($meta[1]) . '">';
        echo esc_html($meta[0]);
        echo '</a>';
        if (!$is_current) {
            echo '<span style="color:#9ca3af;align-self:center;font-size:10px;">→</span>';
        }
    }
    echo '</div>';
}
