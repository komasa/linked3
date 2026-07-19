<?php
/**
 * 风格库融合面板 v2.0 — 统一画风选择器
 *
 * ============================================================================
 * v2.0 重构要点 (修复 v1.x 三大 Bug + 五处架构冲突):
 *
 * [Bug-1 修复] 画风下拉"看不见"
 *   v1.x: 画风下拉在 form-grid 第1格, 面板 grid-column:1/-1 独占下一行 → 视觉断裂
 *   v2.0: 画风下拉内嵌进面板顶部, 作为面板"输出区", 视觉强绑定
 *
 * [Bug-2 修复] AI自动适配 ≈ AI推荐 语义重叠
 *   v1.x: 两个按钮都调 linked3_genesis_recommend(mode=auto), 仅 Top1自动 vs Top3手动 之差
 *   v2.0: 合并为单一"🤖 AI推荐"按钮 + "☑ 自动选用Top1"开关; 开关开=自动选中, 关=手动选卡
 *
 * [冲突-3 修复] 视图过滤清空"自动适配"选项
 *   v1.x: styleSelect.innerHTML='' 直接清空, "auto"选项永久消失
 *   v2.0: 重建时始终保留首位"🤖 自动适配(后端推断)"选项
 *
 * [冲突-4 修复] 硬编码 DOM 耦合
 *   v1.x: JS 硬编码 getElementById('lk3-charts-visual-style') 仅 charts 实例可用
 *   v2.0: visual_style_select_id 改为可选参数, 通过 data 属性传递, 三实例通用
 *
 * [冲突-5 修复] 双轴语义混淆
 *   v1.x: 视图过滤(F/Y/S/G) 与 推荐策略(F-01~F-09) 混在同一面板无分隔
 *   v2.0: 明确分区 "① 按用途筛选" + "② 按策略AI推荐", 标题标注轴别
 *
 * [冲突-6 修复] 四处"auto"语义不一
 *   v1.x: 画风auto / 面板AI自动适配 / 布局auto-adapt / 技法auto-adapt 各指不同
 *   v2.0: 画风auto=后端推断(保留); 面板改为"AI推荐+开关"; 布局/技法auto-adapt加tooltip说明
 *
 * [冲突-7 修复] 标签冗余
 *   v1.x: 面板头"AI智能推荐" + 按钮"AI自动适配" + 按钮"AI推荐" = 3个AI标签
 *   v2.0: 面板头"🎨 画风风格库" + 单按钮"🤖 AI推荐" = 语义清晰
 * ============================================================================
 *
 * 用法:
 *   <?php
 *   $fusion_params = [
 *       'style_select_id'        => 'lk3-charts-style',        // 必填: 画风下拉DOM ID (v2.0由面板内部渲染)
 *       'topic_input_id'         => 'lk3-charts-topic',         // 必填: 内容输入框DOM ID
 *       'visual_style_select_id' => 'lk3-charts-visual-style',  // 可选: 信息图技法下拉DOM ID (联动禁用)
 *       'nonce'                  => wp_create_nonce('linked3_content_writer'),
 *       'ajax_url'               => admin_url('admin-ajax.php'),
 *       'instance'               => 'charts',                   // 实例标识
 *   ];
 *   include __DIR__ . '/style-fusion-panel-v2.php';
 *   ?>
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @since 16.0.35
 * @replaces style-fusion-panel.php (v1.2)
 */

if (!defined('ABSPATH')) exit;

// ---- 参数兜底 (v2.0 新增 visual_style_select_id) ----
$style_select_id        = $style_select_id        ?? 'linked3-genesis-style';
$topic_input_id         = $topic_input_id         ?? 'linked3-genesis-script';
$visual_style_select_id = $visual_style_select_id ?? '';  // v2.0: 可选, 留空则不联动
$nonce                  = $nonce                  ?? wp_create_nonce('linked3_content_writer');
$ajax_url               = $ajax_url               ?? admin_url('admin-ajax.php');
$instance               = $instance               ?? 'default';

// ---- 加载画风列表 (v2.0: 面板自己负责渲染下拉, 不依赖外部预渲染) ----
// v11.0: 按 instance 过滤风格 — 漫画脚本(genesis)只显示S/Y/G漫画基因, 图示脚本(charts)只显示F图示基因
// 公理2: 漫画视觉基因(S/Y/G)与图示基因(F)正交, 不可混入
$panel_styles = [];
if (class_exists('Linked3_Genesis_AtomIndex')) {
    $idx = Linked3_Genesis_AtomIndex::instance();
    $raw = $idx->getStyles();
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $uc = $sinfo['usage_code'] ?? '';
            // v11.0: 按 instance 过滤
            // genesis(漫画脚本) → 只保留 S/Y/G 开头的漫画视觉风格
            // charts(图示脚本)  → 只保留 F 开头的图示风格
            // default/其他       → 全部显示(兼容旧行为)
            if ($instance === 'genesis') {
                if (!preg_match('/^[SYG]\d+/', $uc)) continue;
            } elseif ($instance === 'charts' || $instance === 'diagram') {
                if (!preg_match('/^F\d+/', $uc)) continue;
            }
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if ($uc) $label = '[' . $uc . '] ' . $label;
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $panel_styles[$sid] = $label;
        }
    }
}
// v11.0: 动态计算风格数量
$_panel_style_count = count($panel_styles);
?>

<!-- ===== 画风风格库融合面板 v2.0 [实例: <?php echo esc_attr($instance); ?>] ===== -->
<div class="lk3-sfp-v2" data-instance="<?php echo esc_attr($instance); ?>"
     data-style-select-id="<?php echo esc_attr($style_select_id); ?>"
     data-visual-style-select-id="<?php echo esc_attr($visual_style_select_id); ?>"
     data-topic-input-id="<?php echo esc_attr($topic_input_id); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-ajax-url="<?php echo esc_attr($ajax_url); ?>">

    <!-- 面板头: 统一标题 (去除冗余"AI"标签) -->
    <div class="lk3-sfp-v2-header">
        <strong class="lk3-sfp-v2-title">🎨 画风风格库</strong>
        <span class="lk3-sfp-v2-meta">v2.0 · <?php echo esc_html($_panel_style_count); ?>风格(<?php echo $instance === 'genesis' ? 'S/Y/G漫画' : ($instance === 'charts' ? 'F图示' : 'F/Y/S/G全量'); ?>) × 9推荐策略</span>
    </div>

    <!-- ===== 输出区: 画风下拉 (v2.0 内嵌, 视觉绑定, 修复"看不见") ===== -->
    <div class="lk3-sfp-v2-output">
        <label class="lk3-sfp-v2-label" for="<?php echo esc_attr($style_select_id); ?>">
            🎨 当前画风 <span class="lk3-sfp-v2-label-hint">(画面视觉基因 · 上方筛选/推荐的结果落点)</span>
        </label>
        <select id="<?php echo esc_attr($style_select_id); ?>" class="lk3-sfp-v2-select lk3-sfp-v2-style-select">
            <!-- v2.0: "自动适配"选项始终保留首位, 视图过滤不再清除 -->
            <option value="auto">🤖 自动适配 (后端生成时推断最佳画风)</option>
            <?php if (!empty($panel_styles)): foreach ($panel_styles as $sid => $sname): ?>
                <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($sname); ?></option>
            <?php endforeach; endif; ?>
        </select>
        <div class="lk3-sfp-v2-hint">💡 选"自动适配"=后端推断; 选具体风格=锁定视觉基因; 也可用下方筛选/推荐辅助决策</div>
    </div>

    <!-- ===== 轴①: 按用途筛选 (F/Y/S/G 互斥分类) ===== -->
    <div class="lk3-sfp-v2-section">
        <div class="lk3-sfp-v2-section-title">① 按用途筛选 <span class="lk3-sfp-v2-axis-tag">轴: 画风大类</span></div>
        <div class="lk3-sfp-v2-view-row">
            <button type="button" class="lk3-sfp-v2-view-btn lk3-sfp-v2-view-active" data-view="all">全部 (71)</button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="infographic">📐 信息图示 F01-F57 (57)</button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="illustration">🎨 艺术插画 Y01-Y05 (5)</button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="photography">📷 商业摄影 S01-S06 (6)</button>
            <button type="button" class="lk3-sfp-v2-view-btn" data-view="concept">🔬 概念实验 G01-G03 (3)</button>
        </div>
    </div>

    <!-- ===== 轴②: 按策略AI推荐 (F-01~F-09 推荐引擎) ===== -->
    <div class="lk3-sfp-v2-section">
        <div class="lk3-sfp-v2-section-title">② 按策略AI推荐 <span class="lk3-sfp-v2-axis-tag">轴: 推荐策略</span></div>
        <div class="lk3-sfp-v2-recommend-row">
            <select class="lk3-sfp-v2-select lk3-sfp-v2-mode-select" style="flex:1;min-width:200px;">
                <option value="auto">F-01 一键智能推荐 (Top3候选)</option>
                <option value="beginner">F-02 新手友好 (生产就绪优先)</option>
                <option value="designer">F-03 设计师精选 (信息图+融合技法)</option>
                <option value="market">F-04 万兴市场优选 (wondershare_ready)</option>
                <option value="industry">F-05 行业专家 (行业匹配翻倍)</option>
                <option value="accessible">F-06 无障碍优先 (高对比+信息图)</option>
                <option value="conversion">F-07 高转化推荐 (真人摄影+CTA)</option>
                <option value="complex">F-08 复杂内容 (融合技法+多模块)</option>
                <option value="cross-platform">F-09 跨平台适配 (G5/G6生产级)</option>
            </select>
            <!-- v2.0: 合并原"AI自动适配"为开关, 消除双按钮冗余 -->
            <label class="lk3-sfp-v2-autopick">
                <input type="checkbox" class="lk3-sfp-v2-autopick-cb" checked>
                <span>☑ 自动选用Top1</span>
            </label>
            <button type="button" class="lk3-sfp-v2-btn lk3-sfp-v2-btn-recommend">🤖 AI推荐</button>
        </div>
        <div class="lk3-sfp-v2-hint">💡 勾选"自动选用Top1"=推荐后直接锁定最佳画风(原AI自动适配); 取消勾选=展示TopN候选卡片手动选(原AI推荐)</div>
    </div>

    <!-- 推荐结果展示区 -->
    <div class="lk3-sfp-v2-result"></div>
</div>

<style>
/* ===== v11.0 极简顶级设计 (内聚, 不污染外部) ===== */
.lk3-sfp-v2{padding:14px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Noto Sans SC",sans-serif;font-size:13px;}
.lk3-sfp-v2-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #E4E4E7;}
.lk3-sfp-v2-title{font-size:13px;font-weight:600;color:#18181B;}
.lk3-sfp-v2-meta{font-size:10px;color:#A1A1AA;font-variant-numeric:tabular-nums;}

/* 输出区: 画风下拉 (v2.0 核心 — 视觉绑定) */
.lk3-sfp-v2-output{background:#FFFFFF;border:1px solid #E4E4E7;border-radius:4px;padding:10px;margin-bottom:12px;}
.lk3-sfp-v2-label{display:block;font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:4px;}
.lk3-sfp-v2-label-hint{font-size:10px;color:#A1A1AA;font-weight:normal;}
.lk3-sfp-v2-select{width:100%;padding:8px 10px;border:1px solid #D4D4D8;border-radius:4px;font-size:13px;background:#FFFFFF;color:#27272A;transition:border-color 150ms ease,box-shadow 150ms ease;}
.lk3-sfp-v2-select:hover{border-color:#A1A1AA;}
.lk3-sfp-v2-select:focus{outline:none;border-color:#0F172A;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.lk3-sfp-v2-hint{font-size:10px;color:#A1A1AA;margin-top:4px;line-height:1.5;}

/* 分区 */
.lk3-sfp-v2-section{margin-bottom:12px;}
.lk3-sfp-v2-section-title{font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:6px;display:flex;align-items:center;gap:8px;}
.lk3-sfp-v2-axis-tag{font-size:9px;padding:1px 6px;background:#F4F4F5;color:#52525B;border-radius:3px;font-weight:600;font-variant-numeric:tabular-nums;}

/* 视图过滤按钮 */
.lk3-sfp-v2-view-row{display:flex;gap:6px;flex-wrap:wrap;}
.lk3-sfp-v2-view-btn{padding:5px 11px;font-size:11px;border:1px solid #D4D4D8;border-radius:4px;background:#FFFFFF;color:#52525B;cursor:pointer;transition:all 150ms ease;font-weight:500;}
.lk3-sfp-v2-view-btn:hover{border-color:#A1A1AA;background:#FAFAFA;}
.lk3-sfp-v2-view-btn.lk3-sfp-v2-view-active{background:#0F172A;color:#FFFFFF;border-color:#0F172A;}

/* 推荐行 */
.lk3-sfp-v2-recommend-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.lk3-sfp-v2-autopick{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#52525B;cursor:pointer;white-space:nowrap;padding:6px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;}
.lk3-sfp-v2-autopick input{margin:0;cursor:pointer;}
.lk3-sfp-v2-btn{padding:8px 16px;border:1px solid #0F172A;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;white-space:nowrap;transition:all 150ms ease;}
.lk3-sfp-v2-btn-recommend{background:#0F172A;color:#FFFFFF;}
.lk3-sfp-v2-btn-recommend:hover{background:#18181B;border-color:#18181B;}
.lk3-sfp-v2-btn:disabled{opacity:.5;cursor:not-allowed;}

/* 结果区 */
.lk3-sfp-v2-result{margin-top:10px;min-height:20px;}
.lk3-sfp-v2-rec-grid{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;}
.lk3-sfp-v2-rec-card{flex:1;min-width:140px;max-width:200px;padding:8px;border-radius:4px;cursor:pointer;transition:border-color 150ms ease;}
.lk3-sfp-v2-rec-card:hover{border-color:#A1A1AA;}
.lk3-sfp-v2-rec-card-top1{background:#FFFFFF;border:2px solid #0F172A;}
.lk3-sfp-v2-rec-card-other{background:#FFFFFF;border:1px solid #E4E4E7;}
.lk3-sfp-v2-rec-card-label{font-size:10px;font-weight:600;margin-bottom:2px;color:#71717A;text-transform:uppercase;letter-spacing:.05em;}
.lk3-sfp-v2-rec-card-name{font-size:12px;font-weight:600;color:#18181B;}
.lk3-sfp-v2-rec-card-cat{font-size:10px;color:#71717A;margin-top:2px;}
.lk3-sfp-v2-rec-card-score{font-size:10px;color:#52525B;margin-top:4px;font-variant-numeric:tabular-nums;}
.lk3-sfp-v2-rec-card-reason{font-size:10px;color:#A1A1AA;margin-top:2px;}
</style>

<script>
(function() {
    // ===== v2.0: 每实例独立初始化, 避免重复绑定 =====
    var panel = document.querySelector('.lk3-sfp-v2[data-instance="<?php echo esc_js($instance); ?>"]');
    if (!panel || panel.dataset.v2Init === '1') return;
    panel.dataset.v2Init = '1';

    var instance = panel.dataset.instance;
    var styleSelectId = panel.dataset.styleSelectId;
    var visualStyleSelectId = panel.dataset.visualStyleSelectId; // v2.0: 可选联动
    var topicInputId = panel.dataset.topicInputId;
    var nonce = panel.dataset.nonce;
    var ajaxUrl = panel.dataset.ajaxUrl;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function getStyleSelect() { return document.getElementById(styleSelectId); }
    function getTopicText() {
        var el = document.getElementById(topicInputId);
        return el && el.value ? el.value.trim() : '';
    }
    function getResultEl() { return panel.querySelector('.lk3-sfp-v2-result'); }

    // ===== v2.0: 重建画风下拉时始终保留"自动适配"选项 (修复冲突-3) =====
    function rebuildStyleSelect(stylesObj) {
        var ss = getStyleSelect();
        if (!ss) return;
        var prevVal = ss.value; // 记住当前选择
        ss.innerHTML = '';
        // 始终首位保留"自动适配"
        var autoOpt = document.createElement('option');
        autoOpt.value = 'auto';
        autoOpt.textContent = '🤖 自动适配 (后端生成时推断最佳画风)';
        ss.appendChild(autoOpt);
        // 追加筛选结果
        Object.keys(stylesObj).forEach(function(sid) {
            var opt = document.createElement('option');
            opt.value = sid;
            opt.textContent = stylesObj[sid].label || sid;
            ss.appendChild(opt);
        });
        // 尝试恢复之前的选择
        for (var i = 0; i < ss.options.length; i++) {
            if (ss.options[i].value === prevVal) { ss.selectedIndex = i; break; }
        }
    }

    // ===== v2.0: 联动信息图技法下拉 (解耦硬编码, 通过参数传递) =====
    function syncVisualStyleAvailability(view) {
        if (!visualStyleSelectId) return; // 未配置则跳过 (genesis/video 实例)
        var vss = document.getElementById(visualStyleSelectId);
        if (!vss) return;
        var wrap = vss.closest('div');
        if (view === 'infographic' || view === 'all') {
            vss.disabled = false;
            vss.style.opacity = '1';
            vss.style.cursor = '';
            if (wrap) wrap.style.opacity = '1';
        } else {
            vss.disabled = true;
            vss.style.opacity = '0.5';
            vss.style.cursor = 'not-allowed';
            if (wrap) wrap.style.opacity = '0.6';
        }
    }

    // ===== 轴①: 视图过滤按钮 =====
    panel.querySelectorAll('.lk3-sfp-v2-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            panel.querySelectorAll('.lk3-sfp-v2-view-btn').forEach(function(b) {
                b.classList.remove('lk3-sfp-v2-view-active');
            });
            btn.classList.add('lk3-sfp-v2-view-active');

            var view = btn.dataset.view;
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_styles_filtered');
            fd.append('nonce', nonce);
            fd.append('view', view);

            var resultEl = getResultEl();
            resultEl.innerHTML = '<div style="color:#71717A;font-size:12px;">⏳ 加载风格列表...</div>';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (!res.success) {
                        resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;">❌ ' + escapeHtml(res.data && res.data.message || '加载失败') + '</div>';
                        return;
                    }
                    var styles = res.data.styles || {};
                    var count = res.data.count || 0;
                    rebuildStyleSelect(styles); // v2.0: 保留auto选项
                    syncVisualStyleAvailability(view); // v2.0: 解耦联动
                    resultEl.innerHTML = '<div style="color:#10B981;font-size:12px;">✅ 视图 [' + escapeHtml(view) + '] 筛选完成, 共 ' + count + ' 个风格已载入上方画风下拉 (自动适配选项已保留)</div>';
                })
                .catch(function(e) {
                    resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;">❌ 网络错误: ' + escapeHtml(e.message) + '</div>';
                });
        });
    });

    // ===== 轴②: AI推荐 (v2.0 合并双按钮为单按钮+开关) =====
    var recBtn = panel.querySelector('.lk3-sfp-v2-btn-recommend');
    if (recBtn) {
        recBtn.addEventListener('click', function() {
            var topic = getTopicText();
            if (!topic) {
                alert('请先在内容输入框填写内容, AI推荐将基于内容匹配最佳画风');
                return;
            }
            var modeSelect = panel.querySelector('.lk3-sfp-v2-mode-select');
            var mode = modeSelect ? modeSelect.value : 'auto';
            var autopick = panel.querySelector('.lk3-sfp-v2-autopick-cb').checked; // v2.0: 开关

            recBtn.disabled = true;
            recBtn.textContent = '⏳ AI分析中...';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_recommend');
            fd.append('nonce', nonce);
            fd.append('content', topic);
            fd.append('mode', mode);
            fd.append('industry', 'general');

            var resultEl = getResultEl();
            var autopickHint = autopick ? '⚡ 自动选用Top1模式' : '📋 候选卡片模式';
            resultEl.innerHTML = '<div style="color:#4f46e5;font-size:12px;">🤖 AI引擎 [' + escapeHtml(mode) + '] 分析中 · ' + autopickHint + '...</div>';

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    recBtn.disabled = false;
                    recBtn.textContent = '🤖 AI推荐';
                    if (!res.success) {
                        resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;">❌ ' + escapeHtml(res.data && res.data.message || '推荐失败') + '</div>';
                        return;
                    }
                    var recs = (res.data && res.data.recommendations) || [];
                    if (recs.length === 0) {
                        resultEl.innerHTML = '<div style="color:#F59E0B;font-size:12px;">⚠️ 未找到匹配风格, 请尝试其他策略或调整内容</div>';
                        return;
                    }

                    // v2.0: 根据开关决定行为
                    if (autopick) {
                        // 自动选用Top1 (原AI自动适配行为)
                        var top1 = recs[0];
                        var sid = top1.style_id || top1.id || '';
                        var ss = getStyleSelect();
                        if (ss && sid) {
                            for (var i = 0; i < ss.options.length; i++) {
                                if (ss.options[i].value === sid) {
                                    ss.selectedIndex = i;
                                    ss.style.background = '#F4F4F5';
                                    setTimeout(function(){ ss.style.background = ''; }, 2000);
                                    break;
                                }
                            }
                        }
                    }

                    // 渲染候选卡片 (无论开关都展示, 方便用户切换)
                    var html = '<div style="font-size:11px;color:#4f46e5;margin-bottom:6px;font-weight:600;">🎯 Top' + recs.length + ' 推荐 (策略: ' + escapeHtml(mode) + ')' + (autopick ? ' · Top1已自动选中' : '') + '</div>';
                    html += '<div class="lk3-sfp-v2-rec-grid">';
                    recs.forEach(function(rec, idx) {
                        var score = rec.match_score || 0;
                        var isTop1 = idx === 0;
                        var cls = isTop1 ? 'lk3-sfp-v2-rec-card lk3-sfp-v2-rec-card-top1' : 'lk3-sfp-v2-rec-card lk3-sfp-v2-rec-card-other';
                        var label = isTop1 ? (autopick ? '✅ 已自动选中' : '⭐ Top1') : '点击选用';
                        html += '<div class="' + cls + '" data-style-id="' + escapeHtml(rec.style_id || rec.id || '') + '">';
                        html += '<div class="lk3-sfp-v2-rec-card-label" style="color:' + (isTop1 ? '#6366f1' : '#10B981') + ';">' + label + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-name">' + (idx + 1) + '. ' + escapeHtml(rec.name_cn || rec.style_id || rec.id || '') + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-cat">' + escapeHtml(rec.category || '') + '</div>';
                        html += '<div class="lk3-sfp-v2-rec-card-score">匹配分: <strong>' + score + '</strong></div>';
                        if (rec.reason) html += '<div class="lk3-sfp-v2-rec-card-reason">' + escapeHtml(rec.reason) + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    html += '<div style="font-size:10px;color:#A1A1AA;margin-top:6px;">💡 点击任意卡片可切换上方画风下拉</div>';
                    resultEl.innerHTML = html;

                    // 卡片点击 → 切换画风
                    panel.querySelectorAll('.lk3-sfp-v2-rec-card').forEach(function(card) {
                        card.addEventListener('click', function() {
                            var csid = this.dataset.styleId;
                            var ss = getStyleSelect();
                            if (ss) {
                                for (var j = 0; j < ss.options.length; j++) {
                                    if (ss.options[j].value === csid) {
                                        ss.selectedIndex = j;
                                        ss.style.background = '#F4F4F5';
                                        setTimeout(function(){ ss.style.background = ''; }, 2000);
                                        break;
                                    }
                                }
                            }
                        });
                    });
                })
                .catch(function(e) {
                    recBtn.disabled = false;
                    recBtn.textContent = '🤖 AI推荐';
                    resultEl.innerHTML = '<div style="color:#EF4444;font-size:12px;">❌ 网络错误: ' + escapeHtml(e.message) + '</div>';
                });
        });
    }
})();
</script>
