<?php
/**
 * Partial: genesis-progress
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== 进度向导条 ===== -->
<div class="lk3-wizard" id="lk3-wizard">
    <div class="lk3-wizard-step active" data-stage="0" onclick="lk3GoStage(0)">
        <span class="lk3-ws-num">0</span>
        <span class="lk3-ws-label"><?php echo esc_html__('🧬 SEED 中心', 'linked3'); ?></span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="1" onclick="lk3GoStage(1)">
        <span class="lk3-ws-num">1</span>
        <span class="lk3-ws-label"><?php echo esc_html__('📝 剧本输入', 'linked3'); ?></span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="2" onclick="lk3GoStage(2)">
        <span class="lk3-ws-num">2</span>
        <span class="lk3-ws-label"><?php echo esc_html__('⚙️ 生成配置', 'linked3'); ?></span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="3" onclick="lk3GoStage(3)">
        <span class="lk3-ws-num">3</span>
        <span class="lk3-ws-label"><?php echo esc_html__('🎬 生成执行', 'linked3'); ?></span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="4" onclick="lk3GoStage(4)">
        <span class="lk3-ws-num">4</span>
        <span class="lk3-ws-label"><?php echo esc_html__('✅ 质检导出', 'linked3'); ?></span>
    </div>
</div>

