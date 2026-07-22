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
        'renzhenfei' => '🎭 任正非化 (危机意识+灰度哲学+熵减思维)',
        'liuxiaopai' => '🎭 刘小排化 (极简主义+用户直觉+反共识)',
        'leijun' => '🎭 雷军化 (性价比+用户参与+生态链)',
        'zhangyiming' => '🎭 张一鸣化 (算法思维+数据驱动+延迟满足)',
        'luoxiang' => '🎭 罗翔化 (法理思辨+人文关怀+苏格拉底式)',
        'wujinglian' => '🎭 吴敬琏化 (市场逻辑+制度分析+历史纵深)',
        'product_thinker' => '📝 少楠·产品沉思录 (深度思考+克制表达)',
        'vogue_editorial' => '👗 VOGUE·奢侈品编辑 (优雅从容+感官描写)',
        'data_journalism' => '📊 FT·数据新闻 (数据驱动+图表叙事)',
        'luoshen_poetic' => '📜 洛神赋·古典华丽 (辞藻华丽+意象密集)',
        'guoman_narrative' => '🎨 国漫叙事·现代东方 (东方意境+江湖气韵)',
    ];
}
?>
<!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
<div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;padding:12px;margin-bottom:12px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <span style="font-size:14px;">🧬</span>
        <strong style="font-size:13px;color:#18181B;">思想DNA注入</strong>
        <span style="font-size:11px;color:#71717A;">— 选择大神级写作风格, 让文章有灵魂</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select class="linked3-eco-select" id="lk3-style-dna" name="style_dna" style="flex:1;min-width:200px;">
            <option value="">🤖 自动适配 (AI根据主题推断)</option>
            <?php foreach ($writing_styles as $sid => $slabel): ?>
                <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($slabel); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="linked3-eco-select" id="lk3-tone" name="tone" style="width:160px;" title="写作语气调性">
            <optgroup label="商业写作">
                <option value="professional">专业商务 (麦肯锡风)</option>
                <option value="consultative">顾问式 (BCG风)</option>
                <option value="executive">高管简报 (CEO视角)</option>
            </optgroup>
            <optgroup label="内容营销">
                <option value="marketing">营销转化 (AIDA)</option>
                <option value="storytelling">故事营销</option>
                <option value="social">社交媒体 (小红书风)</option>
            </optgroup>
            <optgroup label="专业学术">
                <option value="academic">学术论文 (Nature风)</option>
                <option value="technical">技术文档 (GitHub风)</option>
                <option value="journalistic">新闻深度 (FT风)</option>
            </optgroup>
            <optgroup label="创意表达">
                <option value="casual">轻松随笔 (知乎风)</option>
                <option value="literary">文学叙事</option>
                <option value="persuasive">观点评论 (虎嗅风)</option>
            </optgroup>
        </select>
    </div>
    <!-- v17.2: 人类化可组合模块 (多选叠加) -->
    <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E4E4E7;">
        <div style="font-size:11px;font-weight:600;color:#3F3F46;margin-bottom:6px;">🧬 人类化模块 (可多选叠加, 组合使用)</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g1" title="G1初代脱壳: 剥骨→破壁→绞杀→缝合"> G1脱壳
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g2" title="G2重组变异: 倒装+断句+意象并置"> G2变异
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="g3" title="G3终极坍缩: 0%AI特征+100%人类混沌感"> G3坍缩
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="emotion" title="情绪注入: 消除机械中立+注入极性偏见"> 💉情绪注入
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="oral" title="口语盐化: 注入口语/自嘲/微观偏见"> 🧂口语盐化
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" class="lk3-humanize-module" value="flaw" title="瑕疵植入: 故意漏冠词/介词/不完美表达"> 🔧瑕疵植入
            </label>
        </div>
        <div style="font-size:10px;color:#A1A1AA;margin-top:4px;">💡 勾选多个模块将组合执行。例如: G1+G2+G3 = 完整3代脱壳; 情绪+口语 = 日常人类感</div>
    </div>
</div>
<script>
// v17.2: 全局取值函数 — 所有写作入口共享
window.lk3_get_style_config = function() {
    var cfg = { style_dna: '', tone: 'professional', humanize_modules: [] };
    var sd = document.getElementById('lk3-style-dna');
    if (sd) cfg.style_dna = sd.value;
    var tn = document.getElementById('lk3-tone');
    if (tn) cfg.tone = tn.value;
    document.querySelectorAll('.lk3-humanize-module:checked').forEach(function(cb) {
        cfg.humanize_modules.push(cb.value);
    });
    return cfg;
};
</script>
