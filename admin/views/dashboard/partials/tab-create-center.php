<?php
if (!defined('ABSPATH')) exit;

// 获取图示结构列表 (8种结构替代旧4Band一刀切)
$structures = [];
if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramStructureRegistry')) {
    $structures = \Linked3\Classes\Diagram\DiagramStructureRegistry::all();
}
$structure_count = count($structures);
if ($structure_count === 0) {
    $structure_count = 8; // fallback
}
?>
<div class="wrap linked3-create-center">
    <h2><?php echo esc_html__('🚀 创作中心', 'linked3'); ?></h2>
    <p class="description"><?php echo esc_html__('统一创作入口 · 选择类型 → 输入素材 → 生成', 'linked3'); ?></p>

    <!-- Step 1: Content Type -->
    <h3><?php echo esc_html__('① 选择内容类型', 'linked3'); ?></h3>
    <div class="l3-type-selector" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="article" checked onchange="l3_switch()">
            <div style="font-size:32px;">📝</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('文章写作', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;"><?php echo esc_html__('SEO文章 · 博客 · 长文', 'linked3'); ?></div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="comic" onchange="l3_switch()">
            <div style="font-size:32px;">🎨</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('漫画脚本', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;"><?php echo esc_html__('分镜 · 画面描述 · 角色设计', 'linked3'); ?></div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="diagram" onchange="l3_switch()">
            <div style="font-size:32px;">📊</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('知识图谱', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;">信息图 · <?php echo $structure_count; ?>种结构 · 单图</div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="video" onchange="l3_switch()">
            <div style="font-size:32px;">🎬</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('视频脚本', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;"><?php echo esc_html__('分镜 · 运镜 · Motion', 'linked3'); ?></div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="xhs" onchange="l3_switch()">
            <div style="font-size:32px;">📕</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('小红书', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;"><?php echo esc_html__('爆款图文 · 多页笔记', 'linked3'); ?></div>
        </label>
        <label class="l3-type-card" style="flex:1;min-width:140px;border:2px solid #ddd;border-radius:8px;padding:16px;cursor:pointer;text-align:center;">
            <input type="radio" name="l3_content_type" value="book" onchange="l3_switch()">
            <div style="font-size:32px;">📚</div>
            <div style="font-weight:600;margin-top:8px;"><?php echo esc_html__('书籍生成', 'linked3'); ?></div>
            <div style="font-size:12px;color:#666;"><?php echo esc_html__('BookFactory · 整书输出', 'linked3'); ?></div>
        </label>
    </div>

    <!-- Step 2: Input -->
    <h3><?php echo esc_html__('② 输入素材', 'linked3'); ?></h3>
    <table class="form-table">
        <tr id="l3-topic-row">
            <th><label><?php echo esc_html__('主题 / 关键词', 'linked3'); ?></label></th>
            <td><input type="text" id="l3_topic" class="large-text" placeholder="<?php echo esc_attr__('输入主题或关键词', 'linked3'); ?>" /></td>
        </tr>
        <tr id="l3-script-row" style="display:none;">
            <th><label><?php echo esc_html__('剧本 / 文章内容', 'linked3'); ?></label></th>
            <td><textarea id="l3_script" rows="6" class="large-text" placeholder="<?php echo esc_attr__('粘贴剧本、故事或文章内容', 'linked3'); ?>"></textarea></td>
        </tr>
    </table>

    <!-- Step 2b: Diagram Structure Selector (when diagram is selected) -->
    <table class="form-table" id="l3-structure-row" style="display:none;">
        <tr>
            <th><label><?php echo esc_html__('📊 图示结构', 'linked3'); ?></label></th>
            <td>
                <select id="l3_structure" class="regular-text">
                    <option value="auto"><?php echo esc_html__('🤖 自动适配 (推荐)', 'linked3'); ?></option>
                    <?php if (!empty($structures)): foreach ($structures as $sid => $s): ?>
                        <option value="<?php echo esc_attr($sid); ?>">
                            <?php echo esc_html($s['icon'] ?? '📌'); ?> <?php echo esc_html($s['label'] ?? $sid); ?>
                            — <?php echo esc_html(mb_substr($s['description'] ?? '', 0, 40)); ?>
                        </option>
                    <?php endforeach; else: ?>
                        <option value="4band"><?php echo esc_html__('4Band · 经典四段式', 'linked3'); ?></option>
                        <option value="timeline"><?php echo esc_html__('⏳ Timeline · 时间线', 'linked3'); ?></option>
                        <option value="flowchart"><?php echo esc_html__('🔄 Flowchart · 流程图', 'linked3'); ?></option>
                        <option value="comparison"><?php echo esc_html__('⚖️ Comparison · 对比图', 'linked3'); ?></option>
                        <option value="data_chart"><?php echo esc_html__('📈 DataChart · 数据图', 'linked3'); ?></option>
                        <option value="checklist"><?php echo esc_html__('✅ Checklist · 清单', 'linked3'); ?></option>
                        <option value="mindmap"><?php echo esc_html__('🧠 MindMap · 思维导图', 'linked3'); ?></option>
                        <option value="quote_card"><?php echo esc_html__('💬 QuoteCard · 金句卡', 'linked3'); ?></option>
                    <?php endif; ?>
                </select>
                <p class="description">选择信息图结构 (v19.52+ 已替代旧4Band一刀切，支持<?php echo $structure_count; ?>种结构)</p>
            </td>
        </tr>
    </table>

    <!-- Step 2c: Generation Config (复合杠杆配置) -->
    <table class="form-table" id="l3-config-row" style="display:none;">
        <tr>
            <th><label><?php echo esc_html__('⚙️ 生成配置', 'linked3'); ?></label></th>
            <td>
                <div style="display:flex;gap:16px;flex-wrap:wrap;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_composite" value="1" checked>
                        <span><?php echo esc_html__('🧠 复合杠杆增强', 'linked3'); ?></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_cos" value="1">
                        <span><?php echo esc_html__('🔄 COS三代演化', 'linked3'); ?></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_seo" value="1" checked>
                        <span><?php echo esc_html__('📈 SEO优化', 'linked3'); ?></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" id="l3_cfg_risk" value="1">
                        <span><?php echo esc_html__('🛡️ 风险审查', 'linked3'); ?></span>
                    </label>
                </div>
                <p class="description"><?php echo esc_html__('复合杠杆: 17种高级认知能力可选, COS: 三代演化生成最优方案', 'linked3'); ?></p>
            </td>
        </tr>
    </table>

    <!-- Step 3: Style (unified) -->
    <?php
    // v27.17.9-fix1: 动态获取风格数量
    $style_count = 71;
    $style_dir = dirname(__DIR__, 4) . '/src/Classes/Genesis/styles';
    if (is_dir($style_dir)) {
        $style_files = glob($style_dir . '/*.json');
        if ($style_files) $style_count = count($style_files);
    }
    ?>
    <h3>③ 选择画风 <span style="font-weight:normal;font-size:12px;color:#666;">(共用统一风格库 · <?php echo $style_count; ?>种画风 × 9推荐策略)</span></h3>
    <table class="form-table">
        <tr>
            <th><label><?php echo esc_html__('画风', 'linked3'); ?></label></th>
            <td>
                <select id="l3_style" class="regular-text">
                    <option value="auto"><?php echo esc_html__('🤖 自动适配 (推荐)', 'linked3'); ?></option>
                    <optgroup label="📐 信息图示 (F01-F57)">
                        <option value="flat_infographic"><?php echo esc_html__('扁平信息图 · 蓝橙紫三色', 'linked3'); ?></option>
                        <option value="isometric"><?php echo esc_html__('等轴测图', 'linked3'); ?></option>
                        <option value="minimal_chart"><?php echo esc_html__('极简图表', 'linked3'); ?></option>
                    </optgroup>
                    <optgroup label="🎨 艺术插画 (Y01-Y05)">
                        <option value="watercolor"><?php echo esc_html__('水彩', 'linked3'); ?></option>
                        <option value="oil_painting"><?php echo esc_html__('油画', 'linked3'); ?></option>
                        <option value="ink_wash"><?php echo esc_html__('水墨', 'linked3'); ?></option>
                    </optgroup>
                    <optgroup label="📷 商业摄影 (S01-S06)">
                        <option value="documentary_photo"><?php echo esc_html__('纪实摄影', 'linked3'); ?></option>
                        <option value="studio_product"><?php echo esc_html__('产品摄影', 'linked3'); ?></option>
                    </optgroup>
                    <optgroup label="🔬 概念实验 (G01-G03)">
                        <option value="cyberpunk"><?php echo esc_html__('赛博朋克', 'linked3'); ?></option>
                        <option value="surreal"><?php echo esc_html__('超现实', 'linked3'); ?></option>
                    </optgroup>
                </select>
                <p class="description"><?php echo esc_html__('自动适配 = AI根据内容类型和主题智能选择最佳画风', 'linked3'); ?></p>
            </td>
        </tr>
        <tr id="l3-platform-row" style="display:none;">
            <th><label><?php echo esc_html__('目标平台', 'linked3'); ?></label></th>
            <td>
                <select id="l3_platform">
                    <option value="midjourney">Midjourney</option>
                    <option value="dall-e">DALL-E 3</option>
                    <option value="stable-diffusion">Stable Diffusion</option>
                    <option value="flux">Flux</option>
                </select>
            </td>
        </tr>
        <tr id="l3-count-row">
            <th><label id="l3-count-label"><?php echo esc_html__('字数', 'linked3'); ?></label></th>
            <td>
                <input type="number" id="l3_count" value="1200" min="100" max="10000" step="100" />
                <span id="l3-count-hint" class="description"></span>
            </td>
        </tr>
    </table>

    <!-- Step 4: Generate -->
    <p style="margin-top:20px;">
        <button type="button" id="l3_generate_btn" class="button button-primary button-large" onclick="l3_generate()">
            ⚡ 生成
        </button>
        <span id="l3_progress" style="margin-left:12px;color:#666;"></span>
    </p>

    <div id="l3_result" style="margin-top:20px;"></div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-create-center.js ?>
