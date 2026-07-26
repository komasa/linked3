<?php
/**
 * 本地模版子面板 v17.0 — 从云模版拉取母版, 隔离修改, 场景化使用
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的模版管理界面规范
 *   - 保留全部功能: Fork母版/本地模版列表/feicai4.0结构化10字段编辑器
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_tpl = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载本地Fork模版 (从云模版拉取的母版副本, 可修改)
$local_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);

// 加载云模版母版库 (用于Fork)
$cloud_masters = [];
if (class_exists('CloudTemplateFactory')) {
    $cloud_factory = new \CloudTemplateFactory();
    $cloud_categories = $cloud_factory->get_categories();
    foreach ($cloud_categories as $cat) {
        try {
            $tpl = $cloud_factory->load_template_by_category($cat);
            $cloud_masters[$cat] = $tpl;
        } catch (\Throwable $e) {}
    }
}
// 也加载自定义母版
$custom_masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);

$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('本地模版 — 从云模版拉取母版, 隔离修改', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:12px;">
        本地模版是云模版母版的<strong>场景化副本</strong>。从<a href="<?php echo esc_url($cloud_master_url); ?>">☁ 云模版总控</a>Fork母版后, 可自由修改, 不影响母版。模版含: Profile/Role/Scene/Background/Goals/Skills/Style/Limit/Step/Output
    </p>

    <!-- v17.2.0: 从云模版Fork母版 -->
    <div style="background:#FAFAFA;border:1px solid #0F172A;border-radius:4px;padding:10px;margin-bottom:12px;">
        <strong style="font-size:13px;color:#0F172A;"><?php echo esc_html__('☁ 从云模版拉取母版', 'linked3'); ?></strong>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;align-items:center;">
            <select class="linked3-eco-select" id="tpl-fork-source" style="flex:1;min-width:200px;">
                <option value=""><?php echo esc_html__('— 选择云模版母版 —', 'linked3'); ?></option>
                <?php foreach ($cloud_masters as $cat => $tpl): ?>
                    <option value="builtin:<?php echo esc_attr($cat); ?>">☁ <?php echo esc_html($tpl['name'] ?? $cat); ?> (内置母版)</option>
                <?php endforeach; ?>
                <?php foreach ($custom_masters as $mid => $tpl): ?>
                    <option value="custom:<?php echo esc_attr($mid); ?>">☁ <?php echo esc_html($tpl['name'] ?? $mid); ?> (自定义母版)</option>
                <?php endforeach; ?>
            </select>
            <button class="linked3-eco-btn" id="tpl-fork-btn"><?php echo esc_html__('📥 Fork到本地', 'linked3'); ?></button>
            <a href="<?php echo esc_url($cloud_master_url); ?>" class="linked3-eco-btn linked3-eco-btn-secondary" style="text-decoration:none;display:inline-block;line-height:28px;">管理母版 →</a>
        </div>
        <div style="font-size:11px;color:#71717A;margin-top:6px;"><?php echo esc_html__('💡 Fork后, 本地副本可自由修改, 母版保持不变 (场景隔离)', 'linked3'); ?></div>
    </div>

    <!-- 本地模版列表 -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
        <select class="linked3-eco-select" id="tpl-list" style="flex:1;min-width:200px;">
            <option value=""><?php echo esc_html__('— 选择本地模版 —', 'linked3'); ?></option>
            <?php foreach ($local_templates as $tid => $tpl): ?>
                <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($tpl['name'] ?? '未命名'); ?> (<?php echo esc_html($tpl['type'] ?? 'content'); ?>)<?php echo !empty($tpl['forked_from']) ? ' [Fork]' : ''; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="tpl-load"><?php echo esc_html__('加载', 'linked3'); ?></button>
        <button class="linked3-eco-btn" id="tpl-save"><?php echo esc_html__('保存', 'linked3'); ?></button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="tpl-delete" style="color:#DC2626;"><?php echo esc_html__('删除', 'linked3'); ?></button>
    </div>

    <!-- feicai4.0 10字段编辑器 -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Profile (作者/版本)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-profile" placeholder="<?php echo esc_attr__('如: Linked3 v10.7', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Role (角色定义)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-role" placeholder="<?php echo esc_attr__('如: 资深内容写手', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Scene (适用场景)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-scene" placeholder="<?php echo esc_attr__('如: 博客文章/公众号', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Background (背景)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-background" placeholder="<?php echo esc_attr__('如: 面向中文读者', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Goals (目标, 逗号分隔)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-goals" placeholder="<?php echo esc_attr__('如: 信息传递,SEO友好', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Skills (技能, 逗号分隔)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-skills" placeholder="<?php echo esc_attr__('如: 结构化写作,关键词布局', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Style (风格)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-style" placeholder="<?php echo esc_attr__('如: 专业但易懂', 'linked3'); ?>">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Limit (限制)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-limit" placeholder="<?php echo esc_attr__('如: 字数800-2000', 'linked3'); ?>">
        </div>
        <div style="grid-column:span 2;">
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Step (步骤, 逗号分隔)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-step" placeholder="<?php echo esc_attr__('如: 选题,大纲,撰写,质检', 'linked3'); ?>">
        </div>
        <div style="grid-column:span 2;">
            <label style="font-size:12px;color:#71717A;"><?php echo esc_html__('Output (输出格式)', 'linked3'); ?></label>
            <input type="text" class="linked3-eco-input" id="tpl-output" placeholder="<?php echo esc_attr__('如: Markdown, 含H1/H2/H3', 'linked3'); ?>">
        </div>
    </div>

    <div id="tpl-status" style="margin-top:12px;"></div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-templates.js ?>
