<?php
/**
 * Partial: genesis-stage-execute
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== Stage 3: 生成执行 ===== -->
<div class="lk3-stage" id="lk3-stage-3">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🎬</span><?php echo esc_html__('Stage 3 · 生成执行', 'linked3'); ?></h3>
        <span class="spinner is-active" id="linked3-genesis-spinner" style="display:none;float:none;margin:0;"></span>
        <span id="linked3-genesis-status" style="font-size:12px;color:#71717A;margin-left:8px;"></span>
    </div>
    <p class="lk3-stage-desc"><?php echo esc_html__('确认配置后点击生成。系统将: Stage1 拆解剧本 → Stage2 批量生成 Prompt + PQS 质检。选中的 SEED 将自动注入每个 Prompt。', 'linked3'); ?></p>

    <!-- 配置摘要 -->
    <div style="background:#FAFAFA;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#52525B;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;">
            <div><?php echo esc_html__('🎨 风格:', 'linked3'); ?><strong id="lk3-summary-style">-</strong></div>
            <div><?php echo esc_html__('🖥️ 平台:', 'linked3'); ?><strong id="lk3-summary-platform">-</strong></div>
            <div><?php echo esc_html__('📊 分镜:', 'linked3'); ?><strong id="lk3-summary-panels">-</strong></div>
            <div>🧬 SEED: <strong id="lk3-summary-seeds">0</strong></div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;gap:12px;margin:20px 0;">
        <button type="button" class="lk3-btn lk3-btn-lg" onclick="lk3GoStage(2)"><?php echo esc_html__('← 上一步', 'linked3'); ?></button>
        <button type="button" class="lk3-btn lk3-btn-primary lk3-btn-lg" id="linked3-genesis-gen"><?php echo esc_html__('🎬 开始生成', 'linked3'); ?></button>
    </div>

    <!-- 结果区 (保留原有 ID) -->
    <div id="linked3-genesis-result" class="lk3-result-panel" style="min-height:60px;">
        <div style="text-align:center;color:#A1A1AA;padding:20px;font-size:13px;"><?php echo esc_html__('点击「开始生成」启动漫画脚本生成流程', 'linked3'); ?></div>
    </div>
</div>

