<?php
/**
 * Dashboard partial: publish tab.
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

                echo '<h2>发布与采集</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong></p>';
                echo '<ul style="list-style:disc;margin-left:20px;">';
                echo '<li><strong>发布目标:</strong>配置多目标发布(本地/远程WP/远程DB/自定义API),文章生成后一键发布到多个站点。</li>';
                echo '<li><strong>采集与改写:</strong>输入 URL 采集内容 → AI 改写(语气/复杂度/SEO)→ 保存为草稿或直接发布。</li>';
                echo '<li><strong>批量改写:</strong>批量输入 URL,SSE 流式进度,逐条采集+改写。</li>';
                echo '</ul></div>';
                // 内联发布目标列表
                echo '<h3>发布目标</h3>';
                $repo = new \Linked3\Classes\Publish\PublishTargetRepository();
                $targets = $repo->all_for_user(get_current_user_id());
                if (empty($targets)) {
                    echo '<div style="text-align:center;padding:30px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:6px;margin:10px 0;">';
                    echo '<p style="font-size:14px;color:#71717A;margin:0 0 12px 0;">📤 暂无发布目标</p>';
                    echo '<p style="font-size:12px;color:#9ca3af;margin:0 0 16px 0;">配置发布目标后, 可一键将生成的文章发布到WordPress/微信公众号/百家号等平台。</p>';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-publish')) . '" class="button button-primary">+ 添加发布目标</a>';
                    echo '</div>';
                } else {
                    echo '<table class="widefat striped"><thead><tr><th>名称</th><th>类型</th><th>默认</th></tr></thead><tbody>';
                    foreach ($targets as $t) {
                        echo '<tr><td>' . esc_html($t['name']) . '</td><td><code>' . esc_html($t['type']) . '</code></td><td>' . ($t['is_default'] ? '✓' : '') . '</td></tr>';
                    }
                    echo '</tbody></table>';
                }
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=linked3-publish')) . '" class="button button-primary">管理发布目标</a> ';
                echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-collect')) . '" class="button">采集与改写</a></p>';
