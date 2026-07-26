<?php
/**
 * Partial: cos-evolution
 * Extracted from: tab-cognitive-os.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ═══════════════════════════════════════════════════════════════
         STEP 1+2: 演化控制台
    ═══════════════════════════════════════════════════════════════ -->
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="background: #667eea; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px;">STEP 1+2</span>
            <h2 style="margin: 0; font-size: 16px; font-weight: 600; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🚀</span> 演化控制台
            </h2>
        </div>
        <p style="margin: 0 0 12px; font-size: 12px; color: #6b7280;">
            <strong><?php echo esc_html__('这是什么:', 'linked3'); ?></strong><?php echo esc_html__('输入一个认知问题, COS 自动运行 FP→EX→C→O→A 五部门流水线, 经历 G1→G2→G3 三代演化, 最终锁定最优方案 (MVP)。', 'linked3'); ?><br>
            <strong><?php echo esc_html__('怎么用:', 'linked3'); ?></strong> 填写问题描述 (越具体越好), 可选填领域, 点击"启动演化"。
        </p>
        <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end; margin-bottom: 12px;">
            <div>
                <label style="display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;"><?php echo esc_html__('问题描述', 'linked3'); ?><span style="color: #ef4444;">*</span></label>
                <input id="cos-problem-input" type="text" value="<?php echo esc_attr__('如何用AI做小红书电商选品', 'linked3'); ?>" placeholder="<?php echo esc_attr__('如: 如何写一篇高转化率的SEO文章', 'linked3'); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;"><?php echo esc_html__('领域 (可选)', 'linked3'); ?></label>
                <select id="cos-domain-input" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; background: #fff;">
                    <option value="ecommerce" selected><?php echo esc_html__('ecommerce · 电商/营销', 'linked3'); ?></option>
                    <option value="seo"><?php echo esc_html__('seo · SEO/搜索优化', 'linked3'); ?></option>
                    <option value="content"><?php echo esc_html__('content · 内容创作', 'linked3'); ?></option>
                    <option value="video"><?php echo esc_html__('video · 视频脚本', 'linked3'); ?></option>
                    <option value="business"><?php echo esc_html__('business · 商业策略', 'linked3'); ?></option>
                    <option value="tech"><?php echo esc_html__('tech · 技术工程', 'linked3'); ?></option>
                    <option value="general"><?php echo esc_html__('general · 通用', 'linked3'); ?></option>
                    <option value="__custom__"><?php echo esc_html__('✏️ 自定义...', 'linked3'); ?></option>
                </select>
                <input id="cos-domain-custom" type="text" placeholder="<?php echo esc_attr__('输入自定义领域 (如: education)', 'linked3'); ?>" style="display:none; width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; margin-top: 6px;">
            </div>
            <button id="cos-evolve-btn" type="button" style="background: #667eea; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                ▶ 启动演化
            </button>
        </div>
        <div style="font-size: 11px; color: #9ca3af; margin-bottom: 8px;">
            💡 <strong><?php echo esc_html__('提示:', 'linked3'); ?></strong> 好的问题格式 = "如何[动作][对象]以达到[目标]"。如: "如何设计小红书封面以提高点击率"
        </div>
        <div id="cos-evolve-result" style="display: none;"></div>
    </div>

