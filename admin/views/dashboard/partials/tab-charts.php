<?php
/**
 * Dashboard partial: 图文脚本 v10.1.0 — SEED先行 + 4Band结构 + 视觉系统引用
 *
 * v10.1.0 重构 (基于 /genesis 三脚本统一架构):
 *   公理1: SEED先行 — 共享Stage 0 SEED中心, 图文脚本引用SEED保持品牌一致
 *   公理2: 4Band结构 — Hook/Body/Proof/CTA, 信息密度高, 品牌一致
 *   公理3: 视觉系统引用 — 画风风格+L3灵魂风格叠加生效
 *
 * 输出结构:
 *   图文脚本 = 1个主题 + N个模块
 *   每个模块 = {module_id, band, visual_prompt, text_overlay, seed_refs, layout}
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-23
 */
if (!defined('ABSPATH')) exit;

$nonce_c  = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载画风风格列表 (与漫画脚本共享)
$styles = [];
if (class_exists('Linked3_Genesis_AtomIndex')) {
    $idx = Linked3_Genesis_AtomIndex::instance();
    $raw = $idx->getStyles();
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $styles[$sid] = $label;
        }
    }
}
?>

<style>
.lk3-charts-wrap{max-width:1200px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
.lk3-charts-stage{background:#fff;border:1px solid #E4E4E7;border-radius:10px;padding:20px;margin-bottom:16px;}
.lk3-charts-stage-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.lk3-charts-stage-title{font-size:16px;font-weight:700;color:#18181B;margin:0;display:flex;align-items:center;gap:8px;}
.lk3-charts-stage-desc{font-size:12px;color:#71717A;margin:0 0 16px 0;}
.lk3-charts-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;}
.lk3-charts-form-label{display:block;font-size:12px;font-weight:600;color:#52525B;margin-bottom:4px;}
.lk3-charts-form-control{width:100%;padding:8px 10px;border:1px solid #D4D4D8;border-radius:6px;font-size:13px;background:#fff;}
.lk3-charts-form-control:focus{outline:none;border-color:#0F172A;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.lk3-charts-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #D4D4D8;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;background:#fff;color:#52525B;}
.lk3-charts-btn:hover{background:#F4F4F5;}
.lk3-charts-btn-primary{background:#0F172A;color:#fff;border-color:#0F172A;}
.lk3-charts-btn-primary:hover{background:#2563eb;}
.lk3-charts-btn-sm{padding:4px 10px;font-size:11px;}
.lk3-charts-module-card{background:#fff;border:1px solid #E4E4E7;border-left:4px solid #0F172A;border-radius:6px;padding:12px;margin-bottom:8px;}
.lk3-charts-band-tag{display:inline-block;padding:2px 8px;border-radius:3px;font-size:10px;font-weight:700;color:#fff;}
.lk3-charts-band-hook{background:#EF4444;}
.lk3-charts-band-body{background:#0F172A;}
.lk3-charts-band-proof{background:#10B981;}
.lk3-charts-band-cta{background:#F59E0B;}
.lk3-charts-prompt-box{background:#0f172a;border-radius:4px;padding:6px;margin-top:6px;}
.lk3-charts-prompt-box textarea{width:100%;min-height:60px;font-size:11px;font-family:"SF Mono",Monaco,monospace;background:#18181B;color:#E4E4E7;border:none;border-radius:3px;padding:8px;resize:vertical;line-height:1.5;}
.lk3-charts-seed-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:4px;font-size:11px;color:#0F172A;margin:2px;}
.lk3-charts-hint{font-size:10px;color:#A1A1AA;margin-top:2px;}
</style>

<div class="lk3-charts-wrap">

<h2>图文脚本生成 <span style="font-size:12px;color:#666;font-weight:normal;">v10.1.0 SEED先行 · 8种结构 · 视觉系统引用</span></h2>
<div class="notice notice-info inline"><p><strong>v10.1.0 重构:</strong><br>
<strong>① SEED先行</strong> — 共享Stage 0 SEED中心, 图文脚本引用SEED保持品牌一致<br>
<strong>② 8种结构</strong> — 4Band/Timeline/Flowchart/Comparison/DataChart/Checklist/MindMap/QuoteCard, 自动适配<br>
<strong>③ 视觉系统</strong> — 画风风格+L3灵魂风格叠加, 与漫画脚本共享视觉基因</p></div>

<!-- ===== Stage 0: SEED 引用 (共享) ===== -->
<div class="lk3-charts-stage">
    <div class="lk3-charts-stage-header">
        <h3 class="lk3-charts-stage-title">🧬 Stage 0 · SEED 引用</h3>
        <button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-seed-refresh">↻ 刷新</button>
    </div>
    <p class="lk3-charts-stage-desc">选择要引用的SEED, 保持图文脚本与品牌/角色/风格一致。SEED在「漫画脚本」标签页的Stage 0统一管理。</p>
    <div style="display:flex;gap:8px;margin-bottom:8px;">
        <button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-seed-pick">🔍 从SEED库选择</button>
        <button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-seed-clear">✕ 清空</button>
    </div>
    <input type="hidden" id="lk3-charts-seed-refs" value="">
    <div id="lk3-charts-seed-selected-list" style="min-height:32px;padding:8px;background:#FAFAFA;border-radius:6px;">
        <span style="color:#A1A1AA;font-size:12px;">未选择任何SEED — 点击「从SEED库选择」</span>
    </div>
    <div class="lk3-charts-hint">💡 品牌SEED确保水印/字体一致; 风格SEED确保画风统一; 角色SEED确保人物外貌一致</div>
</div>

<!-- ===== Stage 1: 主题输入 ===== -->
<div class="lk3-charts-stage">
    <div class="lk3-charts-stage-header">
        <h3 class="lk3-charts-stage-title">📝 Stage 1 · 主题/内容输入</h3>
    </div>
    <p class="lk3-charts-stage-desc">输入主题或粘贴文章。AI将按镜生成, 每镜输出1张含完整结构布局的信息图提示词(8种结构可选)。</p>
    <!-- v16.0.25: 主题输入支持分镜式(多段) — 每段一个模块提示 -->
    <details style="margin-bottom:10px;">
        <summary style="font-size:11px;color:#0F172A;cursor:pointer;">💡 分镜式输入 (可选, 每行一个模块主题)</summary>
        <div style="font-size:11px;color:#71717A;margin-top:6px;padding:8px;background:#FAFAFA;border-radius:4px;">
            普通输入: 粘贴一篇文章, AI自动按8种结构拆分<br>
            分镜式输入: 每行一个模块主题, AI按行生成对应模块。例如:<br>
            <pre style="margin:4px 0;font-size:10px;">Hook: 雷欧奥特曼如何打败贝利亚
Body: 雷欧的修炼历程与师徒情
Proof: 经典战斗场景解析
CTA: 关注获取更多奥特曼解析</pre>
        </div>
    </details>
    <textarea id="lk3-charts-topic" class="lk3-charts-form-control" rows="6" placeholder="输入主题或粘贴文章...&#10;&#10;例如: 介绍一款新上市的智能手表, 主打健康监测+长续航+时尚设计...&#10;&#10;或分镜式输入(每行一个模块):&#10;Hook: 吸引注意的开场&#10;Body: 核心信息&#10;Proof: 证据数据&#10;CTA: 行动号召" style="font-size:13px;line-height:1.6;"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
        <span style="font-size:11px;color:#71717A;" id="lk3-charts-topic-stats">0 字</span>
        <span style="font-size:11px;color:#71717A;">建议200-2000字, AI自动拆分模块</span>
    </div>

    <!-- v16.0.23: CSV批量生成功能 (根据模版导入批量生产图文脚本) -->
    <details style="margin-top:14px;border:1px solid #E4E4E7;border-radius:8px;padding:0;">
        <summary style="padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#52525B;background:#FAFAFA;border-radius:8px;">
            📊 CSV批量生成 (一次生成多个主题的图文脚本)
        </summary>
        <div style="padding:14px;">
            <p style="font-size:12px;color:#71717A;margin:0 0 10px;">上传CSV文件(每行一个主题), 系统按Stage 2配置批量生成图文脚本Prompt。适合内容矩阵批量生产。</p>
            <details style="margin-bottom:10px;">
                <summary style="font-size:11px;color:#0F172A;cursor:pointer;">📋 查看CSV模版格式</summary>
                <pre style="background:#F4F4F5;padding:10px;border-radius:4px;font-size:11px;margin-top:6px;overflow-x:auto;">topic,style,layout,module_count
AI写作工具推荐,auto,auto-adapt,1
ChatGPT使用技巧,auto,linear-progression,2
大模型微调教程,auto,hierarchical-layers,3

说明:
- topic: 必填, 主题/内容
- style: 可选, 画风风格ID(留空用Stage2配置)
- layout: 可选, 布局ID(留空用Stage2配置, 建议auto-adapt)
- module_count: 可选, 镜数量(留空用Stage2配置); v16.3.0: 每镜=1张含完整结构的信息图(8种可选)</pre>
            </details>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                <input type="file" id="lk3-charts-csv-file" accept=".csv,.txt" style="flex:1;min-width:200px;font-size:12px;">
                <button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-csv-download-sample">⬇ 下载样稿CSV</button>
                <button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-csv-upload">上传并预览</button>
                <button type="button" class="lk3-charts-btn lk3-charts-btn-sm lk3-charts-btn-primary" id="lk3-charts-csv-generate" disabled>批量生成</button>
            </div>
            <div id="lk3-charts-csv-preview" style="margin-bottom:10px;"></div>
            <div id="lk3-charts-csv-result"></div>
        </div>
    </details>

</div>

<!-- ===== Stage 2: 生成配置 (v2.0 重构: 画风下拉内嵌面板, 修复"看不见"; 合并双AI按钮) ===== -->
<div class="lk3-charts-stage">
    <div class="lk3-charts-stage-header">
        <h3 class="lk3-charts-stage-title">⚙️ Stage 2 · 生成配置</h3>
    </div>
    <p class="lk3-charts-stage-desc">配置画风、平台、模块数量。8种结构自动适配。</p>
    <div class="lk3-charts-form-grid" style="margin-bottom:12px;">

        <!-- v2.0: 画风风格库融合面板 (内嵌画风下拉, 视觉绑定, 修复"看不见"; 合并AI自动适配+AI推荐为单按钮) -->
        <?php
        $style_select_id        = 'lk3-charts-style';
        $topic_input_id         = 'lk3-charts-topic';
        $visual_style_select_id = 'lk3-charts-visual-style'; // v2.0: 解耦联动参数, 供面板JS切换画风时同步技法禁用态
        $nonce                  = wp_create_nonce('linked3_content_writer');
        $ajax_url               = admin_url('admin-ajax.php');
        $instance               = 'charts';
        include __DIR__ . '/style-fusion-panel-v2.php';
        ?>

        <div>
            <label class="lk3-charts-form-label">☁ 云模版 <span style="font-size:10px;color:#A1A1AA;">(跨生态共享)</span></label>
            <select id="lk3-charts-cloud-template" class="lk3-charts-form-control">
                <option value="">不使用云模版</option>
                <?php
                $shared_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
                foreach ($shared_templates as $tpl_id => $tpl):
                    echo '<option value="' . esc_attr($tpl['type'] ?? 'content') . '">' . esc_html('☁ ' . ($tpl['name'] ?? $tpl_id)) . '</option>';
                endforeach;
                if (class_exists('Linked3_Cloud_Template_Factory')):
                    $cloud_cats = ['content' => '内容模版', 'seo' => 'SEO模版', 'social' => '社媒模版', 'video' => '视频模版', 'comic' => '漫画模版'];
                    foreach ($cloud_cats as $cat => $label):
                        echo '<option value="' . esc_attr($cat) . '">' . esc_html('☁ ' . $label . ' (内置)') . '</option>';
                    endforeach;
                endif;
                ?>
            </select>
            <div class="lk3-charts-hint">🔗 从写作生态云模版池拉取 style/palette · <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud')); ?>" style="color:#0F172A;">→ 管理云模版</a></div>
        </div>
        <div>
            <label class="lk3-charts-form-label">🖥️ 目标平台</label>
            <select id="lk3-charts-platform" class="lk3-charts-form-control">
                <option value="midjourney">Midjourney</option>
                <option value="stable_diffusion">Stable Diffusion</option>
                <option value="dalle3">DALL·E 3</option>
                <option value="flux">Flux</option>
            </select>
        </div>
        <div>
            <label class="lk3-charts-form-label">📊 镜数量 <span style="font-size:10px;color:#A1A1AA;">(每镜含完整结构, 8种可选)</span></label>
            <select id="lk3-charts-module-count" class="lk3-charts-form-control" title="每镜输出1张含完整结构布局的信息图(Hook顶部+Body中部+Proof下部+CTA底部)">
                <!-- v16.3.0: 语义修正 — "镜"而非"模块", 每镜含完整结构(8种可选) -->
                <option value="1" selected>1镜 (单张完整信息图, 含完整结构布局, 推荐)</option>
                <option value="2">2镜 (2张信息图, 每张含完整结构)</option>
                <option value="3">3镜 (3张信息图, 适合系列内容)</option>
                <option value="4">4镜 (4张信息图, 适合深度专题)</option>
                <option value="6">6镜 (6张信息图, 适合长篇拆解)</option>
                <option value="8">8镜 (8张信息图, 适合完整知识体系)</option>
                <option value="auto">🤖 自动适配 (AI根据内容决定镜数)</option>
            </select>
            <div class="lk3-charts-hint">💡 v16.3.0: 每镜=1张含完整结构布局的信息图(Hook+Body+Proof+CTA), 非4张独立图</div>
        </div>

        <div>
            <label class="lk3-charts-form-label">📐 信息图布局 <span style="font-size:10px;color:#A1A1AA;">(宝玉20布局)</span></label>
            <select id="lk3-charts-layout" class="lk3-charts-form-control" title="auto-adapt=后端生成时根据内容自动选最佳布局, 新手推荐">
                <option value="auto-adapt">🤖 自动适配 (新手推荐, AI根据内容自动选最佳布局)</option>
                <option value="bento-grid">bento-grid 便当格 (多主题概览, 适合信息密度高的综合页)</option>
                <option value="linear-progression">linear-progression 线性递进 (时间线/流程, 适合步骤说明)</option>
                <option value="binary-comparison">binary-comparison 二元对比 (A vs B, 适合两选项比较)</option>
                <option value="comparison-matrix">comparison-matrix 对比矩阵 (多因素, 适合多维度评测)</option>
                <option value="hierarchical-layers">hierarchical-layers 层级金字塔 (适合总分结构)</option>
                <option value="tree-branching">tree-branching 树形分支 (分类, 适合知识体系)</option>
                <option value="hub-spoke">hub-spoke 中心辐射 (适合一个核心多分支)</option>
                <option value="structural-breakdown">structural-breakdown 结构拆解 (适合拆解分析)</option>
                <option value="iceberg">iceberg 冰山模型 (适合表象vs本质)</option>
                <option value="bridge">bridge 桥梁 (问题-方案, 适合因果连接)</option>
                <option value="funnel">funnel 漏斗 (转化, 适合营销流程)</option>
                <option value="dashboard">dashboard 仪表盘 (KPI, 适合数据概览)</option>
                <option value="periodic-table">periodic-table 元素周期表 (适合分类网格)</option>
                <option value="comic-strip">comic-strip 连环画 (适合故事序列)</option>
                <option value="story-mountain">story-mountain 故事山 (适合叙事弧线)</option>
                <option value="jigsaw">jigsaw 拼图 (适合关联组件)</option>
                <option value="venn-diagram">venn-diagram 韦恩图 (适合交集关系)</option>
                <option value="winding-roadmap">winding-roadmap 蜿蜒路线图 (适合里程碑路径)</option>
                <option value="circular-flow">circular-flow 循环流 (适合闭环流程)</option>
            </select>
            <div class="lk3-charts-hint">💡 <strong>便当格</strong>=多主题概览格(像便当盒分格); <strong>自动适配</strong>=AI根据内容自动选最合适的布局; 不确定就选"自动适配"</div>
        </div>

        <div>
            <label class="lk3-charts-form-label">🎨 信息图技法 <span style="font-size:10px;color:#A1A1AA;">(画风为信息图示类时生效)</span></label>
            <select id="lk3-charts-visual-style" class="lk3-charts-form-control" title="auto-adapt=后端生成时根据内容选最佳技法; 仅画风为信息图示类(F)时生效">
                <option value="auto-adapt">🤖 自动适配 (AI根据内容选最佳技法)</option>
                <option value="xuehui-infographic" selected>📐 学会写作2.0信息图 (默认, 商业生产级, 蓝橙紫三色扁平化)</option>
                <option value="bold-graphic">bold-graphic 粗犷图形</option>
                <option value="corporate-memphis">corporate-memphis 企业扁平 (商务图示)</option>
                <option value="technical-schematic">technical-schematic 技术蓝图 (技术文档)</option>
                <option value="knolling">knolling 整齐排列 (产品展示)</option>
                <option value="dashboard">dashboard 仪表盘风 (数据图示)</option>
                <option value="craft-handmade">craft-handmade 手工纸艺</option>
                <option value="claymation">claymation 黏土动画</option>
                <option value="kawaii">kawaii 日系可爱</option>
                <option value="storybook-watercolor">storybook-watercolor 绘本水彩</option>
                <option value="chalkboard">chalkboard 黑板粉笔</option>
                <option value="cyberpunk-neon">cyberpunk-neon 赛博朋克</option>
                <option value="origami">origami 折纸</option>
                <option value="pixel-art">pixel-art 像素艺术</option>
                <option value="ikea-manual">ikea-manual 宜家手册</option>
                <option value="lego-brick">lego-brick 乐高积木</option>
            </select>
            <div class="lk3-charts-hint" id="lk3-charts-visual-style-hint">💡 仅画风为"信息图示类(F)"时生效; 选"自动适配"由后端决定</div>
        </div>

        <div>
            <label class="lk3-charts-form-label">📐 画幅比例</label>
            <select id="lk3-charts-aspect-ratio" class="lk3-charts-form-control">
                <option value="3:4" selected>3:4 竖版 (默认, 学会写作2.0同款, 小红书/抖音封面)</option>
                <option value="1:1">1:1 正方形 (小红书/朋友圈)</option>
                <option value="4:3">4:3 横版 (公众号/微博)</option>
                <option value="16:9">16:9 宽屏 (B站/YouTube)</option>
                <option value="9:16">9:16 竖屏 (抖音/快手)</option>
            </select>
        </div>
    </div>
        <div style="background:#FAFAFA;padding:10px;border-radius:6px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:#52525B;margin-bottom:6px;">📐 结构说明 (8种可选) <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(v16.3.0: 每镜含完整结构, 输出1张整体信息图)</span></div>
        <div style="font-size:11px;color:#71717A;margin-bottom:8px;">每镜 = 1张信息图, 图内含4个垂直区域:</div>
        <div style="font-size:11px;color:#71717A;display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div><span class="lk3-charts-band-tag lk3-charts-band-hook">Hook</span> 顶部区域: 大标题+冲击力画面</div>
            <div><span class="lk3-charts-band-tag lk3-charts-band-body">Body</span> 中部区域: 信息图谱+结构化</div>
            <div><span class="lk3-charts-band-tag lk3-charts-band-proof">Proof</span> 下部区域: 数据/案例/对比</div>
            <div><span class="lk3-charts-band-tag lk3-charts-band-cta">CTA</span> 底部区域: 按钮引导+紧迫感</div>
        </div>
        </div>
</div>

<!-- ===== Stage 3: 生成执行 ===== -->
<div class="lk3-charts-stage">
    <div class="lk3-charts-stage-header">
        <h3 class="lk3-charts-stage-title">🎬 Stage 3 · 生成执行</h3>
        <span class="spinner is-active" id="lk3-charts-spinner" style="display:none;float:none;margin:0;"></span>
        <span id="lk3-charts-status" style="font-size:12px;color:#71717A;margin-left:8px;"></span>
    </div>
    <p class="lk3-charts-stage-desc">点击生成, AI将按8种结构自动拆分内容, 每个模块生成独立图文Prompt(含SEED引用)。</p>
    <button type="button" class="lk3-charts-btn lk3-charts-btn-primary" id="lk3-charts-gen">🎬 生成图文脚本</button>
    <div id="lk3-charts-result" style="margin-top:12px;"></div>
</div>

</div><!-- /.lk3-charts-wrap -->

<script>
(function(){
    'use strict';
    var nonce = '<?php echo esc_js($nonce_c); ?>';
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var selectedSeedIds = [];

    // 字数统计
    var topicEl = document.getElementById('lk3-charts-topic');
    var statsEl = document.getElementById('lk3-charts-topic-stats');
    if (topicEl && statsEl) {
        topicEl.addEventListener('input', function() {
            statsEl.textContent = topicEl.value.length + ' 字';
        });
    }

    // SEED选择
    var seedPickBtn = document.getElementById('lk3-charts-seed-pick');
    if (seedPickBtn) {
        seedPickBtn.addEventListener('click', function() {
            loadSeedList();
        });
    }

    function loadSeedList() {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_list');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                showSeedPicker(res.data.seeds || []);
            });
    }

    function showSeedPicker(seeds) {
        var existing = document.getElementById('lk3-charts-seed-picker-modal');
        if (existing) existing.remove();

        var html = '<div id="lk3-charts-seed-picker-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
        html += '<div style="background:#fff;border-radius:10px;width:90%;max-width:500px;max-height:70vh;overflow-y:auto;padding:20px;">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
        html += '<h3 style="margin:0;font-size:16px;">🧬 选择SEED</h3>';
        html += '<button onclick="document.getElementById(\'lk3-charts-seed-picker-modal\').remove()" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>';
        html += '</div>';

        if (seeds.length === 0) {
            html += '<div style="text-align:center;padding:30px;color:#71717A;">';
            html += '<p style="font-size:14px;margin:0 0 8px 0;">🧬 暂无SEED</p>';
            html += '<p style="font-size:12px;color:#9ca3af;margin:0 0 12px 0;">SEED是角色/场景/道具等视觉DNA, 需先创建才能生成图示脚本。</p>';
            html += '<a href="' + '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=genesis")); ?>' + '" class="button button-primary" target="_blank">→ 去漫画脚本创建SEED</a>';
            html += '</div>';
        } else {
            seeds.forEach(function(s) {
                var checked = selectedSeedIds.indexOf(s.seed_id) >= 0 ? 'checked' : '';
                var catLabel = s.category || s.seed_category || '';
                html += '<label style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:12px;">';
                html += '<input type="checkbox" class="lk3-charts-seed-checkbox" value="' + escapeHtml(s.seed_id) + '" ' + checked + '>';
                html += '<div style="flex:1;"><strong>' + escapeHtml(s.name || s.seed_id) + '</strong>';
                if (catLabel) html += ' <span style="color:#999;">[' + escapeHtml(catLabel) + ']</span>';
                html += '</div></label>';
            });
        }

        html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">';
        html += '<button class="lk3-charts-btn lk3-charts-btn-sm" onclick="document.getElementById(\'lk3-charts-seed-picker-modal\').remove()">取消</button>';
        html += '<button class="lk3-charts-btn lk3-charts-btn-sm lk3-charts-btn-primary" id="lk3-charts-seed-confirm">确认选择</button>';
        html += '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', html);

        var confirmBtn = document.getElementById('lk3-charts-seed-confirm');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                selectedSeedIds = [];
                document.querySelectorAll('.lk3-charts-seed-checkbox:checked').forEach(function(cb) {
                    selectedSeedIds.push(cb.value);
                });
                document.getElementById('lk3-charts-seed-refs').value = selectedSeedIds.join(',');
                updateSeedSelectedList();
                document.getElementById('lk3-charts-seed-picker-modal').remove();
            });
        }
    }

    function updateSeedSelectedList() {
        var listEl = document.getElementById('lk3-charts-seed-selected-list');
        if (!listEl) return;
        if (selectedSeedIds.length === 0) {
            listEl.innerHTML = '<span style="color:#A1A1AA;font-size:12px;">未选择任何SEED — 点击「从SEED库选择」</span>';
            return;
        }
        var html = '';
        selectedSeedIds.forEach(function(id) {
            html += '<span class="lk3-charts-seed-tag">🧬 ' + escapeHtml(id) + ' <span style="cursor:pointer;color:#DC2626;" onclick="lk3ChartsRemoveSeed(\'' + escapeHtml(id) + '\')">×</span></span>';
        });
        listEl.innerHTML = html;
    }

    window.lk3ChartsRemoveSeed = function(id) {
        var idx = selectedSeedIds.indexOf(id);
        if (idx >= 0) selectedSeedIds.splice(idx, 1);
        document.getElementById('lk3-charts-seed-refs').value = selectedSeedIds.join(',');
        updateSeedSelectedList();
    };

    // 清空SEED
    var seedClearBtn = document.getElementById('lk3-charts-seed-clear');
    if (seedClearBtn) {
        seedClearBtn.addEventListener('click', function() {
            selectedSeedIds = [];
            document.getElementById('lk3-charts-seed-refs').value = '';
            updateSeedSelectedList();
        });
    }

    // 刷新SEED
    var seedRefreshBtn = document.getElementById('lk3-charts-seed-refresh');
    if (seedRefreshBtn) {
        seedRefreshBtn.addEventListener('click', loadSeedList);
    }

    // 生成
    var genBtn = document.getElementById('lk3-charts-gen');
    var spinner = document.getElementById('lk3-charts-spinner');
    var statusEl = document.getElementById('lk3-charts-status');
    var result = document.getElementById('lk3-charts-result');

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var topic = document.getElementById('lk3-charts-topic').value.trim();
            if (!topic || topic.length < 10) {
                alert('请输入至少10字的主题或内容');
                return;
            }

            genBtn.disabled = true;
            spinner.style.display = 'inline-block';
            statusEl.textContent = 'AI按结构拆分内容...';
            statusEl.style.color = '#2271b1';
            result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在生成图文脚本...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_charts_generate_v10');
            fd.append('nonce', nonce);
            fd.append('topic', topic);
            fd.append('style', document.getElementById('lk3-charts-style').value);
            fd.append('platform', document.getElementById('lk3-charts-platform').value);
            fd.append('module_count', document.getElementById('lk3-charts-module-count').value);
            fd.append('aspect_ratio', document.getElementById('lk3-charts-aspect-ratio').value);
            fd.append('seed_refs', document.getElementById('lk3-charts-seed-refs').value);
            // v11.3.0 #1: 宝玉20布局+17风格
            var layoutEl = document.getElementById('lk3-charts-layout');
            var styleEl = document.getElementById('lk3-charts-visual-style');
            if (layoutEl) fd.append('infographic_layout', layoutEl.value);
            if (styleEl) fd.append('infographic_style', styleEl.value);
            // v10.7.0: 跨生态云模版共享
            var cloudTpl = document.getElementById('lk3-charts-cloud-template');
            if (cloudTpl && cloudTpl.value) {
                fd.append('cloud_template_category', cloudTpl.value);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    genBtn.disabled = false;
                    spinner.style.display = 'none';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        result.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '</div>';
                        return;
                    }
                    statusEl.textContent = '✓ 生成完成';
                    statusEl.style.color = '#00a32a';
                    renderResult(res.data);
                })
                .catch(function(e){
                    genBtn.disabled = false;
                    spinner.style.display = 'none';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    result.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(e.message) + '</div>';
                });
        });
    }

    function renderResult(data) {
        var modules = data.modules || [];
        var html = '';

        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 生成成功</strong> — ' + modules.length + ' 个模块';
        if (data.style) html += ' <span style="font-size:11px;color:#666;">| 画风: ' + escapeHtml(data.style) + '</span>';
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;padding:10px;background:#f9fafb;border-radius:6px;">';
        html += '<strong>📦 批量操作:</strong> ';
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-copy-all">📋 复制全部</button> ';
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-download-all">⬇️ 下载全部</button> ';
        // v11.8.0: SOP闭环 — 保存草稿 + 去发布
        html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm" id="lk3-charts-save-draft">💾 保存为草稿</button> ';
        html += '<a href="<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish")); ?>" class="lk3-charts-btn lk3-charts-btn-sm" style="text-decoration:none;display:inline-block;">📤 去发布</a>';
        html += '</div>';

        // v16.0.25: 集中提示词区 — 所有模块Prompt合并显示, 便于一次性复制
        var allPrompts = modules.map(function(m) {
            return '# ' + (m.scene_id || m.module_id || '') + ' ' + (m.title || '') + '\n' + (m.visual_prompt || '');
        }).join('\n\n---\n\n');
        html += '<details style="margin-bottom:12px;border:1px solid #0F172A;border-radius:6px;">';
        html += '<summary style="padding:10px 12px;cursor:pointer;font-size:13px;font-weight:600;color:#0F172A;background:#FAFAFA;border-radius:6px;">📋 集中查看全部提示词 (点击展开, 可一次性复制)</summary>';
        html += '<div style="padding:12px;"><textarea readonly style="width:100%;min-height:300px;font-family:monospace;font-size:11px;line-height:1.5;" onclick="this.select()">' + escapeHtml(allPrompts) + '</textarea>';
        html += '<div style="margin-top:6px;font-size:11px;color:#71717A;">💡 v16.3.0: 共' + modules.length + '镜, 每镜1个整体结构提示词 (非拆分)。点击文本框可全选, Ctrl+C复制。</div>';
        html += '</div></details>';

        // v16.3.0: 镜卡片 — 每镜含完整结构布局预览 + 1个整体提示词
        modules.forEach(function(m, idx) {
            var isUnified = (m.band === '4band-unified' || m.bands); // v16.3.0: 新模式标识
            html += '<div class="lk3-charts-module-card" style="border-left-color:#6366f1;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
            html += '<div>';
            // v16.3.0: 镜标题 (非单个Band标签)
            html += '<span class="lk3-charts-band-tag" style="background:#F4F4F5;color:#4338ca;border:1px solid #6366f1;">🎬 ' + escapeHtml(m.scene_id || m.module_id || ('S' + (idx+1))) + '</span> ';
            if (m.scene_total && m.scene_total > 1) {
                html += '<span style="font-size:10px;color:#A1A1AA;">第' + escapeHtml(String(m.scene_index || (idx+1))) + '镜/共' + escapeHtml(String(m.scene_total)) + '镜</span> ';
            }
            html += '<strong style="font-size:13px;">' + escapeHtml(m.title || '') + '</strong>';
            html += '</div>';
            html += '<button type="button" class="lk3-charts-btn lk3-charts-btn-sm lk3-charts-copy" data-idx="' + idx + '">📋 复制提示词</button>';
            html += '</div>';

            // v16.3.0: 4Band布局预览 (若新模式含bands结构)
            if (isUnified && m.bands) {
                html += '<div style="margin-bottom:8px;padding:8px;background:#FAFAFA;border-radius:6px;border:1px dashed #D4D4D8;">';
                html += '<div style="font-size:11px;font-weight:600;color:#52525B;margin-bottom:6px;">📐 结构布局预览 (单张信息图内的区域)</div>';
                html += '<div style="display:grid;grid-template-rows:auto auto auto auto;gap:4px;">';
                var bandColors = {Hook:'#EF4444', Body:'#0F172A', Proof:'#10B981', CTA:'#F59E0B'};
                var bandZones = {Hook:'顶部', Body:'中部', Proof:'下部', CTA:'底部'};
                ['Hook','Body','Proof','CTA'].forEach(function(bk) {
                    var bd = m.bands[bk] || {};
                    var color = bandColors[bk] || '#71717A';
                    var zone = bandZones[bk] || '';
                    var text = bd.text_overlay || '';
                    html += '<div style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:#fff;border-radius:4px;border-left:3px solid ' + color + ';">';
                    html += '<span style="font-size:10px;font-weight:700;color:' + color + ';min-width:50px;">' + escapeHtml(bk) + '</span>';
                    html += '<span style="font-size:9px;color:#A1A1AA;min-width:30px;">[' + escapeHtml(zone) + ']</span>';
                    html += '<span style="font-size:11px;color:#3F3F46;flex:1;">' + escapeHtml(text) + '</span>';
                    html += '</div>';
                });
                html += '</div>';
                html += '<div style="font-size:10px;color:#A1A1AA;margin-top:4px;">💡 以上结构区域合并为下方1个整体提示词, 生成1张含4区域的信息图</div>';
                html += '</div>';
            } else if (m.text_overlay) {
                // 向后兼容: 旧模式仅显示text_overlay
                html += '<div style="font-size:12px;color:#3F3F46;margin-bottom:4px;"><strong>画面文字:</strong> ' + escapeHtml(m.text_overlay) + '</div>';
            }

            if (m.seed_refs && m.seed_refs.length > 0) {
                html += '<div style="margin-bottom:4px;">';
                m.seed_refs.forEach(function(sr) {
                    html += '<span class="lk3-charts-seed-tag">🧬 ' + escapeHtml(sr) + '</span>';
                });
                html += '</div>';
            }
            // v16.3.0: 整体提示词 (每镜1个, 非每Band1个)
            html += '<div class="lk3-charts-prompt-box">';
            html += '<div style="font-size:10px;color:#6366f1;margin-bottom:2px;font-weight:600;">🎯 整体提示词 (含结构布局, 生成1张完整信息图)</div>';
            html += '<textarea readonly class="lk3-charts-prompt" data-idx="' + idx + '">' + escapeHtml(m.visual_prompt || '') + '</textarea>';
            html += '</div>';
            html += '</div>';
        });

        result.innerHTML = html;

        // 绑定复制
        result.querySelectorAll('.lk3-charts-copy').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = btn.dataset.idx;
                var ta = result.querySelector('.lk3-charts-prompt[data-idx="' + idx + '"]');
                if (ta) {
                    navigator.clipboard.writeText(ta.value).then(function() {
                        btn.textContent = '✓ 已复制';
                        setTimeout(function() { btn.textContent = '📋 复制'; }, 1500);
                    });
                }
            });
        });

        var copyAll = document.getElementById('lk3-charts-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '# ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n' + (m.visual_prompt || '');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + modules.length + ' 个模块Prompt');
                });
            });
        }

        var dlBtn = document.getElementById('lk3-charts-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '# ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n' + (m.visual_prompt || '');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'charts-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v11.8.0: SOP闭环 — 保存为草稿
        var saveDraftBtn = document.getElementById('lk3-charts-save-draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() {
                var parts = modules.map(function(m) {
                    return '## ' + (m.module_id || '') + ' [' + (m.band || '') + '] ' + (m.title || '') + '\n\n' + (m.visual_prompt || '') + '\n\n' + (m.text_overlay || '');
                });
                var title = prompt('请输入文章标题:', '图示脚本-' + Date.now());
                if (!title) return;
                var fd = new FormData();
                fd.append('action', 'linked3_eco_save_draft');
                fd.append('nonce', nonce);
                fd.append('title', title);
                fd.append('content', parts.join('\n\n---\n\n'));
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        alert(d.success ? '✅ 已保存为草稿' : '❌ ' + (d.data.message || '失败'));
                    });
            });
        }
    }

    function getBandColor(band) {
        var map = {Hook:'#EF4444', Body:'#0F172A', Proof:'#10B981', CTA:'#F59E0B'};
        return map[band] || '#0F172A';
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    // v16.0.23: CSV批量生成功能
    var csvData = [];
    var csvHeaders = [];

    var dlBtn = document.getElementById('lk3-charts-csv-download-sample');
    if (dlBtn) {
        dlBtn.addEventListener('click', function(){
            var sample = 'topic,style,layout,module_count\nAI写作工具推荐,auto,auto-adapt,1\nChatGPT使用技巧,auto,linear-progression,2\n大模型微调教程,auto,hierarchical-layers,3\n';
            var blob = new Blob([sample], {type:'text/csv;charset=utf-8'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'linked3-charts-batch-sample.csv';
            a.click();
        });
    }

    var uploadBtn = document.getElementById('lk3-charts-csv-upload');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function(){
            var fileInput = document.getElementById('lk3-charts-csv-file');
            var file = fileInput.files[0];
            if (!file) { alert('请先选择CSV文件'); return; }
            var reader = new FileReader();
            reader.onload = function(e){
                var text = e.target.result;
                var lines = text.split('\n').filter(function(l){return l.trim();});
                if (lines.length < 2) { alert('CSV至少需要1行表头+1行数据'); return; }
                csvHeaders = lines[0].split(',').map(function(s){return s.trim();});
                csvData = [];
                for (var i = 1; i < lines.length; i++) {
                    var parts = lines[i].split(',');
                    var row = {};
                    csvHeaders.forEach(function(h, idx){ row[h] = (parts[idx]||'').trim(); });
                    csvData.push(row);
                }
                var html = '<div style="font-size:12px;color:#52525B;margin-bottom:6px;">预览 ' + csvData.length + ' 条数据:</div>';
                html += '<table class="widefat striped" style="font-size:11px;"><thead><tr>';
                csvHeaders.forEach(function(h){ html += '<th>' + escapeHtml(h) + '</th>'; });
                html += '</tr></thead><tbody>';
                csvData.slice(0, 10).forEach(function(r){
                    html += '<tr>';
                    csvHeaders.forEach(function(h){ html += '<td>' + escapeHtml(r[h]||'') + '</td>'; });
                    html += '</tr>';
                });
                html += '</tbody></table>';
                if (csvData.length > 10) html += '<div style="font-size:11px;color:#71717A;margin-top:4px;">(仅显示前10条, 共' + csvData.length + '条)</div>';
                document.getElementById('lk3-charts-csv-preview').innerHTML = html;
                document.getElementById('lk3-charts-csv-generate').disabled = false;
            };
            reader.readAsText(file, 'UTF-8');
        });
    }

    var csvGenBtn = document.getElementById('lk3-charts-csv-generate');
    if (csvGenBtn) {
        csvGenBtn.addEventListener('click', function(){
            if (csvData.length === 0) { alert('请先上传CSV'); return; }
            csvGenBtn.disabled = true;
            csvGenBtn.textContent = '批量生成中...';
            var resultEl = document.getElementById('lk3-charts-csv-result');
            resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;">批量生成中, 共 ' + csvData.length + ' 个主题...</div>';

            var results = [];
            var idx = 0;
            var defaultStyle = document.getElementById('lk3-charts-style').value;
            var defaultLayout = document.getElementById('lk3-charts-layout').value;
            var defaultModuleCount = document.getElementById('lk3-charts-module-count').value;
            var defaultVisualStyle = document.getElementById('lk3-charts-visual-style').value;
            var defaultAspectRatio = document.getElementById('lk3-charts-aspect-ratio').value;
            var defaultPlatform = document.getElementById('lk3-charts-platform').value;

            function processNext() {
                if (idx >= csvData.length) {
                    csvGenBtn.disabled = false;
                    csvGenBtn.textContent = '批量生成';
                    var successCount = results.filter(function(r){return r.success;}).length;
                    var html = '<div class="notice notice-success inline"><p>批量生成完成: ' + successCount + '/' + csvData.length + ' 成功</p></div>';
                    html += '<table class="widefat striped"><thead><tr><th>#</th><th>主题</th><th>状态</th><th>模块数</th></tr></thead><tbody>';
                    results.forEach(function(r, i){
                        html += '<tr><td>' + (i+1) + '</td><td>' + escapeHtml(r.topic) + '</td><td>' + (r.success ? '✅' : '❌ ' + escapeHtml(r.error||'')) + '</td><td>' + (r.module_count||0) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    resultEl.innerHTML = html;
                    return;
                }
                var row = csvData[idx];
                var topic = row.topic || '';
                if (!topic) { results.push({topic:'(空)', success:false, error:'主题为空'}); idx++; processNext(); return; }

                var fd = new FormData();
                fd.append('action', 'linked3_charts_generate_v10');
                fd.append('nonce', nonce);
                fd.append('topic', topic);
                fd.append('style', row.style || defaultStyle);
                fd.append('infographic_layout', row.layout || defaultLayout);
                fd.append('infographic_style', defaultVisualStyle);
                fd.append('module_count', row.module_count || defaultModuleCount);
                fd.append('platform', defaultPlatform);
                fd.append('aspect_ratio', defaultAspectRatio);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                    .then(function(data){
                        if (data.success) {
                            results.push({topic:topic, success:true, module_count:(data.data.modules||[]).length});
                        } else {
                            results.push({topic:topic, success:false, error:(data.data&&data.data.message)||'生成失败'});
                        }
                        idx++;
                        resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;">批量生成中... ' + idx + '/' + csvData.length + '</div>';
                        processNext();
                    })
                    .catch(function(e){
                        results.push({topic:topic, success:false, error:e.message});
                        idx++;
                        processNext();
                    });
            }
            processNext();
        });
    }

    // v1.2: 风格库融合面板的JS逻辑已迁移至 style-fusion-panel.php 可复用组件
    // 每个实例(charts/genesis/video)通过 include 引入, 自带独立JS作用域, 避免重复
})();
</script>
