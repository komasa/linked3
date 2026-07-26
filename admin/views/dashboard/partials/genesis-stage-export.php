<?php
/**
 * Partial: genesis-stage-export
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== Stage 4: 质检与导出 (结果区延伸, 由 renderResult 动态填充) ===== -->
<div class="lk3-stage" id="lk3-stage-4" style="display:none;">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">✅</span><?php echo esc_html__('Stage 4 · 质检与导出', 'linked3'); ?></h3>
    </div>
    <p class="lk3-stage-desc"><?php echo esc_html__('PQS 13维质检报告 + 分镜预览 + 批量导出。不合格分镜可单独重新生成。', 'linked3'); ?></p>
    <div id="lk3-stage4-content"></div>
</div>

<!-- ===== SEED DNA 管理面板 (保留原有 ID, 默认隐藏) ===== -->
<div class="lk3-stage" id="linked3-genesis-seed-panel" style="display:none;">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🧬</span><?php echo esc_html__('新建 SEED DNA', 'linked3'); ?></h3>
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="document.getElementById('linked3-genesis-seed-panel').style.display='none';"><?php echo esc_html__('✕ 关闭', 'linked3'); ?></button>
    </div>
    <p class="lk3-stage-desc"><?php echo esc_html__('从剧本中提取角色/场景/色彩 DNA, 生成可复用的 SEED。也可手动创建。', 'linked3'); ?></p>
    <div class="lk3-form-grid" style="margin-bottom:12px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('SEED 名称', 'linked3'); ?></label>
            <input type="text" id="linked3-genesis-seed-name" class="lk3-form-control" placeholder="<?php echo esc_attr__('如: 林隐-主角', 'linked3'); ?>">
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('基于已有 SEED (可选)', 'linked3'); ?></label>
            <select id="linked3-genesis-seed-select" class="lk3-form-control">
                <option value=""><?php echo esc_html__('不使用 (全新创建)', 'linked3'); ?></option>
            </select>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <button type="button" class="lk3-btn lk3-btn-primary" id="linked3-genesis-seed-gen"><?php echo esc_html__('🧬 AI 提取 Seed DNA', 'linked3'); ?></button>
        <button type="button" class="lk3-btn" id="linked3-genesis-seed-export"><?php echo esc_html__('⬇️ 导出 JSON', 'linked3'); ?></button>
        <button type="button" class="lk3-btn lk3-btn-danger" id="linked3-genesis-seed-delete"><?php echo esc_html__('🗑️ 删除选中', 'linked3'); ?></button>
        <button type="button" class="lk3-btn" id="linked3-genesis-seed-refresh" style="display:none;"><?php echo esc_html__('↻ 刷新', 'linked3'); ?></button>
    </div>
    <div id="linked3-genesis-seed-result" style="font-size:12px;"></div>
</div>

</div><!-- /.lk3-genesis-wrap -->

<!-- ============================================================ -->
