<?php
/**
 * Partial: cos-hero
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<div class="linked3-cos-dashboard" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px;">

    <!-- ═══════════════════════════════════════════════════════════════
         顶部 Hero — COS 系统总览 + 统计
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 28px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <div>
                <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">
                    🧠 认知操作系统 <span style="opacity: 0.7; font-size: 14px; font-weight: 400;">Cognitive OS v27.6</span>
                    <span id="cos-patch-badge" style="font-size: 10px; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; margin-left: 8px; cursor: help;" title="<?php echo esc_attr__('点击查看版本诊断', 'linked3'); ?>">检测中...</span>
                </h1>
                <p style="margin: 6px 0 0; opacity: 0.85; font-size: 13px;">
                    能够自行纠错的认知架构 — 演化验证过的方案, 直接应用到内容生成
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-skills" style="font-size: 22px; font-weight: 700;"><?php echo esc_html((string) ($cos_overview['skill_count'] ?? 0)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">Skills</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-evolutions" style="font-size: 22px; font-weight: 700;"><?php echo esc_html((string) ($cos_overview['evolution_count'] ?? 0)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;"><?php echo esc_html__('演化次数', 'linked3'); ?></div>
                </div>
                <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                    <div id="cos-stat-success-rate" style="font-size: 22px; font-weight: 700;"><?php echo esc_html(sprintf('%.0f%%', ($cos_overview['evolution_success_rate'] ?? 0) * 100)); ?></div>
                    <div style="font-size: 10px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;"><?php echo esc_html__('成功率', 'linked3'); ?></div>
                </div>
            </div>
        </div>
    </div>

