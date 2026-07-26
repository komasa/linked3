<?php
/**
 * Partial: genesis-stage-input
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== Stage 1: 剧本输入 ===== -->
<div class="lk3-stage" id="lk3-stage-1">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">📝</span><?php echo esc_html__('Stage 1 · 剧本输入', 'linked3'); ?></h3>
        <div style="display:flex;gap:8px;">
            <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-test-btn"><?php echo esc_html__('🔌 测试连接', 'linked3'); ?></button>
            <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-diag-btn"><?php echo esc_html__('🔧 服务器诊断', 'linked3'); ?></button>
        </div>
    </div>
    <p class="lk3-stage-desc"><?php echo esc_html__('粘贴或输入剧本/故事文本。AI 将自动拆解场景、角色、情节点。建议至少 200 字以获得最佳效果。', 'linked3'); ?></p>
    <textarea id="linked3-genesis-script" class="lk3-form-control" rows="8" placeholder="<?php echo esc_attr__('在此输入剧本或故事...&#10;&#10;示例:&#10;林隐站在天台上, 雨水打湿了他的风衣。他低头看着手中的怀表, 指针停在 11:47。&#10;\"又迟了一步。\" 他喃喃自语, 将怀表收入口袋, 转身消失在雨幕中...', 'linked3'); ?>" style="font-size:13px;line-height:1.6;"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
        <span style="font-size:11px;color:#71717A;" id="lk3-script-stats"><?php echo esc_html__('0 字', 'linked3'); ?></span>
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="lk3GoStage(2)"><?php echo esc_html__('下一步: 生成配置 →', 'linked3'); ?></button>
    </div>
</div>

