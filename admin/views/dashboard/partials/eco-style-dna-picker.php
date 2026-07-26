<?php
/**
 * 思想DNA选择器组件 v17.2 — 全写作入口共享
 *
 * 在生态协同/快速写作/长文写作/CSV批量 所有入口注入
 * 用法: <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>
 *
 * @package Linked3
 * @version 17.2.0
 */
if (!defined('ABSPATH')) exit;

// v17.2: 加载写作风格DNA — 兼容命名空间和直接类名
$writing_styles = [];
if (class_exists('Linked3\\Classes\\ContentWriter\\Prompt\\SystemInstructionBuilder')) {
    $writing_styles = \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder::get_style_options();
} elseif (class_exists('SystemInstructionBuilder')) {
    $writing_styles = SystemInstructionBuilder::get_style_options();
}

// v17.2: 兜底 — 即使类不存在也显示选择器(用硬编码选项)
if (empty($writing_styles)) {
    $writing_styles = [
        'renzhenfei' => __('🎭 任正非化 (危机意识+灰度哲学+熵减思维)', 'linked3'),
        'liuxiaopai' => __('🎭 刘小排化 (极简主义+用户直觉+反共识)', 'linked3'),
        'leijun' => __('🎭 雷军化 (性价比+用户参与+生态链)', 'linked3'),
        'zhangyiming' => __('🎭 张一鸣化 (算法思维+数据驱动+延迟满足)', 'linked3'),
        'luoxiang' => __('🎭 罗翔化 (法理思辨+人文关怀+苏格拉底式)', 'linked3'),
        'wujinglian' => __('🎭 吴敬琏化 (市场逻辑+制度分析+历史纵深)', 'linked3'),
        'product_thinker' => __('📝 少楠·产品沉思录 (深度思考+克制表达)', 'linked3'),
        'vogue_editorial' => __('👗 VOGUE·奢侈品编辑 (优雅从容+感官描写)', 'linked3'),
        'data_journalism' => __('📊 FT·数据新闻 (数据驱动+图表叙事)', 'linked3'),
        'luoshen_poetic' => __('📜 洛神赋·古典华丽 (辞藻华丽+意象密集)', 'linked3'),
        'guoman_narrative' => __('🎨 国漫叙事·现代东方 (东方意境+江湖气韵)', 'linked3'),
    ];
}
?>
<!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
<div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;padding:12px;margin-bottom:12px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:14px;">🧬</span>
        <strong style="font-size:13px;color:#18181B;"><?php echo esc_html__('思想DNA注入', 'linked3'); ?></strong>
        <span style="font-size:11px;color:#71717A;"><?php echo esc_html__('— 选择大神级写作风格, 让文章有灵魂', 'linked3'); ?></span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select class="linked3-eco-select" id="lk3-style-dna" name="style_dna" style="flex:1;min-width:200px;">
            <option value=""><?php echo esc_html__('🤖 自动适配 (AI根据主题推断)', 'linked3'); ?></option>
            <?php foreach ($writing_styles as $sid => $slabel): ?>
                <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($slabel); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="linked3-eco-select" id="lk3-tone" name="tone" style="width:160px;" title="<?php echo esc_attr__('写作语气调性', 'linked3'); ?>">
            <optgroup label="商业写作">
                <option value="professional"><?php echo esc_html__('专业商务 (麦肯锡风)', 'linked3'); ?></option>
                <option value="consultative"><?php echo esc_html__('顾问式 (BCG风)', 'linked3'); ?></option>
                <option value="executive"><?php echo esc_html__('高管简报 (CEO视角)', 'linked3'); ?></option>
            </optgroup>
            <optgroup label="内容营销">
                <option value="marketing"><?php echo esc_html__('营销转化 (AIDA)', 'linked3'); ?></option>
                <option value="storytelling"><?php echo esc_html__('故事营销', 'linked3'); ?></option>
                <option value="social"><?php echo esc_html__('社交媒体 (小红书风)', 'linked3'); ?></option>
            </optgroup>
            <optgroup label="专业学术">
                <option value="academic"><?php echo esc_html__('学术论文 (Nature风)', 'linked3'); ?></option>
                <option value="technical"><?php echo esc_html__('技术文档 (GitHub风)', 'linked3'); ?></option>
                <option value="journalistic"><?php echo esc_html__('新闻深度 (FT风)', 'linked3'); ?></option>
            </optgroup>
            <optgroup label="创意表达">
                <option value="casual"><?php echo esc_html__('轻松随笔 (知乎风)', 'linked3'); ?></option>
                <option value="literary"><?php echo esc_html__('文学叙事', 'linked3'); ?></option>
                <option value="persuasive"><?php echo esc_html__('观点评论 (虎嗅风)', 'linked3'); ?></option>
            </optgroup>
        </select>
    </div>
    <!-- v17.2: 人类化可组合模块 (多选叠加) -->
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E4E4E7;">
        <div style="font-size:11px;font-weight:600;color:#3F3F46;margin-bottom:6px;"><?php echo esc_html__('🧬 人类化模块 (可多选叠加, 组合使用)', 'linked3'); ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g1" title="<?php echo esc_attr__('G1初代脱壳: 剥骨→破壁→绞杀→缝合', 'linked3'); ?>"> G1脱壳
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g2" title="<?php echo esc_attr__('G2重组变异: 倒装+断句+意象并置', 'linked3'); ?>"> G2变异
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g3" title="<?php echo esc_attr__('G3终极坍缩: 0%AI特征+100%人类混沌感', 'linked3'); ?>"> G3坍缩
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="emotion" title="<?php echo esc_attr__('情绪注入: 消除机械中立+注入极性偏见', 'linked3'); ?>"> 💉情绪注入
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="oral" title="<?php echo esc_attr__('口语盐化: 注入口语/自嘲/微观偏见', 'linked3'); ?>"> 🧂口语盐化
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="flaw" title="<?php echo esc_attr__('瑕疵植入: 故意漏冠词/介词/不完美表达', 'linked3'); ?>"> 🔧瑕疵植入
            </label>
        </div>
        <div style="font-size:10px;color:#A1A1AA;margin-top:4px;"><?php echo esc_html__('💡 勾选多个模块将组合执行。例如: G1+G2+G3 = 完整3代脱壳; 情绪+口语 = 日常人类感', 'linked3'); ?></div>
    </div>
</div>
<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-style-dna-picker.js ?>
