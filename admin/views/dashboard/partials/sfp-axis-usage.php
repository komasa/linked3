<?php
/**
 * Partial: sfp-axis-usage
 * Extracted from: style-fusion-panel-v2.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- ===== 轴①: 按用途筛选 (F/Y/S/G 互斥分类) ===== -->
    <div class="lk3-sfp-v2-section">
        <div class="lk3-sfp-v2-section-title"><?php echo esc_html__('① 按用途筛选', 'linked3'); ?><span class="lk3-sfp-v2-axis-tag"><?php echo esc_html__('轴: 画风大类', 'linked3'); ?></span></div>
        <div class="lk3-sfp-v2-view-row">
            <button type="button" class="lk3-sfp-v2-view-btn lk3-sfp-v2-view-active" data-view="all"><?php echo esc_html__('全部 (71)', 'linked3'); ?></button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="infographic"><?php echo esc_html__('📐 信息图示 F01-F57 (57)', 'linked3'); ?></button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="illustration"><?php echo esc_html__('🎨 艺术插画 Y01-Y05 (5)', 'linked3'); ?></button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="photography"><?php echo esc_html__('📷 商业摄影 S01-S06 (6)', 'linked3'); ?></button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="concept"><?php echo esc_html__('🔬 概念实验 G01-G03 (3)', 'linked3'); ?></button>
        </div>
    </div>

