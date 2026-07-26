<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('linked3_collect');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;"><?php echo esc_html__('采集与改写', 'linked3'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard')); ?>" class="button">← 返回总览</a>
    </div>
    <div class="notice notice-info inline"><p><strong><?php echo esc_html__('功能说明:', 'linked3'); ?></strong><?php echo esc_html__('输入 URL 采集内容 → AI 改写(伪原创)→ 保存为草稿或直接发布。批量改写支持 SSE 流式进度。', 'linked3'); ?></p></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <h2><?php echo esc_html__('单个 URL 采集', 'linked3'); ?></h2>
            <p><input type="url" id="linked3-col-url" class="regular-text" placeholder="https://example.com/article" /></p>
            <p><button class="button" id="linked3-col-scrape"><?php echo esc_html__('采集', 'linked3'); ?></button></p>
            <h3><?php echo esc_html__('采集内容', 'linked3'); ?></h3>
            <textarea id="linked3-col-original" rows="12" class="large-text" style="width:100%;"></textarea>

            <h3><?php echo esc_html__('改写选项', 'linked3'); ?></h3>
            <p>
                <label>语气:
                    <select id="linked3-col-tone">
                        <option value="professional"><?php echo esc_html__('专业', 'linked3'); ?></option>
                        <option value="casual"><?php echo esc_html__('随意', 'linked3'); ?></option>
                        <option value="academic"><?php echo esc_html__('学术', 'linked3'); ?></option>
                        <option value="persuasive"><?php echo esc_html__('说服', 'linked3'); ?></option>
                        <option value="custom"><?php echo esc_html__('自定义提示词', 'linked3'); ?></option>
                    </select>
                </label>
                <label style="margin-left:15px;">复杂度:
                    <select id="linked3-col-complexity">
                        <option value="beginner"><?php echo esc_html__('入门', 'linked3'); ?></option>
                        <option value="intermediate" selected><?php echo esc_html__('中级', 'linked3'); ?></option>
                        <option value="expert"><?php echo esc_html__('专家', 'linked3'); ?></option>
                    </select>
                </label>
            </p>
            <p>
                <label><input type="checkbox" id="linked3-col-seo" checked /><?php echo esc_html__('SEO 优化', 'linked3'); ?></label>
                <label style="margin-left:15px;"><input type="checkbox" id="linked3-col-simplify" /><?php echo esc_html__('简化语言', 'linked3'); ?></label>
                <label style="margin-left:15px;">发布状态:
                    <select id="linked3-col-rewrite-status">
                        <option value=""><?php echo esc_html__('仅显示(不保存)', 'linked3'); ?></option>
                        <option value="draft"><?php echo esc_html__('保存为草稿', 'linked3'); ?></option>
                        <option value="publish"><?php echo esc_html__('直接发布', 'linked3'); ?></option>
                    </select>
                </label>
            </p>
            <div id="linked3-col-custom-prompt-box" style="display:none;">
                <p><label><?php echo esc_html__('自定义改写提示词:', 'linked3'); ?><br>
                    <textarea id="linked3-col-custom-prompt" rows="4" cols="60" class="large-text" style="width:100%;" placeholder="<?php echo esc_attr__('例如:请将以下内容改写为科技博客风格,加入代码示例,保持技术准确性。保留原文的核心观点,但用不同的表达方式。', 'linked3'); ?>"></textarea>
                </label></p>
                <p class="description">用 {content} 代表原文内容。留空则用默认改写逻辑。</p>
            </div>

            <!-- v3.2.0: 查看默认改写 prompt -->
            <details style="margin:10px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:10px;">
                <summary style="cursor:pointer;font-weight:600;color:#666;"><?php echo esc_html__('📝 查看默认改写 Prompt (点击展开)', 'linked3'); ?></summary>
                <div style="margin-top:10px;">
                    <p style="font-size:12px;color:#666;"><?php echo esc_html__('以下是默认改写 System Prompt (语气=专业, 复杂度=中级, SEO=开, 简化=关)。选择不同选项会动态调整。', 'linked3'); ?></p>
                    <pre style="white-space:pre-wrap;background:#fff;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:11px;">您是专业的文章改写器,改写用户提供的文章使其原创(通过查重),同时保留所有事实和含义。

语气:{tone}。 复杂度:{complexity}。

[SEO 优化:自然包含相关关键词,使用 H2/H3 小标题。]
[简化复杂句子,目标 8 年级阅读水平。]

仅输出 Markdown,不要前言。

User Prompt:
Rewrite the following article:

{original}</pre>
                    <p style="font-size:11px;color:#666;">{tone} = 语气选项 (专业/随意/学术/说服)<br>{complexity} = 复杂度选项 (入门/中级/专家)<br>{original} = 采集的原文内容<br><?php echo esc_html__('[...] 方括号段落是可选的,根据开关动态拼接', 'linked3'); ?></p>
                </div>
            </details>
            <p><button class="button button-primary" id="linked3-col-rewrite"><?php echo esc_html__('AI 改写', 'linked3'); ?></button></p>
        </div>
        <div>
            <h2><?php echo esc_html__('批量改写 (SSE 流式进度)', 'linked3'); ?></h2>
            <p><?php echo esc_html__('每行一个 URL,最多 20 个。逐条采集+改写,实时显示进度。', 'linked3'); ?></p>
            <p><textarea id="linked3-col-bulk-urls" rows="6" class="large-text" style="width:100%;" placeholder="<?php echo esc_attr__('每行一个 URL (最多 20 个)', 'linked3'); ?>"></textarea></p>
            <p>
                <label>发布状态:
                    <select id="linked3-col-status">
                        <option value="draft"><?php echo esc_html__('草稿(推荐,人工审核后发布)', 'linked3'); ?></option>
                        <option value="publish"><?php echo esc_html__('直接发布', 'linked3'); ?></option>
                    </select>
                </label>
                <button class="button" id="linked3-col-bulk"><?php echo esc_html__('开始批量', 'linked3'); ?></button>
            </p>
            <div id="linked3-col-bulk-log" style="background:#fff;border:1px solid #ddd;padding:10px;height:300px;overflow:auto;font-family:monospace;font-size:12px;"></div>
        </div>
    </div>

    <h2><?php echo esc_html__('改写结果', 'linked3'); ?></h2>
    <div id="linked3-col-output" style="background:#fff;border:1px solid #ddd;padding:12px;min-height:200px;">
        <p style="color:#999;"><?php echo esc_html__('改写后的内容将显示在此。', 'linked3'); ?></p>
    </div>

    <?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-collect-rewriter.js ?>
</div>
