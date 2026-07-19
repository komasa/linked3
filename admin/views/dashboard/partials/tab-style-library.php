<?php
/**
 * [DEPRECATED v11.7.1] 风格库独立Tab已移除
 *
 * v11.7.1 架构审计修复:
 *   - 风格库独立Tab与视觉生态·图示脚本功能同质冗余, 已移除
 *   - 风格库功能(视图过滤 + AI推荐)已融合至 tab-charts.php 生成配置区域
 *   - 本文件保留为废弃存根, 防止旧URL直接访问404
 *
 * 重定向目标: 视觉生态 → 图示脚本 → Stage 2 生成配置 → 风格库融合面板
 *
 * 如需查看融合后的实现, 请访问:
 *   admin/views/dashboard/partials/tab-charts.php (搜索 "v11.7.1: 风格库融合面板")
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @deprecated since v11.7.1
 * @see tab-charts.php
 */

if (!defined('ABSPATH')) exit;

// 废弃存根: 显示重定向提示
?>
<div style="padding: 40px; text-align: center; background: #FAFAFA; border-radius: 8px; margin: 20px;">
    <div style="font-size: 48px; margin-bottom: 16px;">🎨→✍️</div>
    <h2 style="color: #18181B; margin-bottom: 12px;">风格库已融合至创作中心</h2>
    <p style="color: #71717A; max-width: 500px; margin: 0 auto 20px; line-height: 1.6;">
        v11.7.1 架构优化: 风格库独立Tab已移除, 视图过滤与AI推荐功能已有机融合至<br>
        <strong>创作中心 → 视觉生态 → 图示脚本 → 生成配置</strong> 区域。<br>
        此举消除了功能同质冗余, 降低了用户在Tab间的切换成本。
    </p>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=charts')); ?>"
       style="display: inline-block; padding: 10px 24px; background: #0F172A; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
        → 前往图示脚本使用风格库
    </a>
</div>
