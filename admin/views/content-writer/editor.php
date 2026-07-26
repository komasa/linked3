<?php
/**
 * Content Writer admin page — template editor + generate UI.
 *
 * @package Linked3
 * @subpackage Admin\Views\ContentWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $templates */
$templates = $templates ?? [];
$nonce = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap" id="linked3-content-writer">
    <h1><?php echo esc_html__('Linked3 内容写作', 'linked3'); ?></h1>

    <div class="linked3-cw-grid">
        <div class="linked3-cw-form">
            <h2><?php echo esc_html__('生成文章', 'linked3'); ?></h2>
            <p>
                <label><?php echo esc_html__('关键词', 'linked3'); ?>
                    <input type="text" id="linked3-cw-keyword" class="regular-text" />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('标题(可选)', 'linked3'); ?>
                    <input type="text" id="linked3-cw-title" class="regular-text" />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('模板', 'linked3'); ?>
                    <select id="linked3-cw-template">
                        <?php foreach ($templates as $tpl) : ?>
                            <option value="<?php echo esc_attr($tpl['id']); ?>" <?php echo $tpl['is_starter'] ? 'data-starter="1"' : ''; ?>>
                                <?php echo esc_html($tpl['template_name'] . ' (' . $tpl['template_type'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" id="linked3-cw-images" checked />
                    <?php echo esc_html__('插入图片', 'linked3'); ?>
                </label>
            </p>
            <p>
                <button type="button" class="button button-primary" id="linked3-cw-generate">
                    <?php echo esc_html__('生成', 'linked3'); ?>
                </button>
                <span class="spinner" id="linked3-cw-spinner"></span>
            </p>

            <h3><?php echo esc_html__('Quick Actions', 'linked3'); ?></h3>
            <p>
                <button type="button" class="button" id="linked3-cw-gen-title"><?php echo esc_html__('生成标题', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-meta"><?php echo esc_html__('Meta 描述', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-tags"><?php echo esc_html__('标签', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-excerpt"><?php echo esc_html__('摘要', 'linked3'); ?></button>
            </p>
        </div>

        <div class="linked3-cw-output">
            <h2><?php echo esc_html__('输出', 'linked3'); ?></h2>
            <div id="linked3-cw-result" class="linked3-cw-result">
                <p class="linked3-cw-placeholder"><?php echo esc_html__('生成的内容将显示在此。', 'linked3'); ?></p>
            </div>
            <p>
                <button type="button" class="button" id="linked3-cw-copy"><?php echo esc_html__('复制', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-new-post"><?php echo esc_html__('创建文章', 'linked3'); ?></button>
            </p>
        </div>
    </div>

    <?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-cw-editor.js ?>
