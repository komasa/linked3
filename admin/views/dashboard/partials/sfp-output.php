<?php
/**
 * Partial: sfp-output
 * Extracted from: style-fusion-panel-v2.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
    <!-- 面板头: 统一标题 (去除冗余"AI"标签) -->
    <div class="lk3-sfp-v2-header">
        <strong class="lk3-sfp-v2-title"><?php echo esc_html__('🎨 画风风格库', 'linked3'); ?></strong>
        <span class="lk3-sfp-v2-meta">v2.0 · <?php echo esc_html($_panel_style_count); ?>风格(<?php echo $instance === 'genesis' ? 'S/Y/G漫画' : ($instance === 'charts' ? 'F图示' : 'F/Y/S/G全量'); ?>) × 9推荐策略</span>
    </div>

    <!-- ===== 输出区: 画风下拉 (v2.0 内嵌, 视觉绑定, 修复"看不见") ===== -->
    <div class="lk3-sfp-v2-output">
        <label class="lk3-sfp-v2-label" for="<?php echo esc_attr($style_select_id); ?>">
            🎨 当前画风 <span class="lk3-sfp-v2-label-hint"><?php echo esc_html__('(画面视觉基因 · 上方筛选/推荐的结果落点)', 'linked3'); ?></span>
        </label>
        <select id="<?php echo esc_attr($style_select_id); ?>" class="lk3-sfp-v2-select lk3-sfp-v2-style-select">
            <!-- v2.0: "自动适配"选项始终保留首位, 视图过滤不再清除 -->
            <option value="auto"><?php echo esc_html__('🤖 自动适配 (后端生成时推断最佳画风)', 'linked3'); ?></option>
            <?php if (!empty($panel_styles)): foreach ($panel_styles as $sid => $sname): ?>
                <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($sname); ?></option>
            <?php endforeach; endif; ?>
        </select>
        <div class="lk3-sfp-v2-hint"><?php echo esc_html__('💡 选"自动适配"=后端推断; 选具体风格=锁定视觉基因; 也可用下方筛选/推荐辅助决策', 'linked3'); ?></div>
    </div>

