<?php
/**
 * 内容写作子面板 v17.0 — 全功能链整合 (快速/长文/CSV批量, feicai4.0 5阶段)
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的写作界面规范
 *   - 修复BUG: 写作配置桥接器布局错乱/模版来源/配图画风显示问题
 *   - 新增: HTML输出格式选择 (MD/HTML/纯文本)
 *   - 保留 feicai4.0 文案5阶段法进度可视化
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_cw = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
$cw_mode = isset($_GET['cw_mode']) ? sanitize_key($_GET['cw_mode']) : 'quick';
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('📝 内容写作 — feicai4.0文案5阶段法', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;"><?php echo esc_html__('5阶段: 上下文收集 → 简报锁定 → 草稿生成 → 自检 → 交付 · 支持快速/长文/CSV批量三种模式', 'linked3'); ?></p>

    <!-- 写作模式切换 (v17.0: 极简下划线式) -->
    <div class="linked3-eco-subtabs" style="margin-bottom:16px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=quick')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'quick' ? 'active' : ''; ?>">⚡ 快速写作</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=longform')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'longform' ? 'active' : ''; ?>">📚 长文写作</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=csv')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'csv' ? 'active' : ''; ?>">📊 CSV批量</a>
    </div>

    <?php if ($cw_mode === 'quick'): ?>
    <!-- 快速写作模式 -->

    <!-- v17.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风/输出格式) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" class="linked3-eco-input" id="cw-topic" placeholder="<?php echo esc_attr__('主题', 'linked3'); ?>" style="flex:1;min-width:200px;">
        <input type="text" class="linked3-eco-input" id="cw-keywords" placeholder="<?php echo esc_attr__('关键词(逗号分隔)', 'linked3'); ?>" style="flex:1;min-width:200px;">
        <?php
        // v11.5.0: 行业选择器 (P2) — 消费G3的50场景母版
        $p2_industries = [];
        if (class_exists('CloudTemplateFactory')) {
            try { $p2_industries = (new \CloudTemplateFactory())->get_industries(); } catch (\Throwable $e) {}
        }
        if (!empty($p2_industries)) :
        ?>
        <select class="linked3-eco-select" id="cw-industry" title="<?php echo esc_attr__('选择行业变体, AI将按行业调性生成', 'linked3'); ?>">
            <?php foreach ($p2_industries as $ind_slug => $ind_meta) : ?>
            <option value="<?php echo esc_attr($ind_slug); ?>"><?php echo esc_html($ind_meta['icon'] . ' ' . $ind_meta['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="number" class="linked3-eco-input" id="cw-wordcount" value="800" min="200" max="3000" style="width:80px;">
        <button class="linked3-eco-btn" id="cw-generate"><?php echo esc_html__('生成内容', 'linked3'); ?></button>
    </div>

    <!-- 5阶段进度 -->
    <div id="cw-phases" style="display:none;margin-bottom:12px;">
        <div style="display:flex;gap:4px;">
            <span class="linked3-eco-phase" data-phase="0"><?php echo esc_html__('① 上下文', 'linked3'); ?></span>
            <span class="linked3-eco-phase" data-phase="1"><?php echo esc_html__('② 简报', 'linked3'); ?></span>
            <span class="linked3-eco-phase" data-phase="2"><?php echo esc_html__('③ 草稿', 'linked3'); ?></span>
            <span class="linked3-eco-phase" data-phase="3"><?php echo esc_html__('④ 自检', 'linked3'); ?></span>
            <span class="linked3-eco-phase" data-phase="4"><?php echo esc_html__('⑤ 交付', 'linked3'); ?></span>
        </div>
    </div>

    <div id="cw-result"></div>

    <?php elseif ($cw_mode === 'longform'): ?>
    <!-- 长文写作模式 -->

    <!-- v1.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" class="linked3-eco-input" id="cw-lf-topic" placeholder="<?php echo esc_attr__('长文主题', 'linked3'); ?>" style="flex:1;min-width:200px;">
        <input type="text" class="linked3-eco-input" id="cw-lf-keywords" placeholder="<?php echo esc_attr__('关键词(逗号分隔)', 'linked3'); ?>" style="flex:1;min-width:200px;">
        <input type="number" class="linked3-eco-input" id="cw-lf-sections" value="5" min="2" max="20" style="width:80px;" title="<?php echo esc_attr__('段落数', 'linked3'); ?>">
        <span style="font-size:12px;color:#71717A;align-self:center;"><?php echo esc_html__('段', 'linked3'); ?></span>
        <input type="number" class="linked3-eco-input" id="cw-lf-words" value="3000" min="1000" max="20000" style="width:90px;" title="<?php echo esc_attr__('总字数', 'linked3'); ?>">
        <span style="font-size:12px;color:#71717A;align-self:center;"><?php echo esc_html__('字', 'linked3'); ?></span>
        <button class="linked3-eco-btn" id="cw-lf-outline"><?php echo esc_html__('生成大纲', 'linked3'); ?></button>
        <button class="linked3-eco-btn" id="cw-lf-generate" disabled><?php echo esc_html__('逐段生成', 'linked3'); ?></button>
        <!-- v16.0.25: 长文配图 + 保存草稿 (闭环) -->
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="cw-lf-gen-images" disabled title="<?php echo esc_attr__('为长文每段生成配图', 'linked3'); ?>">🎨 配图</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="cw-lf-save-draft" disabled><?php echo esc_html__('💾 保存草稿', 'linked3'); ?></button>
    </div>

    <div id="cw-lf-outline-result" style="margin-bottom:12px;"></div>
    <div id="cw-lf-sections-progress" style="display:none;margin-bottom:12px;"></div>
    <div id="cw-lf-result"></div>

    <?php else: ?>
    <!-- CSV批量模式 -->

    <!-- v1.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:4px;padding:10px;margin-bottom:12px;font-size:12px;color:#92400E;">
        💡 CSV批量模式: 上传含主题列表的CSV文件, 批量生成文章。支持以下格式:
    </div>

    <!-- v11.0.5 #6: CSV格式样稿说明 -->
    <details style="margin-bottom:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;">
        <summary style="cursor:pointer;font-weight:600;color:#3F3F46;font-size:13px;"><?php echo esc_html__('📋 CSV格式样稿 (点击展开查看/下载)', 'linked3'); ?></summary>
        <div style="margin-top:10px;font-size:12px;">
            <p style="color:#71717A;margin:0 0 8px 0;"><?php echo esc_html__('支持3种格式, 任选其一:', 'linked3'); ?></p>

            <p style="font-weight:600;color:#3F3F46;margin:8px 0 4px 0;"><?php echo esc_html__('格式1: 单列主题 (最简单)', 'linked3'); ?></p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">AI写作工具推荐
ChatGPT使用技巧
大模型微调教程
AI绘画提示词工程</pre>

            <p style="font-weight:600;color:#3F3F46;margin:12px 0 4px 0;"><?php echo esc_html__('格式2: 主题+关键词 (推荐)', 'linked3'); ?></p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">title,keywords
AI写作工具推荐,AI写作|内容生成|效率工具
ChatGPT使用技巧,ChatGPT|提示词|对话技巧
大模型微调教程,大模型|微调|LLM|训练</pre>

            <p style="font-weight:600;color:#3F3F46;margin:12px 0 4px 0;"><?php echo esc_html__('格式3: 完整字段 (主题+关键词+字数)', 'linked3'); ?></p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">title,keywords,word_count
AI写作工具推荐,AI写作|内容生成,800
ChatGPT使用技巧,ChatGPT|提示词,1200
大模型微调教程,大模型|微调,2000</pre>

            <p style="color:#71717A;margin:12px 0 4px 0;"><?php echo esc_html__('📝 也可使用纯TXT文件, 每行一个主题。', 'linked3'); ?></p>
            <button class="button button-small" id="cw-csv-download-sample" style="margin-top:4px;"><?php echo esc_html__('⬇ 下载样稿CSV', 'linked3'); ?></button>
        </div>
    </details>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <input type="file" id="cw-csv-file" accept=".csv,.txt" style="flex:1;min-width:200px;">
        <select class="linked3-eco-select" id="cw-csv-status">
            <option value="draft"><?php echo esc_html__('草稿', 'linked3'); ?></option>
            <option value="publish"><?php echo esc_html__('直接发布', 'linked3'); ?></option>
        </select>
        <button class="linked3-eco-btn" id="cw-csv-upload"><?php echo esc_html__('上传并预览', 'linked3'); ?></button>
        <button class="linked3-eco-btn" id="cw-csv-generate" disabled><?php echo esc_html__('批量生成', 'linked3'); ?></button>
    </div>
    <div id="cw-csv-preview" style="margin-bottom:12px;"></div>
    <div id="cw-csv-result"></div>
    <?php endif; ?>
</div>

<!-- v16.1.0: 引入生态共享JS库 (收敛 escHtml/generateImages/saveDraft 重复定义) -->
<?php include __DIR__ . '/eco-shared-js.php'; ?>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-content.js
// Pass partial-local $cw_mode to JS via inline script
wp_add_inline_script('linked3-eco-content', 'window.linked3_eco_content.cw_mode = ' . wp_json_encode($cw_mode ?? '') . ';', 'after');
?>
