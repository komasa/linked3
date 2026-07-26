<?php
/**
 * 写作配置桥接器 v17.0 — 统一本地模版/图片设置/画风库/输出格式到内容写作
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的配置面板规范
 *   - 修复BUG: 布局错乱/模版来源显示/配图画风下拉
 *   - 新增: HTML输出格式选择 (MD/HTML/纯文本)
 *   - 新增: 输出格式说明 (MD→HTML自动转换)
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;

// 加载本地模版列表
$bridge_local_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
// 加载图片设置
$bridge_img_settings = (array) get_option(LINKED3_OPTION_PREFIX . 'image_settings', []);
// 加载画风库 (复用视觉生态)
$bridge_styles = [];
if (class_exists('GenesisAtomIndex')) {
    $_idx = GenesisAtomIndex::instance();
    $_raw = $_idx->getStyles();
    if (isset($_raw['styles']) && is_array($_raw['styles'])) {
        foreach ($_raw['styles'] as $_sid => $_sinfo) {
            $bridge_styles[$_sid] = $_sinfo['name_cn'] ?? $_sinfo['name'] ?? $_sid;
        }
    }
}
$bridge_nonce = wp_create_nonce('linked3_content_writer');
$bridge_ajax_url = admin_url('admin-ajax.php');
?>

<!-- ===== 写作配置桥接器 v17.0 (统一模版/图片/画风/输出格式) ===== -->
<div class="linked3-eco-card" style="background:#FAFAFA;border:1px solid #E4E4E7;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:6px;margin-bottom:12px;">
        <span style="font-size:14px;">🔗</span>
        <span style="font-size:13px;font-weight:600;color:#18181B;"><?php echo esc_html__('写作配置桥接器', 'linked3'); ?></span>
        <span style="font-size:11px;color:#71717A;"><?php echo esc_html__('— 统一模版 / 图片 / 画风 / 输出格式', 'linked3'); ?></span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">

        <!-- ① 模版来源 -->
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📋 模版来源', 'linked3'); ?></label>
            <select class="linked3-eco-select" id="bridge-tpl-source">
                <option value="none"><?php echo esc_html__('不使用模版', 'linked3'); ?></option>
                <option value="local"><?php echo esc_html__('📁 本地模版', 'linked3'); ?></option>
                <option value="cloud"><?php echo esc_html__('☁ 云模版', 'linked3'); ?></option>
            </select>
        </div>

        <!-- ② 本地模版选择 (随来源联动) -->
        <div id="bridge-local-tpl-wrap" style="display:none;">
            <label class="lk3-form-label"><?php echo esc_html__('📁 选择本地模版', 'linked3'); ?></label>
            <select class="linked3-eco-select" id="bridge-local-tpl">
                <option value=""><?php echo esc_html__('— 选择 —', 'linked3'); ?></option>
                <?php foreach ($bridge_local_templates as $_tid => $_tpl): ?>
                    <option value="<?php echo esc_attr($_tid); ?>"><?php echo esc_html($_tpl['name'] ?? $_tid); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ③ 云模版选择 (随来源联动) -->
        <div id="bridge-cloud-tpl-wrap" style="display:none;">
            <label class="lk3-form-label"><?php echo esc_html__('☁ 选择云模版', 'linked3'); ?></label>
            <select class="linked3-eco-select" id="bridge-cloud-tpl">
                <option value=""><?php echo esc_html__('— 选择 —', 'linked3'); ?></option>
                <?php
                if (class_exists('CloudTemplateFactory')):
                    $_cloud_cats = ['content' => __('内容模版', 'linked3'), 'seo' => __('SEO模版', 'linked3'), 'social' => __('社媒模版', 'linked3')];
                    foreach ($_cloud_cats as $_cat => $_label):
                        echo '<option value="' . esc_attr($_cat) . '">' . esc_html('☁ ' . $_label) . '</option>';
                    endforeach;
                endif;
                ?>
            </select>
        </div>

        <!-- ④ 配图画风 -->
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🎨 配图画风', 'linked3'); ?><span style="color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(复用风格库)', 'linked3'); ?></span></label>
            <select class="linked3-eco-select" id="bridge-img-style" title="<?php echo esc_attr__('配图时注入此画风到图片生成prompt', 'linked3'); ?>">
                <option value="auto"><?php echo esc_html__('🤖 自动适配 (按文章调性推断)', 'linked3'); ?></option>
                <?php foreach ($bridge_styles as $_sid => $_sname): ?>
                    <option value="<?php echo esc_attr($_sid); ?>"><?php echo esc_html($_sname); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ⑤ 输出格式 (v17.0新增: MD/HTML/纯文本) -->
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📄 输出格式', 'linked3'); ?><span style="color:#A1A1AA;font-weight:normal;"><?php echo esc_html__('(发布时转换)', 'linked3'); ?></span></label>
            <select class="linked3-eco-select" id="bridge-output-format" title="<?php echo esc_attr__('选择内容输出的最终格式', 'linked3'); ?>">
                <option value="markdown"><?php echo esc_html__('📝 Markdown (默认, 写作时使用)', 'linked3'); ?></option>
                <option value="html"><?php echo esc_html__('🌐 HTML (发布时自动转换, 适配WordPress)', 'linked3'); ?></option>
                <option value="plaintext"><?php echo esc_html__('📄 纯文本 (去除所有格式)', 'linked3'); ?></option>
                <option value="markdown_html"><?php echo esc_html__('📝+🌐 MD+HTML (同时保存两种格式)', 'linked3'); ?></option>
            </select>
        </div>

    </div>

    <!-- ⑥ 图片设置摘要卡 -->
    <div style="margin-top:12px;padding:8px 12px;background:#FFFFFF;border-radius:4px;border:1px solid #E4E4E7;font-size:11px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
            <span style="color:#52525B;">
                📷 图片设置:
                <strong style="color:#3F3F46;">供应商</strong>=<span id="bridge-img-summary-provider"><?php echo esc_html($bridge_img_settings['provider'] ?? '万相'); ?></span> ·
                <strong style="color:#3F3F46;">分辨率</strong>=<span id="bridge-img-summary-res"><?php echo esc_html($bridge_img_settings['resolution'] ?? '1024x1024'); ?></span> ·
                <strong style="color:#3F3F46;">插入位置</strong>=<span id="bridge-img-summary-pos"><?php echo esc_html($bridge_img_settings['insert_position'] ?? '段间'); ?></span> ·
                <strong style="color:#3F3F46;">采集源</strong>=<span id="bridge-img-summary-src"><?php echo !empty($bridge_img_settings['image_site_url']) ? '已配置' : '未配置'; ?></span>
            </span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images')); ?>" style="color:#0F172A;font-size:11px;text-decoration:none;">→ 修改图片设置</a>
        </div>
    </div>

    <!-- ⑦ 输出格式说明 (v17.0新增) -->
    <div style="margin-top:8px;padding:8px 12px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:4px;font-size:11px;color:#92400E;">
        💡 <strong><?php echo esc_html__('输出格式说明:', 'linked3'); ?></strong> 写作过程始终使用Markdown格式 (便于AI生成与编辑)。
        发布时根据此处的格式选择自动转换: HTML格式适配WordPress文章编辑器, 纯文本去除所有格式标记, MD+HTML同时保存两种版本供不同平台使用。
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-config-bridge.js ?>
