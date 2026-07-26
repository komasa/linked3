<?php
/**
 * 生态协同面板 v17.0 — 一键全流程
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的一键生产界面规范
 *   - 8模块全链路: 关键词→模版→内容→图片→SEO→标题→摘要→改写
 *   - 保留 feicai4.0 文案5阶段法进度可视化
 *   - 保留 v10.7.0 修复: 进度同步/内容预览/模版名称/HTML转义
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
// v17.2.0: 支持从URL参数预填主题 (跨子面板传递)
$eco_topic_preset = isset($_GET['topic']) ? sanitize_text_field($_GET['topic']) : '';
// v17.2.0: 云模版总控链接
$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');
// v11.8.0: 确保媒体库API可用 (图库导入功能依赖)
if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('⚡ 一键生态生产', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;"><?php echo esc_html__('输入主题, 自动完成8模块全链路: 关键词 → 模版 → 内容 → 图片 → SEO → 标题 → 摘要 → 改写', 'linked3'); ?></p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <input type="text" class="linked3-eco-input" id="eco-topic" placeholder="输入主题, 如: AI写作工具推荐" style="flex:1;min-width:300px;" value="<?php echo esc_attr($eco_topic_preset); ?>">
        <?php
        // v17.2.0 R6: 从长尾词库选题
        $saved_tail = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []);
        if (!empty($saved_tail)) :
        ?>
        <select class="linked3-eco-select" id="eco-tail-select" title="<?php echo esc_attr__('从长尾词库选择主题', 'linked3'); ?>">
            <option value=""><?php echo esc_html__('📋 从长尾词库选...', 'linked3'); ?></option>
            <?php foreach (array_slice($saved_tail, 0, 50) as $tail) : ?>
            <option value="<?php echo esc_attr($tail); ?>"><?php echo esc_html(mb_substr($tail, 0, 30)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select class="linked3-eco-select" id="eco-category">
            <option value="content"><?php echo esc_html__('内容模版', 'linked3'); ?></option>
            <option value="seo"><?php echo esc_html__('SEO模版', 'linked3'); ?></option>
            <option value="social"><?php echo esc_html__('社媒模版', 'linked3'); ?></option>
            <option value="video"><?php echo esc_html__('视频模版', 'linked3'); ?></option>
        </select>
        <button class="linked3-eco-btn" id="eco-run-all"><?php echo esc_html__('一键生态生产', 'linked3'); ?></button>
        <label style="font-size:12px;color:#52525B;display:flex;align-items:center;gap:4px;" title="<?php echo esc_attr__('勾选后: 生产完成→自动生图→自动组装保存草稿, 全流程无需手动', 'linked3'); ?>">
            <input type="checkbox" id="eco-auto-gen-images" checked> 🔄 自动生图+组装
        </label>
    </div>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <!-- feicai4.0 5阶段法进度 -->
    <div id="eco-phases" style="display:none;">
        <h4 style="font-size:13px;color:#3F3F46;margin-bottom:8px;"><?php echo esc_html__('feicai4.0 文案5阶段法', 'linked3'); ?></h4>
        <div style="display:flex;gap:4px;margin-bottom:12px;">
            <div class="linked3-eco-flow-step" id="phase-1"><?php echo esc_html__('① 上下文收集', 'linked3'); ?></div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-2"><?php echo esc_html__('② 简报锁定', 'linked3'); ?></div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-3"><?php echo esc_html__('③ 草稿生成', 'linked3'); ?></div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-4"><?php echo esc_html__('④ 自检', 'linked3'); ?></div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-5"><?php echo esc_html__('⑤ 交付', 'linked3'); ?></div>
        </div>
        <div class="linked3-eco-progress"><div class="linked3-eco-progress-bar" id="eco-bar"></div></div>
        <p style="margin-top:8px;color:#666;font-size:13px;" id="eco-status"><?php echo esc_html__('准备中...', 'linked3'); ?></p>
    </div>

    <div id="eco-result" style="margin-top:16px;"></div>
</div>

<!-- v16.1.0: 引入生态共享JS库 (收敛 escHtml/generateImages/saveDraft 重复定义) -->
<?php include __DIR__ . '/eco-shared-js.php'; ?>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-synergy.js ?>
