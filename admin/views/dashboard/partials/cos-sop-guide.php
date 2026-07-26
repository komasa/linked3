<?php
/**
 * Partial: cos-sop-guide
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ═══════════════════════════════════════════════════════════════
         📋 使用 SOP — 5 步引导式工作流
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="margin: 0 0 12px; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">📋</span> 使用指南 — 5 步完整工作流
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: #f0f4ff; border-left: 3px solid #667eea; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #667eea; font-weight: 600; margin-bottom: 4px;">STEP 1 · 🎯</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('提出问题', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html__('在下方输入你要解决的认知问题', 'linked3'); ?></div>
            </div>
            <div style="background: #f0fdf4; border-left: 3px solid #10b981; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #10b981; font-weight: 600; margin-bottom: 4px;">STEP 2 · 🔄</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('启动演化', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html__('COS 自动运行三代演化, 锁定 MVP', 'linked3'); ?></div>
            </div>
            <div style="background: #fefce8; border-left: 3px solid #eab308; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #eab308; font-weight: 600; margin-bottom: 4px;">STEP 3 · 💎</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('查看 Skill', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html__('最优方案结晶为 Skill, 保存在下方', 'linked3'); ?></div>
            </div>
            <div style="background: #fef3c7; border-left: 3px solid #f59e0b; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #f59e0b; font-weight: 600; margin-bottom: 4px;">STEP 4 · 🚀</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('应用 Skill', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html__('点击"应用"生成 system_prompt', 'linked3'); ?></div>
            </div>
            <div style="background: #fdf2f8; border-left: 3px solid #ec4899; padding: 12px; border-radius: 6px;">
                <div style="font-size: 11px; color: #ec4899; font-weight: 600; margin-bottom: 4px;">STEP 5 · 🔗</div>
                <div style="font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 4px;"><?php echo esc_html__('杠杆链审查', 'linked3'); ?></div>
                <div style="font-size: 11px; color: #6b7280;"><?php echo esc_html__('可选: 对方案做深度认知审查', 'linked3'); ?></div>
            </div>
        </div>
    </div>

