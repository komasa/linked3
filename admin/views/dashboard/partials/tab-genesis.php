<?php
/**
 * Dashboard partial: 漫画脚本 Genesis v10.0 — SEED-First 线性流水线重构
 *
 * v10.0 重构要点 (基于 /genesis 5部门3代演化):
 *   公理1: SEED是信息基(低熵), 剧本是熵增调度 → UI顺序必须 SEED→剧本→分镜
 *   公理2: SEED的"不可变/可变"二分是降维核心 → fixed/variable 显式UI化
 *   公理3: 线性5阶段流水线 → Stage0(SEED)→Stage1(剧本)→Stage2(配置)→Stage3(生成)→Stage4(质检)
 *
 * 兼容性: 保留所有现有 element ID 和 AJAX action, 仅重构 UI 呈现层
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-23
 */
if (!defined('ABSPATH')) exit;

// ============================================================
// PHP 数据准备 (保留原逻辑)
// ============================================================
$nonce_g  = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

$styles = [];
$stats  = [];
if (class_exists('GenesisAtomIndex')) {
    $idx    = GenesisAtomIndex::instance();
    $raw    = $idx->getStyles();
    // _index.json 返回 {version, total_styles, architecture, styles:{...}}
    // 漫画脚本只显示漫画视觉风格 (usage_code 以 S/Y/G 开头), 排除图示风格 (F开头)
    // 公理2: 漫画视觉基因 = S(摄影) + Y(插画/东方) + G(概念/西方), 不可混入图示基因(F)
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $uc = $sinfo['usage_code'] ?? '';
            // 只保留漫画视觉风格: S01-S99, Y01-Y99, G01-G99
            if (!preg_match('/^[SYG]\d+/', $uc)) continue;
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $styles[$sid] = $label;
        }
    }
    $stats = $idx->getStats();
}

// v10.0: SEED 分类定义 (6类, 对应公理2) — v11.0: 统一墨黑色头, 极简
$seed_categories = [
    'character' => ['icon' => '👤', 'label' => '角色', 'desc' => '人物外貌/性格/服装', 'color' => '#18181B'],
    'scene'     => ['icon' => '🏞️', 'label' => '场景', 'desc' => '地点/环境/氛围', 'color' => '#18181B'],
    'prop'      => ['icon' => '⚔️', 'label' => '道具', 'desc' => '关键物品/武器/信物', 'color' => '#18181B'],
    'style'     => ['icon' => '🎨', 'label' => '风格', 'desc' => '画风/色调/笔触', 'color' => '#18181B'],
    'brand'     => ['icon' => '🏷️', 'label' => '品牌', 'desc' => 'IP标识/水印/字体', 'color' => '#18181B'],
    'palette'   => ['icon' => '🌈', 'label' => '色板', 'desc' => '主色/辅色/情绪色', 'color' => '#18181B'],
];
?>

<!-- ============================================================ -->
<!-- CSS: v11.0 极简顶级设计系统 (参照 Linear/Vercel/Stripe) -->
<!-- 公理: 顶级设计 = 信息熵减 + 系统降维 -->
<!-- ============================================================ -->
<style>
.lk3-genesis-wrap{max-width:1400px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Noto Sans SC",sans-serif;color:#27272A;font-size:13px;line-height:1.6;}
.lk3-genesis-wrap *{box-sizing:border-box;}

/* 进度向导条 — 极简下划线式 */
.lk3-wizard{display:flex;align-items:center;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:6px;padding:12px 16px;margin-bottom:16px;position:sticky;top:32px;z-index:50;}
.lk3-wizard-step{display:flex;align-items:center;gap:6px;flex:1;cursor:pointer;transition:color 150ms ease;}
.lk3-wizard-step .lk3-ws-num{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;background:#F4F4F5;color:#A1A1AA;transition:all 150ms ease;flex-shrink:0;font-variant-numeric:tabular-nums;}
.lk3-wizard-step .lk3-ws-label{font-size:12px;font-weight:500;color:#A1A1AA;white-space:nowrap;}
.lk3-wizard-step.active .lk3-ws-num{background:#0F172A;color:#FFFFFF;}
.lk3-wizard-step.active .lk3-ws-label{color:#18181B;font-weight:600;}
.lk3-wizard-step.done .lk3-ws-num{background:#18181B;color:#FFFFFF;}
.lk3-wizard-step.done .lk3-ws-label{color:#52525B;}
.lk3-wizard-arrow{color:#D4D4D8;font-size:14px;margin:0 4px;flex-shrink:0;}

/* 阶段容器 — 零阴影, 边框分区 */
.lk3-stage{background:#FFFFFF;border:1px solid #E4E4E7;border-radius:6px;padding:20px;margin-bottom:16px;transition:opacity 200ms ease;}
.lk3-stage.disabled{opacity:.4;pointer-events:none;}
.lk3-stage-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #F4F4F5;}
.lk3-stage-title{font-size:14px;font-weight:600;margin:0;display:flex;align-items:center;gap:8px;color:#18181B;letter-spacing:-0.01em;}
.lk3-stage-title .lk3-stage-icon{font-size:16px;line-height:1;}
.lk3-stage-desc{font-size:12px;color:#71717A;margin:0 0 14px;line-height:1.6;}

/* SEED 中心卡片网格 */
.lk3-seed-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:16px;}
.lk3-seed-cat-card{border:1px solid #E4E4E7;border-radius:6px;overflow:hidden;transition:border-color 150ms ease;background:#FFFFFF;}
.lk3-seed-cat-card:hover{border-color:#A1A1AA;}
.lk3-seed-cat-header{display:flex;align-items:center;gap:8px;padding:10px 12px;font-size:13px;font-weight:600;color:#FFFFFF;cursor:pointer;background:#18181B;}
.lk3-seed-cat-header .lk3-seed-cat-count{margin-left:auto;background:rgba(255,255,255,.15);padding:1px 8px;border-radius:10px;font-size:11px;font-variant-numeric:tabular-nums;}
.lk3-seed-cat-body{padding:8px;max-height:240px;overflow-y:auto;background:#FAFAFA;}
.lk3-seed-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:4px;margin-bottom:6px;font-size:12px;transition:border-color 150ms ease;cursor:pointer;}
.lk3-seed-item:hover{border-color:#A1A1AA;background:#FAFAFA;}
.lk3-seed-item.selected{border-color:#0F172A;background:#F4F4F5;}
.lk3-seed-item .lk3-seed-lock{font-size:13px;flex-shrink:0;}
.lk3-seed-item .lk3-seed-name{flex:1;font-weight:500;color:#27272A;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.lk3-seed-item .lk3-seed-type{font-size:9px;padding:1px 5px;border-radius:3px;font-weight:600;flex-shrink:0;font-variant-numeric:tabular-nums;}
.lk3-seed-item .lk3-seed-type.fixed{background:#F4F4F5;color:#52525B;}
.lk3-seed-item .lk3-seed-type.variable{background:#FFFBEB;color:#B45309;}
.lk3-seed-item .lk3-seed-actions{display:flex;gap:4px;flex-shrink:0;}
.lk3-seed-item .lk3-seed-actions button{background:none;border:none;cursor:pointer;font-size:12px;padding:2px;line-height:1;border-radius:3px;color:#A1A1AA;transition:color 150ms ease;}
.lk3-seed-item .lk3-seed-actions button:hover{color:#18181B;}
.lk3-seed-empty{text-align:center;padding:16px;color:#A1A1AA;font-size:11px;font-style:italic;}

/* 表单控件 — 统一极简 */
.lk3-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;}
.lk3-form-label{display:block;font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:4px;}
.lk3-form-control{width:100%;padding:8px 10px;border:1px solid #D4D4D8;border-radius:4px;font-size:13px;background:#FFFFFF;transition:border-color 150ms ease,box-shadow 150ms ease;color:#27272A;font-family:inherit;}
.lk3-form-control:hover{border-color:#A1A1AA;}
.lk3-form-control:focus{outline:none;border-color:#0F172A;box-shadow:0 0 0 3px rgba(59,130,246,.1);}

/* 按钮 — 仅3级 (默认/主/危险) */
.lk3-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #D4D4D8;border-radius:4px;font-size:13px;font-weight:500;cursor:pointer;background:#FFFFFF;color:#52525B;transition:all 150ms ease;text-decoration:none;line-height:1.4;}
.lk3-btn:hover{background:#FAFAFA;border-color:#A1A1AA;color:#18181B;}
.lk3-btn-primary{background:#0F172A;color:#FFFFFF;border-color:#0F172A;}
.lk3-btn-primary:hover{background:#18181B;border-color:#18181B;color:#FFFFFF;}
.lk3-btn-success{background:#0F172A;color:#FFFFFF;border-color:#0F172A;}
.lk3-btn-success:hover{background:#18181B;border-color:#18181B;}
.lk3-btn-danger{background:#FFFFFF;color:#DC2626;border-color:#FECACA;}
.lk3-btn-danger:hover{background:#FEF2F2;border-color:#FCA5A5;}
.lk3-btn-sm{padding:4px 10px;font-size:11px;}
.lk3-btn-lg{padding:10px 24px;font-size:14px;}

/* SEED 标签 — 克制色块 */
.lk3-seed-tags{display:flex;flex-wrap:wrap;gap:6px;min-height:32px;align-items:center;padding:8px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:4px;}
.lk3-seed-tag{display:inline-flex;align-items:center;gap:4px;background:#F4F4F5;color:#3F3F46;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:500;border:1px solid #E4E4E7;}
.lk3-seed-tag .lk3-seed-tag-remove{cursor:pointer;color:#A1A1AA;font-weight:700;margin-left:2px;font-size:14px;line-height:1;transition:color 150ms ease;}
.lk3-seed-tag .lk3-seed-tag-remove:hover{color:#DC2626;}

/* 三轴选择器 */
.lk3-axis-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
.lk3-axis-card{padding:12px;border:1px solid #E4E4E7;border-radius:6px;background:#FAFAFA;}
.lk3-axis-card .lk3-axis-label{font-size:11px;font-weight:600;color:#71717A;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
.lk3-axis-hint{margin-top:10px;padding:8px 12px;background:#F4F4F5;border-left:2px solid #18181B;border-radius:0 4px 4px 0;font-size:12px;color:#3F3F46;}

/* 结果区 */
.lk3-result-panel{background:#FFFFFF;border:1px solid #E4E4E7;border-radius:6px;padding:16px;margin-top:12px;}
.lk3-panel-card{background:#FFFFFF;border:1px solid #E4E4E7;border-left:3px solid #18181B;border-radius:4px;padding:12px;margin-bottom:8px;}
.lk3-prompt-box{background:#18181B;border-radius:4px;padding:6px;margin-top:6px;}
.lk3-prompt-box textarea{width:100%;min-height:60px;font-size:11px;font-family:"SF Mono","Fira Code",monospace;background:#27272A;color:#E4E4E7;border:none;border-radius:3px;padding:8px;resize:vertical;line-height:1.5;}

/* SEED 脚本生成器 — 去除紫色渐变, 改为极简灰底 */
.lk3-seed-gen-box{margin-top:20px;padding:16px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;}
.lk3-seed-gen-box .lk3-seed-gen-title{display:flex;align-items:center;gap:8px;margin-bottom:10px;}
.lk3-seed-gen-box .lk3-seed-gen-title h4{margin:0;font-size:14px;color:#18181B;font-weight:600;}
.lk3-seed-gen-box .lk3-seed-gen-desc{font-size:12px;color:#52525B;margin:0 0 12px 0;line-height:1.6;}

/* 响应式 */
@media(max-width:768px){
    .lk3-wizard{flex-wrap:wrap;gap:8px;}
    .lk3-wizard-step{flex:1 1 40%;}
    .lk3-wizard-arrow{display:none;}
    .lk3-seed-grid{grid-template-columns:1fr;}
    .lk3-axis-grid{grid-template-columns:1fr;}
    .lk3-form-grid{grid-template-columns:1fr;}
}
</style>

<!-- ============================================================ -->
<!-- HTML: 线性5阶段流水线 -->
<!-- ============================================================ -->
<div class="lk3-genesis-wrap">

<!-- ===== 进度向导条 ===== -->
<div class="lk3-wizard" id="lk3-wizard">
    <div class="lk3-wizard-step active" data-stage="0" onclick="lk3GoStage(0)">
        <span class="lk3-ws-num">0</span>
        <span class="lk3-ws-label">🧬 SEED 中心</span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="1" onclick="lk3GoStage(1)">
        <span class="lk3-ws-num">1</span>
        <span class="lk3-ws-label">📝 剧本输入</span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="2" onclick="lk3GoStage(2)">
        <span class="lk3-ws-num">2</span>
        <span class="lk3-ws-label">⚙️ 生成配置</span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="3" onclick="lk3GoStage(3)">
        <span class="lk3-ws-num">3</span>
        <span class="lk3-ws-label">🎬 生成执行</span>
    </div>
    <span class="lk3-wizard-arrow">→</span>
    <div class="lk3-wizard-step" data-stage="4" onclick="lk3GoStage(4)">
        <span class="lk3-ws-num">4</span>
        <span class="lk3-ws-label">✅ 质检导出</span>
    </div>
</div>

<!-- ===== Stage 0: SEED 中心 (置顶) ===== -->
<div class="lk3-stage" id="lk3-stage-0">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🧬</span> Stage 0 · SEED 中心</h3>
        <div style="display:flex;gap:8px;">
            <button type="button" class="lk3-btn lk3-btn-sm" id="lk3-seed-refresh-cats">↻ 刷新</button>
            <button type="button" class="lk3-btn lk3-btn-sm lk3-btn-primary" id="lk3-seed-create-new">＋ 新建 SEED</button>
            <button type="button" class="lk3-btn lk3-btn-sm" id="lk3-seed-import-tpl">📥 从模板导入</button>
        </div>
    </div>
    <p class="lk3-stage-desc">
        <strong>公理1</strong>: SEED 是漫画一致性的信息基 (低熵), 必须先于剧本定义。
        <strong>公理2</strong>: 🔒<code>fixed</code>=不可变基因 (如人物样貌), 🔄<code>variable</code>=可变基因 (如每日服装)。
        点击卡片选择 SEED, 选中的将自动注入 Stage 3 的 Prompt 生成。
    </p>

    <!-- SEED 分类卡片网格 -->
    <div class="lk3-seed-grid" id="lk3-seed-grid">
        <?php foreach ($seed_categories as $cat_key => $cat_info): ?>
        <div class="lk3-seed-cat-card" data-category="<?php echo esc_attr($cat_key); ?>">
            <div class="lk3-seed-cat-header" style="background:<?php echo esc_attr($cat_info['color']); ?>;" onclick="lk3ToggleSeedCat(this)">
                <span style="font-size:16px;"><?php echo $cat_info['icon']; ?></span>
                <span><?php echo esc_html($cat_info['label']); ?> SEED</span>
                <span class="lk3-seed-cat-count" data-count="<?php echo esc_attr($cat_key); ?>">0</span>
            </div>
            <div class="lk3-seed-cat-body" id="lk3-seed-cat-<?php echo esc_attr($cat_key); ?>">
                <div class="lk3-seed-empty">暂无 <?php echo esc_html($cat_info['label']); ?> SEED<br>点击「新建 SEED」或「从剧本生成」</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== v10.0.2 新增: SEED 脚本生成模块 (解决先有鸡还是先有蛋) ===== -->
    <div class="lk3-seed-gen-box">
        <div class="lk3-seed-gen-title">
            <span style="font-size:18px;">🥚</span>
            <h4>SEED 脚本生成器 — 从全剧本一键生成 SEED 库</h4>
        </div>
        <p class="lk3-seed-gen-desc">
            <strong>解决"先有鸡还是先有蛋"</strong>: 粘贴任意剧本/故事, AI 自动提取角色/场景/道具/风格, 生成完整 SEED 库。<br>
            生成的 SEED 可共享用于 <strong>图文脚本</strong> / <strong>漫画脚本</strong> / <strong>视频脚本</strong>, 一次生成, 多场景复用。
        </p>
        <div class="lk3-form-grid" style="margin-bottom:10px;">
            <div>
                <label class="lk3-form-label">📋 脚本类型 (决定 SEED 提取侧重点)</label>
                <select id="lk3-seedgen-script-type" class="lk3-form-control">
                    <option value="comic">📖 漫画脚本 — 侧重视觉描述/角色外貌/场景氛围</option>
                    <option value="image">🖼️ 图文脚本 — 侧重产品特征/品牌元素/构图风格</option>
                    <option value="video">🎬 视频脚本 — 侧重镜头运动/场景转换/节奏情绪</option>
                </select>
            </div>
            <div>
                <label class="lk3-form-label">🎨 视觉风格 (影响 SEED 的画风基因)</label>
                <select id="lk3-seedgen-style" class="lk3-form-control">
                    <option value="auto">自动 (AI 根据剧本推断)</option>
                    <?php if (!empty($styles)): foreach ($styles as $sid => $sname): ?>
                    <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($sname); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>
        <textarea id="lk3-seedgen-script" class="lk3-form-control" rows="5" placeholder="粘贴完整剧本或故事... AI 将自动提取角色、场景、道具、风格, 生成 SEED 库。&#10;&#10;例如: 林隐是一名驱魔师, 25岁, 短黑发, 左眉有疤, 身穿黑色战术夹克。他常出没于雨夜古宅、荒野战场..." style="font-size:13px;line-height:1.6;margin-bottom:10px;"></textarea>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="lk3-btn lk3-btn-primary" id="lk3-seedgen-run">🥚 从剧本生成 SEED</button>
            <span id="lk3-seedgen-status" style="font-size:12px;color:#7C3AED;"></span>
        </div>
        <div id="lk3-seedgen-result" style="margin-top:10px;font-size:12px;"></div>
    </div>

    <!-- 已选 SEED 标签区 -->
    <div style="margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label class="lk3-form-label" style="margin:0;">📌 已选 SEED 引用 (将注入 Prompt)</label>
            <div style="display:flex;gap:6px;">
                <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-seed-pick">🔍 从库中选择</button>
                <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-seed-clear">✕ 清空</button>
            </div>
        </div>
        <!-- 保留原有 hidden input (JS兼容) -->
        <input type="hidden" id="linked3-genesis-seed-refs" value="">
        <div class="lk3-seed-tags" id="linked3-genesis-seed-selected-list">
            <span style="color:#A1A1AA;font-size:12px;" id="seed-empty-hint">未选择任何 SEED — 点击「从库中选择」或上方卡片</span>
        </div>
        <div style="font-size:11px;color:#71717A;margin-top:4px;">已选 <strong id="seed-ref-count">0</strong> 个 SEED</div>
    </div>
</div>

<!-- ===== Stage 1: 剧本输入 ===== -->
<div class="lk3-stage" id="lk3-stage-1">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">📝</span> Stage 1 · 剧本输入</h3>
        <div style="display:flex;gap:8px;">
            <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-test-btn">🔌 测试连接</button>
            <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-diag-btn">🔧 服务器诊断</button>
        </div>
    </div>
    <p class="lk3-stage-desc">粘贴或输入剧本/故事文本。AI 将自动拆解场景、角色、情节点。建议至少 200 字以获得最佳效果。</p>
    <textarea id="linked3-genesis-script" class="lk3-form-control" rows="8" placeholder="在此输入剧本或故事...&#10;&#10;示例:&#10;林隐站在天台上, 雨水打湿了他的风衣。他低头看着手中的怀表, 指针停在 11:47。&#10;'又迟了一步。' 他喃喃自语, 将怀表收入口袋, 转身消失在雨幕中..." style="font-size:13px;line-height:1.6;"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
        <span style="font-size:11px;color:#71717A;" id="lk3-script-stats">0 字</span>
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="lk3GoStage(2)">下一步: 生成配置 →</button>
    </div>
</div>

<!-- ===== Stage 2: 生成配置 ===== -->
<div class="lk3-stage" id="lk3-stage-2">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">⚙️</span> Stage 2 · 生成配置</h3>
    </div>
    <p class="lk3-stage-desc">配置画风、平台、分镜数量与三轴路由。三轴决定骨架模板的选择。</p>

    <!-- 基础配置 -->
    <div class="lk3-form-grid" style="margin-bottom:16px;">

        <!-- v2.0: 画风风格库融合面板 (内嵌画风下拉, 视觉绑定, 修复"看不见"; 合并双AI按钮) -->
        <?php
        $style_select_id        = 'linked3-genesis-style';
        $topic_input_id         = 'linked3-genesis-script';
        $visual_style_select_id = ''; // 漫画脚本无信息图技法下拉, 留空不联动
        $nonce                  = wp_create_nonce('linked3_content_writer');
        $ajax_url               = admin_url('admin-ajax.php');
        $instance               = 'genesis';
        include __DIR__ . '/style-fusion-panel-v2.php';
        ?>

        <div>
            <label class="lk3-form-label">🖥️ 目标平台</label>
            <select id="linked3-genesis-platform" class="lk3-form-control">
                <option value="midjourney">Midjourney</option>
                <option value="stable_diffusion">Stable Diffusion</option>
                <option value="dalle3">DALL·E 3</option>
                <option value="flux">Flux</option>
                <option value="niji">Niji Journey</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">📊 分镜数量 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(fixed模式严格生效)</span></label>
            <input type="number" id="linked3-genesis-panel-count" class="lk3-form-control" value="8" min="1" max="50">
        </div>
        <div>
            <label class="lk3-form-label">✂️ 分镜模式</label>
            <select id="linked3-genesis-split-mode" class="lk3-form-control">
                <option value="auto">auto (动态: AI按剧情拆分, 最多15)</option>
                <option value="fixed">fixed (固定: 严格按"分镜数量"生成)</option>
                <option value="sentence">sentence (按句: 每句1分镜)</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">📑 章节标记 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(留空=自动按段落拆分)</span></label>
            <input type="text" id="linked3-genesis-chapter-marker" class="lk3-form-control" value="" placeholder="留空=自动拆分; 或输入分隔符如: 第X章">
        </div>
    </div>

    <!-- v11.0: 漫画分镜布局+画幅比例+渲染技法 (参照图示脚本大格局补全) -->
    <div class="lk3-form-grid" style="margin-bottom:16px;">
        <div>
            <label class="lk3-form-label">📐 分镜布局 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(影响画面构图与节奏)</span></label>
            <select id="linked3-genesis-panel-layout" class="lk3-form-control">
                <option value="auto">🤖 自动适配 (AI根据剧情节奏选最佳布局)</option>
                <option value="grid-4">grid-4 四格网格 (经典漫画, 适合日常叙事)</option>
                <option value="grid-6">grid-6 六格网格 (信息密度高, 适合快节奏)</option>
                <option value="grid-8">grid-8 八格网格 (密集叙事, 适合动作戏)</option>
                <option value="splash">splash 全页大格 (冲击力强, 适合高潮/扉页)</option>
                <option value="full-width">full-width 通栏横幅 (宽幅场景, 适合风景/全景)</option>
                <option value="vertical-strip">vertical-strip 竖条漫 (韩式Webtoon, 适合手机阅读)</option>
                <option value="manga-classic">manga-classic 日漫经典 (2×3变格, 节奏感强)</option>
                <option value="bd-european">bd-european 欧漫条带 (3行横条, 适合法比BD)</option>
                <option value="cinematic-widescreen">cinematic-widescreen 电影宽屏 (16:9, 适合影视感)</option>
                <option value="dynamic-asymmetric">dynamic-asymmetric 动态非对称 (美漫英雄, 破格冲击)</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">🖼️ 画幅比例 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(单格画面比例)</span></label>
            <select id="linked3-genesis-aspect-ratio" class="lk3-form-control">
                <option value="3:4">3:4 竖版 (经典漫画单格)</option>
                <option value="1:1">1:1 方形 (社交媒体/Instagram)</option>
                <option value="4:3">4:3 横版 (传统漫画宽格)</option>
                <option value="16:9">16:9 宽屏 (电影感/影视分镜)</option>
                <option value="9:16">9:16 竖屏 (手机全屏/Webtoon)</option>
                <option value="2:3">2:3 竖版窄 (书籍/杂志封面)</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">🖌️ 渲染技法 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(影响画面质感)</span></label>
            <select id="linked3-genesis-rendering-tech" class="lk3-form-control">
                <option value="auto">🤖 自动 (跟随画风风格)</option>
                <option value="cel-shading">cel-shading 赛璐璐平涂 (日漫标准)</option>
                <option value="ink-wash">ink-wash 水墨渲染 (东方写意)</option>
                <option value="watercolor">watercolor 水彩渲染 (柔彩绘本)</option>
                <option value="oil-painting">oil-painting 油画质感 (厚重写实)</option>
                <option value="digital-painting">digital-painting 数码绘画 (现代主流)</option>
                <option value="pencil-sketch">pencil-sketch 铅笔素描 (草稿感)</option>
                <option value="halftone-print">halftone-print 半调印刷 (复古美漫)</option>
                <option value="flat-design">flat-design 扁平设计 (现代极简)</option>
                <option value="3d-render">3d-render 3D渲染 (CG质感)</option>
            </select>
        </div>
    </div>

    <!-- v9 三轴路由 (默认启用, 移除checkbox) -->
    <div id="linked3-genesis-v9-options" style="display:block;">
        <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="font-size:13px;font-weight:700;margin-bottom:4px;color:#52525B;">🎯 三轴路由 (v9 集成模式)</div>
            <div style="font-size:11px;color:#71717A;margin-bottom:10px;">💡 三轴决定骨架模板。选"无"可跳过该轴, 仅用画风风格控制画面。</div>
            <div class="lk3-axis-grid">
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label">L1 · 题材类型</div>
                    <select id="linked3-genesis-l1" class="lk3-form-control">
                        <option value="auto">自动检测</option>
                        <option value="none">无 (跳过L1)</option>
                        <option value="story">故事叙事</option>
                        <option value="documentary">纪录片</option>
                        <option value="commercial">商业广告</option>
                        <option value="art">艺术表达</option>
                    </select>
                </div>
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label">L2 · 视觉栏目</div>
                    <select id="linked3-genesis-l2" class="lk3-form-control">
                        <option value="auto">自动检测</option>
                        <option value="none">无 (跳过L2)</option>
                        <option value="documentary">纪录片摄影</option>
                        <option value="cyber">赛博朋克</option>
                        <option value="noir">黑色悬疑</option>
                        <option value="watercolor">水彩治愈</option>
                        <option value="floral">花系唯美</option>
                        <option value="guochao">国潮东方</option>
                        <option value="pet">萌宠可爱</option>
                        <option value="suspense">悬疑</option>
                        <option value="healing">治愈</option>
                    </select>
                </div>
                <div class="lk3-axis-card">
                    <div class="lk3-axis-label">L3 · 灵魂风格 <span style="font-size:10px;color:#A1A1AA;font-weight:normal;">(括号内为适用场景)</span></div>
                    <select id="linked3-genesis-l3" class="lk3-form-control">
                        <option value="auto">自动检测</option>
                        <option value="none">无 (跳过L3, 仅用画风风格)</option>
                        <optgroup label="─ 主流商业 ─">
                        <option value="cinematic">电影感 (影视剧照/品牌大片/高端商业)</option>
                        <option value="magazine">杂志感 (时尚封面/品牌广告/高端产品)</option>
                        <option value="xiaohongshu">小红书感 (种草图文/生活方式/美妆穿搭)</option>
                        <option value="documentary">纪实感 (新闻报道/人文纪实/故事叙事)</option>
                        </optgroup>
                        <optgroup label="─ 大师风格 ─">
                        <option value="miyazaki">宫崎骏 (治愈动漫/奇幻冒险/温暖童话)</option>
                        <option value="mucha">穆夏 (装饰海报/复古插画/唯美女性)</option>
                        <option value="klimt">克里姆特 (装饰艺术/金色奢华/情感表达)</option>
                        <option value="hopper">霍珀 (都市孤独/光影叙事/情绪场景)</option>
                        <option value="banksy">班克西 (涂鸦艺术/社会讽刺/街头文化)</option>
                        </optgroup>
                        <optgroup label="─ 传奇/小众 ─">
                        <option value="playboy_retro">花花公子复古 (复古性感/男性生活方式/奢华派对)</option>
                        <option value="warhol">安迪·沃霍尔 (波普艺术/重复复制/消费文化)</option>
                        <option value="dali">达利超现实 (超现实/梦境/潜意识探索)</option>
                        <option value="picasso">毕加索 (立体主义/抽象表达/艺术实验)</option>
                        <option value="basquiat">巴斯奎特 (新表现主义/街头艺术/原始力量)</option>
                        <option value="wes_anderson">韦斯·安德森 (对称构图/复古色调/quirky美学)</option>
                        <option value="tim_burton">蒂姆·伯顿 (哥特暗黑/怪诞童话/诡异可爱)</option>
                        </optgroup>
                        <optgroup label="─ 东方美学 ─">
                        <option value="ukiyoe">浮世绘 (和风古典/木版画/东方传统)</option>
                        <option value="song_dynasty">宋画意境 (水墨留白/文人雅趣/禅意山水)</option>
                        <option value="dunhuang">敦煌壁画 (佛教艺术/飞天纹样/西域色彩)</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            <div class="lk3-axis-hint" id="linked3-genesis-skeleton-hint">骨架路由: 故事叙事 × 纪录片摄影 × 电影感 → <strong>documentary_photo</strong></div>
        </div>
    </div>

    <!-- 保留 v9 checkbox (hidden, 默认checked, JS兼容) -->
    <input type="checkbox" id="linked3-genesis-v9-mode" checked style="display:none;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="lk3GoStage(1)">← 上一步</button>
        <button type="button" class="lk3-btn lk3-btn-sm lk3-btn-primary" onclick="lk3GoStage(3)">下一步: 生成执行 →</button>
    </div>
</div>

<!-- ===== Stage 3: 生成执行 ===== -->
<div class="lk3-stage" id="lk3-stage-3">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🎬</span> Stage 3 · 生成执行</h3>
        <span class="spinner is-active" id="linked3-genesis-spinner" style="display:none;float:none;margin:0;"></span>
        <span id="linked3-genesis-status" style="font-size:12px;color:#71717A;margin-left:8px;"></span>
    </div>
    <p class="lk3-stage-desc">确认配置后点击生成。系统将: Stage1 拆解剧本 → Stage2 批量生成 Prompt + PQS 质检。选中的 SEED 将自动注入每个 Prompt。</p>

    <!-- 配置摘要 -->
    <div style="background:#FAFAFA;border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;color:#52525B;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;">
            <div>🎨 风格: <strong id="lk3-summary-style">-</strong></div>
            <div>🖥️ 平台: <strong id="lk3-summary-platform">-</strong></div>
            <div>📊 分镜: <strong id="lk3-summary-panels">-</strong></div>
            <div>🧬 SEED: <strong id="lk3-summary-seeds">0</strong></div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;gap:12px;margin:20px 0;">
        <button type="button" class="lk3-btn lk3-btn-lg" onclick="lk3GoStage(2)">← 上一步</button>
        <button type="button" class="lk3-btn lk3-btn-primary lk3-btn-lg" id="linked3-genesis-gen">🎬 开始生成</button>
    </div>

    <!-- 结果区 (保留原有 ID) -->
    <div id="linked3-genesis-result" class="lk3-result-panel" style="min-height:60px;">
        <div style="text-align:center;color:#A1A1AA;padding:20px;font-size:13px;">点击「开始生成」启动漫画脚本生成流程</div>
    </div>
</div>

<!-- ===== Stage 4: 质检与导出 (结果区延伸, 由 renderResult 动态填充) ===== -->
<div class="lk3-stage" id="lk3-stage-4" style="display:none;">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">✅</span> Stage 4 · 质检与导出</h3>
    </div>
    <p class="lk3-stage-desc">PQS 13维质检报告 + 分镜预览 + 批量导出。不合格分镜可单独重新生成。</p>
    <div id="lk3-stage4-content"></div>
</div>

<!-- ===== SEED DNA 管理面板 (保留原有 ID, 默认隐藏) ===== -->
<div class="lk3-stage" id="linked3-genesis-seed-panel" style="display:none;">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🧬</span> 新建 SEED DNA</h3>
        <button type="button" class="lk3-btn lk3-btn-sm" onclick="document.getElementById('linked3-genesis-seed-panel').style.display='none';">✕ 关闭</button>
    </div>
    <p class="lk3-stage-desc">从剧本中提取角色/场景/色彩 DNA, 生成可复用的 SEED。也可手动创建。</p>
    <div class="lk3-form-grid" style="margin-bottom:12px;">
        <div>
            <label class="lk3-form-label">SEED 名称</label>
            <input type="text" id="linked3-genesis-seed-name" class="lk3-form-control" placeholder="如: 林隐-主角">
        </div>
        <div>
            <label class="lk3-form-label">基于已有 SEED (可选)</label>
            <select id="linked3-genesis-seed-select" class="lk3-form-control">
                <option value="">不使用 (全新创建)</option>
            </select>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <button type="button" class="lk3-btn lk3-btn-primary" id="linked3-genesis-seed-gen">🧬 AI 提取 Seed DNA</button>
        <button type="button" class="lk3-btn" id="linked3-genesis-seed-export">⬇️ 导出 JSON</button>
        <button type="button" class="lk3-btn lk3-btn-danger" id="linked3-genesis-seed-delete">🗑️ 删除选中</button>
        <button type="button" class="lk3-btn" id="linked3-genesis-seed-refresh" style="display:none;">↻ 刷新</button>
    </div>
    <div id="linked3-genesis-seed-result" style="font-size:12px;"></div>
</div>

</div><!-- /.lk3-genesis-wrap -->

<!-- ============================================================ -->
<!-- JS: 保留所有现有AJAX逻辑 + 新增SEED中心交互 + 阶段导航 -->
<!-- ============================================================ -->
<script>
(function(){
    'use strict';

    var nonce   = '<?php echo esc_js($nonce_g); ?>';
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';

    // ============================================================
    // v10.0 新增: 阶段导航系统
    // ============================================================
    var currentStage = 0;
    var stageCompleted = {0:false, 1:false, 2:false, 3:false, 4:false};

    window.lk3GoStage = function(stage) {
        currentStage = stage;
        // 更新向导条
        document.querySelectorAll('.lk3-wizard-step').forEach(function(step) {
            var s = parseInt(step.dataset.stage);
            step.classList.remove('active', 'done');
            if (s < stage) step.classList.add('done');
            else if (s === stage) step.classList.add('active');
        });
        // 滚动到对应阶段
        var target = document.getElementById('lk3-stage-' + stage);
        if (target) {
            target.scrollIntoView({behavior:'smooth', block:'start'});
        }
        // 更新配置摘要 (进入Stage3时)
        if (stage === 3) updateSummary();
    };

    window.lk3ToggleSeedCat = function(header) {
        var body = header.nextElementSibling;
        if (body.style.display === 'none') {
            body.style.display = 'block';
        } else {
            body.style.display = body.style.display === 'none' ? 'block' : 'none';
        }
    };

    function updateSummary() {
        var styleEl = document.getElementById('linked3-genesis-style');
        var platEl  = document.getElementById('linked3-genesis-platform');
        var panEl   = document.getElementById('linked3-genesis-panel-count');
        var sSum = document.getElementById('lk3-summary-style');
        var pSum = document.getElementById('lk3-summary-platform');
        var nSum = document.getElementById('lk3-summary-panels');
        var seedSum = document.getElementById('lk3-summary-seeds');
        if (sSum && styleEl) sSum.textContent = styleEl.options[styleEl.selectedIndex] ? styleEl.options[styleEl.selectedIndex].text : '-';
        if (pSum && platEl) pSum.textContent = platEl.options[platEl.selectedIndex] ? platEl.options[platEl.selectedIndex].text : '-';
        if (nSum && panEl) nSum.textContent = panEl.value;
        var refs = document.getElementById('linked3-genesis-seed-refs').value;
        var count = refs ? refs.split(',').filter(function(s){return s;}).length : 0;
        if (seedSum) seedSum.textContent = count;
    }

    // 剧本字数统计
    var scriptEl = document.getElementById('linked3-genesis-script');
    var statsEl = document.getElementById('lk3-script-stats');
    if (scriptEl && statsEl) {
        scriptEl.addEventListener('input', function() {
            statsEl.textContent = scriptEl.value.length + ' 字';
        });
    }

    // ============================================================
    // v10.0 新增: SEED 中心 — 加载并渲染分类卡片
    // ============================================================
    var selectedSeedIds = [];

    function loadSeedCenter() {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_list');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) return;
                renderSeedCenter(res.data.seeds || []);
                // 同步到原有 select (JS兼容)
                syncSeedSelect(res.data.seeds || []);
            })
            .catch(function(){ /* 静默失败, 不阻塞 */ });
    }

    function renderSeedCenter(seeds) {
        var categories = ['character','scene','prop','style','brand','palette'];
        var grouped = {};
        categories.forEach(function(c){ grouped[c] = []; });

        seeds.forEach(function(s) {
            var cat = s.category || s.seed_type || 'character';
            if (!grouped[cat]) cat = 'character';
            grouped[cat].push(s);
        });

        categories.forEach(function(cat) {
            var catStr = String(cat || '');
            var body = document.getElementById('lk3-seed-cat-' + catStr);
            var countEl = document.querySelector('[data-count="' + catStr + '"]');
            if (!body) return;
            var list = grouped[cat];
            if (countEl) countEl.textContent = list.length;

            if (list.length === 0) {
                body.innerHTML = '<div class="lk3-seed-empty" style="text-align:center;padding:20px;">' +
                    '<p style="font-size:13px;color:#71717A;margin:0 0 8px 0;">🧬 暂无 SEED</p>' +
                    '<p style="font-size:11px;color:#9ca3af;margin:0 0 12px 0;">SEED是角色/场景/道具等视觉DNA, 创建后可用于漫画/图示/视频脚本生成。</p>' +
                    '<button class="button button-small lk3-seed-new-btn" data-cat="' + escHtml(cat) + '" style="margin-right:6px;">+ 新建 SEED</button>' +
                    '<button class="button button-small lk3-seed-import-btn" data-cat="' + escHtml(cat) + '">📥 从剧本导入</button>' +
                    '</div>';
                return;
            }

            var html = '';
            list.forEach(function(s) {
                var isFixed = (s.lock === true || s.seed_type === 'fixed');
                var isSelected = selectedSeedIds.indexOf(s.seed_id) >= 0;
                var lockIcon = isFixed ? '🔒' : '🔄';
                var typeLabel = isFixed ? 'fixed' : 'variable';
                html += '<div class="lk3-seed-item' + (isSelected ? ' selected' : '') + '" data-seed-id="' + escapeHtml(s.seed_id) + '" onclick="lk3ToggleSeedSelect(\'' + escapeHtml(s.seed_id) + '\', \'' + escapeHtml(s.name || '') + '\')">';
                html += '<span class="lk3-seed-lock">' + lockIcon + '</span>';
                html += '<span class="lk3-seed-name">' + escapeHtml(s.name || '未命名') + '</span>';
                html += '<span class="lk3-seed-type ' + typeLabel + '">' + typeLabel + '</span>';
                html += '<span class="lk3-seed-actions">';
                html += '<button title="编辑" onclick="event.stopPropagation();lk3EditSeed(\'' + escapeHtml(s.seed_id) + '\')">✏️</button>';
                html += '<button title="删除" onclick="event.stopPropagation();lk3DeleteSeed(\'' + escapeHtml(s.seed_id) + '\')">🗑️</button>';
                html += '</span>';
                html += '</div>';
            });
            body.innerHTML = html;
        });
    }

    function syncSeedSelect(seeds) {
        var select = document.getElementById('linked3-genesis-seed-select');
        if (!select) return;
        var current = select.value;
        select.innerHTML = '<option value="">不使用 (全新创建)</option>';
        seeds.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s.seed_id;
            opt.textContent = s.name + (s.style_name ? ' (' + s.style_name + ')' : '') + (s.created_at ? ' - ' + s.created_at : '');
            select.appendChild(opt);
        });
        select.value = current;
    }

    window.lk3ToggleSeedSelect = function(seedId, seedName) {
        var idx = selectedSeedIds.indexOf(seedId);
        if (idx >= 0) {
            selectedSeedIds.splice(idx, 1);
        } else {
            selectedSeedIds.push(seedId);
        }
        // 更新 hidden input (兼容原有逻辑)
        document.getElementById('linked3-genesis-seed-refs').value = selectedSeedIds.join(',');
        // 更新标签区
        updateSeedSelectedList(selectedSeedIds);
        // 重新渲染卡片选中态
        loadSeedCenter();
    };

    window.lk3EditSeed = function(seedId) {
        // v10.1.0: 增强SEED编辑 — 弹窗显示DNA并可编辑
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_export');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    alert('获取SEED详情失败: ' + (res.data.message || ''));
                    return;
                }
                var seedData = {};
                try { seedData = JSON.parse(res.data.json || '{}'); } catch(e) {}
                showSeedEditModal(seedId, seedData);
            })
            .catch(function(e){
                alert('网络错误: ' + e.message);
            });
    };

    // v10.1.0: SEED编辑弹窗
    function showSeedEditModal(seedId, seedData) {
        // 移除已有弹窗
        var existing = document.getElementById('lk3-seed-edit-modal');
        if (existing) existing.remove();

        var visualDna = seedData.visual_dna || {};
        var personalityDna = seedData.personality_dna || {};
        var lock = seedData.lock || [];
        var priority = seedData.priority || {};
        var aiAdapter = seedData.ai_adapter || {};

        // 构建visual_dna可编辑字段
        var visualHtml = '';
        if (Object.keys(visualDna).length === 0) {
            visualHtml = '<div style="text-align:center;padding:16px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:4px;">' +
                '<p style="font-size:12px;color:#71717A;margin:0 0 8px 0;">🎨 暂无视觉DNA数据</p>' +
                '<p style="font-size:11px;color:#9ca3af;margin:0 0 10px 0;">视觉DNA定义角色的画风/色彩/构图等, 填写后可保证跨分镜一致性。</p>' +
                '<button class="button button-small" onclick="document.getElementById(\'lk3-seed-visual-dna-generate\') && document.getElementById(\'lk3-seed-visual-dna-generate\').click()">🤖 AI生成视觉DNA</button>' +
                '</div>';
        } else {
            Object.keys(visualDna).forEach(function(key) {
                var val = typeof visualDna[key] === 'object' ? JSON.stringify(visualDna[key]) : visualDna[key];
                visualHtml += '<div style="margin-bottom:6px;"><label style="font-size:11px;color:#52525B;display:block;margin-bottom:2px;">' + escapeHtml(key) + '</label>';
                visualHtml += '<textarea class="lk3-form-control" data-dna-key="' + escapeHtml(key) + '" style="font-size:11px;min-height:40px;">' + escapeHtml(val) + '</textarea></div>';
            });
        }

        // 构建AI适配字段
        var adapterHtml = '';
        ['mj','sd','flux','dalle'].forEach(function(platform) {
            var val = aiAdapter[platform] || '';
            adapterHtml += '<div style="margin-bottom:4px;"><label style="font-size:10px;color:#71717A;">' + platform.toUpperCase() + '</label>';
            adapterHtml += '<input type="text" class="lk3-form-control" data-adapter-key="' + platform + '" value="' + escapeHtml(val) + '" style="font-size:11px;"></div>';
        });

        var html = '<div id="lk3-seed-edit-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;">';
        html += '<div style="background:#fff;border-radius:10px;width:90%;max-width:600px;max-height:85vh;overflow-y:auto;padding:20px;">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
        html += '<h3 style="margin:0;font-size:16px;">🧬 SEED 编辑: ' + escapeHtml(seedData.title || seedId) + '</h3>';
        html += '<button onclick="document.getElementById(\'lk3-seed-edit-modal\').remove()" style="background:none;border:none;font-size:20px;cursor:pointer;">✕</button>';
        html += '</div>';

        // 基本信息
        html += '<div style="background:#FAFAFA;padding:10px;border-radius:6px;margin-bottom:12px;font-size:11px;">';
        html += '<div><strong>SEED ID:</strong> ' + escapeHtml(seedId) + '</div>';
        html += '<div><strong>分类:</strong> ' + escapeHtml(seedData.seed_category || '-') + ' | <strong>类型:</strong> ' + escapeHtml(seedData.seed_type || '-') + '</div>';
        html += '<div><strong>锁定项:</strong> ' + escapeHtml(Array.isArray(lock) ? lock.join(', ') : JSON.stringify(lock)) + '</div>';
        html += '</div>';

        // 视觉DNA
        html += '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#52525B;">👁️ 视觉DNA (Visual DNA)</div>';
        html += '<div style="font-size:10px;color:#A1A1AA;margin-bottom:8px;">💡 角色的外貌/服装/特征等视觉基因。修改后保存生效。</div>';
        html += visualHtml;
        html += '</div>';

        // AI适配
        html += '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:13px;font-weight:700;margin-bottom:6px;color:#52525B;">🤖 AI平台适配Prompt</div>';
        html += '<div style="font-size:10px;color:#A1A1AA;margin-bottom:8px;">💡 各生图平台的专属Prompt片段, 会注入到对应平台的输出中。</div>';
        html += adapterHtml;
        html += '</div>';

        // 操作按钮
        html += '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid #E4E4E7;">';
        html += '<button class="lk3-btn lk3-btn-sm" onclick="document.getElementById(\'lk3-seed-edit-modal\').remove()">取消</button>';
        html += '<button class="lk3-btn lk3-btn-sm lk3-btn-primary" onclick="lk3SaveSeedEdit(\'' + escapeHtml(seedId) + '\')">💾 保存修改</button>';
        html += '</div>';

        html += '</div></div>';

        document.body.insertAdjacentHTML('beforeend', html);
    }

    window.lk3SaveSeedEdit = function(seedId) {
        // 收集编辑后的数据
        var visualDna = {};
        document.querySelectorAll('#lk3-seed-edit-modal textarea[data-dna-key]').forEach(function(el) {
            var key = el.dataset.dnaKey;
            var val = el.value;
            // 尝试解析JSON
            try { visualDna[key] = JSON.parse(val); } catch(e) { visualDna[key] = val; }
        });
        var aiAdapter = {};
        document.querySelectorAll('#lk3-seed-edit-modal input[data-adapter-key]').forEach(function(el) {
            aiAdapter[el.dataset.adapterKey] = el.value;
        });

        var fd = new FormData();
        fd.append('action', 'linked3_save_seed');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);
        fd.append('visual_dna', JSON.stringify(visualDna));
        fd.append('ai_adapter', JSON.stringify(aiAdapter));

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    alert('✓ SEED 已保存');
                    document.getElementById('lk3-seed-edit-modal').remove();
                    loadSeedCenter();
                } else {
                    alert('保存失败: ' + (res.data.message || ''));
                }
            })
            .catch(function(e){
                alert('网络错误: ' + e.message);
            });
    };

    window.lk3DeleteSeed = function(seedId) {
        if (!confirm('确定删除此 SEED? 此操作不可撤销。')) return;
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_seed_delete');
        fd.append('nonce', nonce);
        fd.append('seed_id', seedId);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    // 从已选列表移除
                    var idx = selectedSeedIds.indexOf(seedId);
                    if (idx >= 0) selectedSeedIds.splice(idx, 1);
                    document.getElementById('linked3-genesis-seed-refs').value = selectedSeedIds.join(',');
                    updateSeedSelectedList(selectedSeedIds);
                    loadSeedCenter();
                } else {
                    alert('删除失败: ' + (res.data && res.data.message ? res.data.message : ''));
                }
            });
    };

    // 保留原有 updateSeedSelectedList 函数 (增强版)
    function updateSeedSelectedList(seeds) {
        var listEl = document.getElementById('linked3-genesis-seed-selected-list');
        var countEl = document.getElementById('seed-ref-count');
        var emptyHint = document.getElementById('seed-empty-hint');
        if (!listEl) return;

        if (!seeds || seeds.length === 0) {
            listEl.innerHTML = '<span style="color:#A1A1AA;font-size:12px;" id="seed-empty-hint">未选择任何 SEED — 点击「从库中选择」或上方卡片</span>';
            if (countEl) countEl.textContent = '0';
            return;
        }

        var html = '';
        // seeds 可能是 ID 数组, 也可能是对象数组
        seeds.forEach(function(s) {
            var id = typeof s === 'string' ? s : (s.seed_id || s.id || '');
            var name = typeof s === 'string' ? s : (s.name || s.seed_id || id);
            html += '<span class="lk3-seed-tag">';
            html += '<span>🧬 ' + escapeHtml(name) + '</span>';
            html += '<span class="lk3-seed-tag-remove" onclick="lk3ToggleSeedSelect(\'' + escapeHtml(id) + '\',\'' + escapeHtml(name) + '\')">×</span>';
            html += '</span>';
        });
        listEl.innerHTML = html;
        if (countEl) countEl.textContent = seeds.length;
    }

    // 从库中选择按钮 (复用原有逻辑)
    var seedPickBtn = document.getElementById('linked3-genesis-seed-pick');
    if (seedPickBtn) {
        seedPickBtn.addEventListener('click', function() {
            // 滚动到 SEED 网格区
            document.getElementById('lk3-seed-grid').scrollIntoView({behavior:'smooth', block:'start'});
        });
    }

    // 清空按钮
    var seedClearBtn = document.getElementById('linked3-genesis-seed-clear');
    if (seedClearBtn) {
        seedClearBtn.addEventListener('click', function() {
            selectedSeedIds = [];
            document.getElementById('linked3-genesis-seed-refs').value = '';
            updateSeedSelectedList([]);
            loadSeedCenter();
        });
    }

    // 新建 SEED 按钮
    var seedCreateBtn = document.getElementById('lk3-seed-create-new');
    if (seedCreateBtn) {
        seedCreateBtn.addEventListener('click', function() {
            var panel = document.getElementById('linked3-genesis-seed-panel');
            if (panel) {
                panel.style.display = 'block';
                panel.scrollIntoView({behavior:'smooth'});
                var nameInput = document.getElementById('linked3-genesis-seed-name');
                if (nameInput) { nameInput.value = ''; nameInput.focus(); }
            }
        });
    }

    // 从模板导入按钮
    var seedImportBtn = document.getElementById('lk3-seed-import-tpl');
    if (seedImportBtn) {
        seedImportBtn.addEventListener('click', function() {
            if (!confirm('从 lib/seeds/ 模板库导入预设 SEED 到 CPT? \n(角色5个 + 场景5个 + 风格3个)')) return;
            var btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ 导入中...';
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_import_templates');
            fd.append('nonce', nonce);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '📥 从模板导入';
                    if (res.success) {
                        alert('导入成功: ' + (res.data.count || 0) + ' 个 SEED');
                        loadSeedCenter();
                    } else {
                        alert('导入失败: ' + (res.data && res.data.message ? res.data.message : '未知错误') + '\n\n(如该AJAX未注册, 请手动在 SEED CPT 管理页创建)');
                        // 降级: 直接刷新列表
                        loadSeedCenter();
                    }
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '📥 从模板导入';
                    // AJAX 可能未注册, 静默降级
                    loadSeedCenter();
                });
        });
    }

    // 刷新按钮
    var seedRefreshCatsBtn = document.getElementById('lk3-seed-refresh-cats');
    if (seedRefreshCatsBtn) {
        seedRefreshCatsBtn.addEventListener('click', loadSeedCenter);
    }

    // ============================================================
    // v10.0.2 新增: SEED 脚本生成器 — 从全剧本一键生成 SEED 库
    // ============================================================
    var seedGenBtn = document.getElementById('lk3-seedgen-run');
    if (seedGenBtn) {
        seedGenBtn.addEventListener('click', function() {
            var script = document.getElementById('lk3-seedgen-script').value.trim();
            if (!script || script.length < 20) {
                alert('请输入至少 20 字的剧本内容');
                return;
            }
            var scriptType = document.getElementById('lk3-seedgen-script-type').value;
            var styleId = document.getElementById('lk3-seedgen-style').value;
            var statusEl = document.getElementById('lk3-seedgen-status');
            var resultEl = document.getElementById('lk3-seedgen-result');
            var btn = this;

            btn.disabled = true;
            btn.textContent = '⏳ 生成中...';
            statusEl.textContent = 'AI 正在分析剧本, 提取角色/场景/道具/风格 DNA...';
            resultEl.innerHTML = '<div style="text-align:center;padding:16px;color:#7C3AED;"><div class="spinner is-active" style="float:none;margin:0 auto 8px;"></div>正在生成 SEED 库...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_generate');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', styleId === 'auto' ? 'documentary_photo' : styleId);
            fd.append('seed_name', 'SEED库_' + scriptType + '_' + new Date().toLocaleDateString());
            // v10.0.2: 传递脚本类型, 后端可根据类型调整提取侧重点
            fd.append('script_type', scriptType);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    btn.disabled = false;
                    btn.textContent = '🥚 从剧本生成 SEED';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        resultEl.innerHTML = '<div style="color:#DC2626;padding:8px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '<br><br><strong>建议:</strong> 检查 AI API 配置, 或先使用「从模板导入」加载预设 SEED。</div>';
                        return;
                    }
                    // v10.0.3: 适配新的6类SEED生成结果
                    var dna = res.data.dna || {};
                    var created = res.data.created || {};
                    var seedId = res.data.seed_id || '';
                    var html = '<div style="background:#F4F4F5;padding:12px;border-radius:6px;border:1px solid #86efac;">';
                    html += '<div style="font-weight:700;color:#16a34a;margin-bottom:8px;">✓ SEED 库生成成功! 共 ' + (created.total || 0) + ' 个 SEED</div>';

                    // 显示6类SEED生成结果
                    if (created.characters && created.characters.length) {
                        html += '<div style="margin-bottom:6px;"><strong>👤 角色 (' + created.characters.length + '):</strong> ';
                        html += created.characters.map(function(c) { return escapeHtml(c.name || c.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.scenes && created.scenes.length) {
                        html += '<div style="margin-bottom:6px;"><strong>🏞️ 场景 (' + created.scenes.length + '):</strong> ';
                        html += created.scenes.map(function(s) { return escapeHtml(s.name || s.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.props && created.props.length) {
                        html += '<div style="margin-bottom:6px;"><strong>⚔️ 道具 (' + created.props.length + '):</strong> ';
                        html += created.props.map(function(p) { return escapeHtml(p.name || p.seed_id); }).join(' · ');
                        html += '</div>';
                    }
                    if (created.style) {
                        html += '<div style="margin-bottom:6px;"><strong>🎨 风格:</strong> ' + escapeHtml(created.style.name || created.style.seed_id) + '</div>';
                    }
                    if (created.palette) {
                        html += '<div style="margin-bottom:6px;"><strong>🌈 色板:</strong> 已生成</div>';
                    }
                    if (created.brand) {
                        html += '<div style="margin-bottom:6px;"><strong>🏷️ 品牌:</strong> ' + escapeHtml(created.brand.name || created.brand.seed_id) + '</div>';
                    }

                    // 兼容旧格式 (dna.characters等)
                    if (!created.characters && dna.characters && dna.characters.length) {
                        html += '<div style="margin-bottom:6px;"><strong>👤 角色 (DNA):</strong> ';
                        html += dna.characters.map(function(c) { return escapeHtml(c.name || '未知'); }).join(' · ');
                        html += '</div>';
                    }

                    html += '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #86efac;font-size:11px;color:#16a34a;">';
                    html += '✅ ' + (created.total || 0) + ' 个 SEED 已入库, 可在上方卡片中查看。现在可以进入 Stage 1 输入剧本, 开始生成漫画/图文/视频分镜。';
                    html += '</div>';
                    html += '</div>';

                    statusEl.textContent = '✓ 生成成功';
                    statusEl.style.color = '#16a34a';
                    resultEl.innerHTML = html;

                    // 刷新 SEED 中心
                    loadSeedCenter();
                })
                .catch(function(e) {
                    btn.disabled = false;
                    btn.textContent = '🥚 从剧本生成 SEED';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    resultEl.innerHTML = '<div style="color:#DC2626;padding:8px;">✗ ' + escapeHtml(e.message) + '<br><br>可能是 AJAX 请求失败。请检查网络连接, 或查看 PHP error_log。</div>';
                });
        });
    }

    // 页面加载时拉取 SEED 列表
    loadSeedCenter();

    // ============================================================
    // 保留原有逻辑: v9 三轴联动
    // ============================================================
    var l1 = document.getElementById('linked3-genesis-l1');
    var l2 = document.getElementById('linked3-genesis-l2');
    var l3 = document.getElementById('linked3-genesis-l3');
    var skeletonHint = document.getElementById('linked3-genesis-skeleton-hint');

    function updateSkeletonHint() {
        if (!l1 || !l2 || !l3 || !skeletonHint) return;
        var v1 = l1.value, v2 = l2.value, v3 = l3.value;
        var labels = {l1:{}, l2:{}, l3:{}};
        if (l1.selectedOptions[0]) labels.l1 = l1.selectedOptions[0].text;
        if (l2.selectedOptions[0]) labels.l2 = l2.selectedOptions[0].text;
        if (l3.selectedOptions[0]) labels.l3 = l3.selectedOptions[0].text;
        // v10.0.5: 处理"无"和"自动"选项
        var parts = [];
        if (v1 !== 'none') parts.push(labels.l1);
        if (v2 !== 'none') parts.push(labels.l2);
        if (v3 !== 'none') parts.push(labels.l3);
        var skeleton = 'documentary_photo';
        if (v2 !== 'none' && v2 !== 'auto' && v3 !== 'none' && v3 !== 'auto') {
            skeleton = v2 + '_' + v3;
        } else if (v2 !== 'none' && v2 !== 'auto') {
            skeleton = v2;
        }
        var hint = parts.length > 0 ? parts.join(' × ') + ' → <strong>' + escapeHtml(skeleton) + '</strong>' : '<strong>仅用画风风格控制 (三轴已跳过)</strong>';
        skeletonHint.innerHTML = '骨架路由: ' + hint;
    }
    if (l1) l1.addEventListener('change', updateSkeletonHint);
    if (l2) l2.addEventListener('change', updateSkeletonHint);
    if (l3) l3.addEventListener('change', updateSkeletonHint);
    updateSkeletonHint();

    // ============================================================
    // 保留原有逻辑: 错误分类系统
    // ============================================================
    function classifyError(e, httpStatus) {
        var msg = (e && e.message) ? e.message : String(e);
        var info = {type:'unknown', title:'生成失败', detail:msg, causes:[], actions:[]};

        if (httpStatus === 0 || /NetworkError|Failed to fetch|network/i.test(msg)) {
            info.type = 'network';
            info.title = '网络连接失败';
            info.detail = '无法连接到服务器。可能是网络中断或服务器未响应。';
            info.causes = ['网络连接不稳定', '服务器超时 (PHP max_execution_time)', '防火墙拦截'];
            info.actions = ['重试', '检查网络连接', '查看 PHP error_log'];
        } else if (httpStatus === 403) {
            info.type = 'forbidden';
            info.title = '权限被拒 (403)';
            info.detail = '服务器拒绝了请求。可能是 nonce 验证失败或权限不足。';
            info.causes = ['Nonce 过期', '用户角色无权限', '安全插件拦截'];
            info.actions = ['刷新页面重试', '检查用户角色权限', '暂时禁用安全插件测试'];
        } else if (httpStatus === 500) {
            info.type = 'server_error';
            info.title = '服务器内部错误 (500)';
            info.detail = '服务器遇到内部错误。通常是 PHP Fatal Error。';
            info.causes = ['PHP Fatal Error', '内存不足', '插件冲突'];
            info.actions = ['查看 PHP error_log', '增加 PHP memory_limit', '禁用其他插件排查冲突'];
        } else if (httpStatus === 504 || /timeout/i.test(msg)) {
            info.type = 'timeout';
            info.title = '请求超时';
            info.detail = '服务器响应超时。生成任务可能仍在后台运行。';
            info.causes = ['AI API 响应慢', 'PHP max_execution_time 过短', '分镜数过多'];
            info.actions = ['减少分镜数重试', '增加 PHP 超时', '查看任务是否在后台完成'];
        } else if (/api|key|unauthorized|401/i.test(msg)) {
            info.type = 'api_error';
            info.title = 'AI API 错误';
            info.detail = 'AI 服务商返回错误。可能是 API Key 无效或余额不足。';
            info.causes = ['API Key 无效', 'API 余额不足', 'API 限流'];
            info.actions = ['检查 API Key 配置', '查看 API 余额', '更换 AI 服务商'];
        } else {
            info.type = 'unknown';
            info.title = '生成失败';
            info.detail = msg;
            info.causes = ['未知原因'];
            info.actions = ['重试', '查看 PHP error_log', '联系技术支持'];
        }
        return info;
    }

    function renderClassifiedError(errInfo, rawMsg) {
        var html = '<div style="background:#fef2f2;border:1px solid #FECACA;border-radius:8px;padding:16px;">';
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">';
        html += '<span style="font-size:24px;">❌</span>';
        html += '<div><div style="font-size:15px;font-weight:700;color:#DC2626;">' + escapeHtml(errInfo.title) + '</div>';
        html += '<div style="font-size:12px;color:#991b1b;">类型: ' + escapeHtml(errInfo.type) + '</div></div>';
        html += '</div>';
        html += '<div style="font-size:13px;color:#7f1d1d;margin-bottom:10px;">' + escapeHtml(errInfo.detail) + '</div>';
        if (errInfo.causes.length) {
            html += '<div style="font-size:12px;margin-bottom:8px;"><strong>可能原因:</strong><ul style="margin:4px 0 0 20px;">';
            errInfo.causes.forEach(function(c){ html += '<li>' + escapeHtml(c) + '</li>'; });
            html += '</ul></div>';
        }
        if (errInfo.actions.length) {
            html += '<div style="font-size:12px;margin-bottom:10px;"><strong>建议操作:</strong><ul style="margin:4px 0 0 20px;color:#16a34a;">';
            errInfo.actions.forEach(function(a){ html += '<li>' + escapeHtml(a) + '</li>'; });
            html += '</ul></div>';
        }
        html += '<div style="display:flex;gap:8px;margin-top:12px;">';
        html += '<button type="button" class="lk3-btn lk3-btn-primary lk3-btn-sm" id="linked3-genesis-retry">↻ 重试</button>';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-test-conn">🔌 测试连接</button>';
        html += '</div>';
        if (rawMsg) {
            html += '<details style="margin-top:10px;"><summary style="font-size:11px;color:#A1A1AA;cursor:pointer;">原始错误信息</summary>';
            html += '<pre style="font-size:10px;background:#18181B;color:#E4E4E7;padding:8px;border-radius:4px;overflow-x:auto;margin-top:6px;">' + escapeHtml(rawMsg) + '</pre></details>';
        }
        html += '</div>';
        return html;
    }

    // ============================================================
    // 保留原有逻辑: 测试连接
    // ============================================================
    function testConnection() {
        var btn = document.getElementById('linked3-genesis-test-btn');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ 测试中...'; }
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_test_connection');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (btn) { btn.disabled = false; btn.textContent = '🔌 测试连接'; }
                if (res.success) {
                    alert('✓ 连接正常!\n\nAI 服务商: ' + (res.data.provider || 'unknown') + '\n模型: ' + (res.data.model || 'unknown') + '\n延迟: ' + (res.data.latency || '?') + 'ms');
                } else {
                    alert('✗ 连接失败: ' + (res.data && res.data.message ? res.data.message : '未知错误'));
                }
            })
            .catch(function(e){
                if (btn) { btn.disabled = false; btn.textContent = '🔌 测试连接'; }
                alert('✗ 请求失败: ' + e.message);
            });
    }

    // ============================================================
    // 保留原有逻辑: 生成按钮 (v9模式 + 经典模式)
    // ============================================================
    var genBtn = document.getElementById('linked3-genesis-gen');
    var spinner = document.getElementById('linked3-genesis-spinner');
    var statusEl = document.getElementById('linked3-genesis-status');
    var result = document.getElementById('linked3-genesis-result');
    var cancelBtn = null; // 可扩展
    var currentJobId = null;
    var pollTimer = null;

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var script = document.getElementById('linked3-genesis-script').value.trim();
            if (!script) {
                alert('请先在 Stage 1 输入剧本');
                lk3GoStage(1);
                return;
            }
            var panelCount = document.getElementById('linked3-genesis-panel-count').value || 8;

            // v9 模式 (默认启用)
            var v9Mode = document.getElementById('linked3-genesis-v9-mode');
            var useV9 = v9Mode ? v9Mode.checked : true;

            if (useV9) {
                runV9Mode(script);
                return;
            }

            // 经典模式
            runClassicMode(script, panelCount);
        });
    }

    // v9 模式生成
    function runV9Mode(script) {
        genBtn.disabled = true;
        spinner.style.display = 'inline-block';
        statusEl.textContent = 'Stage 1: 拆解剧本...';
        statusEl.style.color = '#2271b1';
        result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在拆解剧本, 提取语义核节点...</div>';

        var l1 = document.getElementById('linked3-genesis-l1').value;
        var l2 = document.getElementById('linked3-genesis-l2').value;
        var l3 = document.getElementById('linked3-genesis-l3').value;
        var seedRefs = document.getElementById('linked3-genesis-seed-refs').value;

        var fd = new FormData();
        fd.append('action', 'linked3_genesis_v9_stage1');
        fd.append('nonce', nonce);
        fd.append('script', script);
        // v10.0.2 修复: 后端期望 l1_type/l2_column/l3_soul, 不是 l1/l2/l3
        fd.append('l1_type', l1);
        fd.append('l2_column', l2);
        fd.append('l3_soul', l3);
        fd.append('seed_refs', seedRefs);
        fd.append('style', document.getElementById('linked3-genesis-style').value);
        fd.append('platform', document.getElementById('linked3-genesis-platform').value);
        // v10.0.3 Bug2修复: 传递panel_count和split_mode, 后端按此控制beats数量
        fd.append('panel_count', document.getElementById('linked3-genesis-panel-count').value);
        fd.append('split_mode', document.getElementById('linked3-genesis-split-mode').value);
        // v11.0: 漫画分镜布局+画幅比例+渲染技法 (参照图示脚本大格局补全)
        fd.append('panel_layout', (document.getElementById('linked3-genesis-panel-layout')||{}).value || 'auto');
        fd.append('aspect_ratio', (document.getElementById('linked3-genesis-aspect-ratio')||{}).value || '3:4');
        fd.append('rendering_tech', (document.getElementById('linked3-genesis-rendering-tech')||{}).value || 'auto');
        // v10.0.2 修复: 后端期望 gen_mode 参数
        fd.append('gen_mode', 'local');

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    var errInfo = classifyError(new Error(res.data.message || 'Stage 1 失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res.data.message);
                    bindErrorButtons();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ Stage 1 失败';
                    statusEl.style.color = '#DC2626';
                    return;
                }
                // Stage 1 成功, 进入 Stage 2
                statusEl.textContent = 'Stage 2: 批量生成 Prompt...';
                result.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>Stage 1 完成, 拆解出 ' + (res.data.beat_count || (res.data.beats ? res.data.beats.length : 0)) + ' 个分镜节点<br>正在批量生成 Prompt...</div>';

                // v10.0.2 修复: 后端 Stage2 期望 beats/characters/theme/skeleton_id/gen_mode
                // 不是 cores/cores_data/job_id
                var fd2 = new FormData();
                fd2.append('action', 'linked3_genesis_v9_stage2');
                fd2.append('nonce', nonce);
                fd2.append('beats', JSON.stringify(res.data.beats || []));
                fd2.append('characters', JSON.stringify(res.data.characters || []));
                fd2.append('theme', res.data.theme || '');
                fd2.append('skeleton_id', res.data.skeleton_id || 'documentary_photo');
                fd2.append('style', document.getElementById('linked3-genesis-style').value);
                fd2.append('platform', document.getElementById('linked3-genesis-platform').value);
                fd2.append('seed_refs', seedRefs);
                fd2.append('gen_mode', 'local');

                return fetch(ajaxUrl, {method:'POST', body:fd2, credentials:'same-origin'});
            })
            .then(function(r){ if (r) return r.json(); })
            .then(function(res2){
                if (!res2) return;
                spinner.style.display = 'none';
                genBtn.disabled = false;
                if (res2.success) {
                    statusEl.textContent = '✓ 生成完成';
                    statusEl.style.color = '#00a32a';
                    renderResult({success:true, data:res2.data}, result);
                    // 显示 Stage 4
                    var stage4 = document.getElementById('lk3-stage-4');
                    if (stage4) {
                        stage4.style.display = 'block';
                        stageCompleted[3] = true;
                    }
                } else {
                    var errInfo = classifyError(new Error(res2.data.message || 'Stage 2 失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res2.data.message);
                    bindErrorButtons();
                    statusEl.textContent = '✗ Stage 2 失败';
                    statusEl.style.color = '#DC2626';
                }
            })
            .catch(function(e){
                spinner.style.display = 'none';
                genBtn.disabled = false;
                var errInfo = classifyError(e, null);
                result.innerHTML = renderClassifiedError(errInfo, e.message);
                bindErrorButtons();
                statusEl.textContent = '✗ 错误';
                statusEl.style.color = '#DC2626';
            });
    }

    // 经典模式生成 (异步任务)
    function runClassicMode(script, panelCount) {
        genBtn.disabled = true;
        spinner.style.display = 'inline-block';
        statusEl.textContent = '启动任务...';
        statusEl.style.color = '#2271b1';

        var fd = new FormData();
        fd.append('action', 'linked3_genesis_start_job');
        fd.append('nonce', nonce);
        fd.append('script', script);
        fd.append('style', document.getElementById('linked3-genesis-style').value);
        fd.append('platform', document.getElementById('linked3-genesis-platform').value);
        fd.append('panel_count', panelCount);
        fd.append('split_mode', document.getElementById('linked3-genesis-split-mode').value);
        fd.append('chapter_marker', document.getElementById('linked3-genesis-chapter-marker').value);
        // v11.0: 漫画分镜布局+画幅比例+渲染技法
        fd.append('panel_layout', (document.getElementById('linked3-genesis-panel-layout')||{}).value || 'auto');
        fd.append('aspect_ratio', (document.getElementById('linked3-genesis-aspect-ratio')||{}).value || '3:4');
        fd.append('rendering_tech', (document.getElementById('linked3-genesis-rendering-tech')||{}).value || 'auto');
        var seedSelect = document.getElementById('linked3-genesis-seed-select');
        if (seedSelect && seedSelect.value) fd.append('seed_id', seedSelect.value);
        // v10.0: 传递多 SEED 引用
        var seedRefs = document.getElementById('linked3-genesis-seed-refs').value;
        if (seedRefs) fd.append('seed_refs', seedRefs);

        var controller = new AbortController();
        var timeoutId = setTimeout(function(){ controller.abort(); }, 10000);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin', signal: controller.signal})
            .then(function(r){
                clearTimeout(timeoutId);
                if (!r.ok) {
                    return r.text().then(function(t){
                        var msg = 'HTTP ' + r.status;
                        try { var j = JSON.parse(t); msg = (j.data && j.data.message) ? j.data.message : msg; } catch(e) { msg += ': ' + t.substring(0, 300); }
                        var err = new Error(msg); err.httpStatus = r.status; throw err;
                    });
                }
                return r.json();
            })
            .then(function(res){
                if (!res.success) {
                    var errInfo = classifyError(new Error(res.data.message || '启动失败'), null);
                    result.innerHTML = renderClassifiedError(errInfo, res.data.message);
                    bindErrorButtons();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ 启动失败';
                    statusEl.style.color = '#DC2626';
                    return;
                }
                currentJobId = res.data.job_id;
                statusEl.textContent = '任务运行中 (job: ' + currentJobId.substring(0, 12) + '...)';
                pollJob(currentJobId);
            })
            .catch(function(e){
                clearTimeout(timeoutId);
                var errInfo = classifyError(e, e.httpStatus || null);
                result.innerHTML = renderClassifiedError(errInfo, e.message);
                bindErrorButtons();
                spinner.style.display = 'none';
                genBtn.disabled = false;
                statusEl.textContent = '✗ 启动失败';
                statusEl.style.color = '#DC2626';
            });
    }

    function pollJob(jobId) {
        var fd = new FormData();
        fd.append('action', 'linked3_genesis_poll_job');
        fd.append('nonce', nonce);
        fd.append('job_id', jobId);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (!res.success) {
                    stopPolling();
                    showError(res.data && res.data.message ? res.data.message : '轮询失败');
                    return;
                }
                var data = res.data;
                updateProgress(data);
                if (data.status === 'done') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✓ 完成';
                    statusEl.style.color = '#00a32a';
                    renderResult({success:true, data:data.result}, result);
                    var stage4 = document.getElementById('lk3-stage-4');
                    if (stage4) { stage4.style.display = 'block'; stageCompleted[3] = true; }
                } else if (data.status === 'error') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '✗ 错误';
                    statusEl.style.color = '#DC2626';
                    var errInfo = {type:'job_error', title:'生成失败: ' + (data.error_class || 'Error'), detail:data.error || '未知错误', causes:[], actions:['重试','检查 API 配置','查看 PHP error_log']};
                    result.innerHTML = renderClassifiedError(errInfo, data.error);
                    bindErrorButtons();
                } else if (data.status === 'cancelled') {
                    stopPolling();
                    spinner.style.display = 'none';
                    genBtn.disabled = false;
                    statusEl.textContent = '已取消';
                    statusEl.style.color = '#666';
                } else {
                    pollTimer = setTimeout(function(){ pollJob(jobId); }, 2000);
                }
            })
            .catch(function(e){
                if (!pollJob._retries) pollJob._retries = 0;
                pollJob._retries++;
                if (pollJob._retries < 3) {
                    pollTimer = setTimeout(function(){ pollJob(jobId); }, 3000);
                } else {
                    stopPolling();
                    showError('轮询失败 (连续 3 次网络错误): ' + e.message);
                }
            });
    }

    function updateProgress(data) {
        if (data.progress !== undefined) {
            statusEl.textContent = '进度: ' + data.progress + '% — ' + (data.stage || '');
        }
    }

    function stopPolling() {
        if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    }

    function showError(msg) {
        spinner.style.display = 'none';
        genBtn.disabled = false;
        statusEl.textContent = '✗ 错误';
        statusEl.style.color = '#DC2626';
        var errInfo = classifyError(new Error(msg), null);
        result.innerHTML = renderClassifiedError(errInfo, msg);
        bindErrorButtons();
    }

    function bindErrorButtons() {
        var retryBtn = document.getElementById('linked3-genesis-retry');
        if (retryBtn) retryBtn.addEventListener('click', function(){ genBtn.click(); });
        var testBtn = document.getElementById('linked3-genesis-test-conn');
        if (testBtn) testBtn.addEventListener('click', testConnection);
    }

    // ============================================================
    // 保留原有逻辑: 测试连接按钮 + 诊断按钮
    // ============================================================
    var testBtnTop = document.getElementById('linked3-genesis-test-btn');
    if (testBtnTop) testBtnTop.addEventListener('click', testConnection);

    var diagBtn = document.getElementById('linked3-genesis-diag-btn');
    if (diagBtn) {
        diagBtn.addEventListener('click', function(){
            var btn = this;
            btn.disabled = true;
            btn.textContent = '诊断中...';
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_server_diagnostic');
            fd.append('nonce', nonce);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '🔧 服务器诊断';
                    if (!res.success) { alert('诊断失败: ' + (res.data.message || '')); return; }
                    var d = res.data;
                    var msg = '=== 服务器诊断报告 ===\n\n';
                    msg += '【PHP】\n  版本: ' + d.php.version + ' (SAPI: ' + d.php.sapi + ')\n  max_execution_time: ' + d.php.max_execution_time + 's\n  memory_limit: ' + d.php.memory_limit + '\n\n';
                    msg += '【curl】\n  启用: ' + (d.curl.enabled ? '✓' : '✗') + ' (v' + d.curl.version + ')\n  multi: ' + (d.curl.multi_enabled ? '✓' : '✗') + '\n\n';
                    msg += '【WordPress】\n  版本: ' + d.wordpress.version + '\n  WP_DEBUG: ' + (d.wordpress.wp_debug ? '开' : '关') + '\n\n';
                    msg += '【服务器】\n  软件: ' + d.server.software + '\n  fastcgi_finish_request: ' + (d.server.fastcgi_finish ? '✓' : '✗') + '\n\n';
                    msg += '【Genesis 类加载】\n';
                    if (d.genesis && d.genesis.classes_loaded) {
                        Object.keys(d.genesis.classes_loaded).forEach(function(k){
                            msg += '  ' + k + ': ' + (d.genesis.classes_loaded[k] ? '✓' : '✗') + '\n';
                        });
                    }
                    msg += '\n【预检结果】\n  ' + (d.genesis && d.genesis.preflight ? (d.genesis.preflight.ok ? '✓ 通过' : '✗ 失败: ' + d.genesis.preflight.message) : 'N/A') + '\n';
                    if (d.recommendations) {
                        msg += '\n【建议】\n';
                        d.recommendations.forEach(function(r){ msg += '  ' + r + '\n'; });
                    }
                    alert(msg);
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🔧 服务器诊断';
                    alert('诊断请求失败: ' + e.message + '\n\n这本身就是一个信号 — 说明 AJAX 端点可能未注册或 PHP 有 Fatal Error。');
                });
        });
    }

    // ============================================================
    // 保留原有逻辑: Seed DNA 系统 (生成/导出/删除)
    // ============================================================
    var seedBtn = document.getElementById('linked3-genesis-seed-btn');
    if (seedBtn) {
        seedBtn.addEventListener('click', function(){
            var panel = document.getElementById('linked3-genesis-seed-panel');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                loadSeedList();
            } else {
                panel.style.display = 'none';
            }
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
                syncSeedSelect(res.data.seeds || []);
                renderSeedCenter(res.data.seeds || []);
            });
    }

    var seedGenBtn = document.getElementById('linked3-genesis-seed-gen');
    if (seedGenBtn) {
        seedGenBtn.addEventListener('click', function(){
            var script = document.getElementById('linked3-genesis-script').value.trim();
            if (!script) { alert('请先在 Stage 1 输入剧本'); lk3GoStage(1); return; }
            var seedName = document.getElementById('linked3-genesis-seed-name').value.trim() || '未命名 Seed';
            var styleId = document.getElementById('linked3-genesis-style').value;
            var btn = this;
            btn.disabled = true;
            btn.textContent = '🧬 生成中...';
            var resultEl = document.getElementById('linked3-genesis-seed-result');
            resultEl.innerHTML = '<p style="color:#666;">AI 分析剧本, 提取角色/场景/色彩 DNA...</p>';

            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_generate');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', styleId);
            fd.append('seed_name', seedName);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    btn.disabled = false;
                    btn.textContent = '🧬 AI 提取 Seed DNA';
                    if (!res.success) {
                        resultEl.innerHTML = '<p style="color:#DC2626;">✗ ' + escapeHtml(res.data.message || '失败') + '</p>';
                        return;
                    }
                    var dna = res.data.dna;
                    var html = '<div style="background:#F4F4F5;padding:8px;border-radius:4px;">';
                    html += '<p><strong>✓ Seed DNA 生成成功</strong> (ID: ' + res.data.seed_id + ')</p>';
                    if (dna.characters && dna.characters.length) {
                        html += '<p><strong>角色:</strong> ' + dna.characters.map(function(c){ return c.name + '(' + (c.appearance||'') + ')'; }).join(', ') + '</p>';
                    }
                    if (dna.scenes && dna.scenes.length) {
                        html += '<p><strong>场景:</strong> ' + dna.scenes.map(function(s){ return s.name; }).join(', ') + '</p>';
                    }
                    if (dna.color_palette) {
                        html += '<p><strong>色彩:</strong> ' + JSON.stringify(dna.color_palette) + '</p>';
                    }
                    html += '</div>';
                    resultEl.innerHTML = html;
                    loadSeedCenter();
                })
                .catch(function(e){
                    btn.disabled = false;
                    btn.textContent = '🧬 AI 提取 Seed DNA';
                    resultEl.innerHTML = '<p style="color:#DC2626;">✗ ' + escapeHtml(e.message) + '</p>';
                });
        });
    }

    var seedExportBtn = document.getElementById('linked3-genesis-seed-export');
    if (seedExportBtn) {
        seedExportBtn.addEventListener('click', function(){
            var seedId = document.getElementById('linked3-genesis-seed-select').value;
            if (!seedId) { alert('请先选择一个 Seed'); return; }
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_export');
            fd.append('nonce', nonce);
            fd.append('seed_id', seedId);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (!res.success) { alert('导出失败'); return; }
                    var blob = new Blob([res.data.json], {type:'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'seed-dna-' + seedId + '.json';
                    a.click();
                    setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
                });
        });
    }

    var seedDeleteBtn = document.getElementById('linked3-genesis-seed-delete');
    if (seedDeleteBtn) {
        seedDeleteBtn.addEventListener('click', function(){
            var seedId = document.getElementById('linked3-genesis-seed-select').value;
            if (!seedId) { alert('请先选择一个 Seed'); return; }
            if (!confirm('确定删除此 Seed DNA?')) return;
            var fd = new FormData();
            fd.append('action', 'linked3_genesis_seed_delete');
            fd.append('nonce', nonce);
            fd.append('seed_id', seedId);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if (res.success) { alert('已删除'); loadSeedCenter(); }
                    else alert('删除失败');
                });
        });
    }

    // ============================================================
    // 保留原有逻辑: renderResult (结果渲染)
    // ============================================================
    function renderResult(res, el) {
        if (!res.success) {
            el.innerHTML = '<div class="notice notice-error inline"><p><strong>✗ 生成失败:</strong> ' + escapeHtml(res.data.message || '未知错误') + '</p></div>';
            return;
        }
        var d = res.data || {};
        var panels = d.panels || [];
        var total = d.total_panels || 0;
        var sceneCount = d.total_scenes || 0;

        if (total === 0) {
            var html = '<div class="notice notice-error inline" style="padding:14px;">';
            html += '<p><strong>✗ 生成 0 个分镜</strong> — 任务完成但未产出任何分镜</p>';
            html += '<div style="margin-top:10px;font-size:12px;color:#666;">';
            html += '<p><strong>诊断信息:</strong></p><ul style="margin-left:20px;">';
            html += '<li>FP 剥骨节点数: ' + (d.fp_cores || 0) + '</li>';
            html += '<li>并发模式: ' + escapeHtml(d.parallel_mode || 'unknown') + '</li>';
            if (d.error) html += '<li>错误: ' + escapeHtml(d.error) + '</li>';
            html += '</ul></div>';
            html += '<div style="margin-top:10px;font-size:12px;"><p><strong>建议操作:</strong></p><ul style="margin-left:20px;color:#16a34a;">';
            html += '<li>点击「🔌 测试连接」验证 API 是否正常</li>';
            html += '<li>点击「🔧 服务器诊断」检查配置</li>';
            html += '<li>查看 PHP error_log 获取详细错误</li>';
            html += '<li>尝试输入更长的剧本 (至少 100 字)</li>';
            html += '</ul></div>';
            html += '<p style="margin-top:12px;"><button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-retry">↻ 重试</button></p>';
            html += '</div>';
            el.innerHTML = html;
            var retryBtn = document.getElementById('linked3-genesis-retry');
            if (retryBtn) retryBtn.addEventListener('click', function(){ genBtn.click(); });
            return;
        }

        var html = '';
        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 生成成功</strong> — ' + sceneCount + ' 个场景, <strong>' + total + '</strong> 个分镜';
        if (d.target_panels) html += ' <span style="font-size:11px;color:#666;">(目标: ' + d.target_panels + (d.is_auto ? ' · auto 动态' : '') + ')</span>';
        html += '<span style="font-size:11px;color:#666;margin-left:10px;">| 风格: ' + escapeHtml(d.style || '') + ' | 平台: ' + escapeHtml(d.platform || '') + '</span>';
        if (d.pipeline) html += '<div style="margin-top:4px;font-size:10px;color:#2271b1;">📋 流程: ' + escapeHtml(d.pipeline) + '</div>';
        if (d.fp_cores) html += '<div style="margin-top:6px;font-size:11px;color:#7c3aed;">🦴 FP剥骨: 提纯 ' + d.fp_cores + ' 个语义核节点 → 每节点独立 AI 调用生成画面 Prompt</div>';
        if (d.ai_generated_count !== undefined) {
            var mode = d.parallel_mode || 'unknown';
            var modeLabel = mode === 'curl_multi' ? '⚡ curl_multi 真并发' : (mode === 'serial' ? '🔄 串行降级' : mode);
            var elapsed = d.parallel_elapsed_ms || 0;
            var ai = d.ai_generated_count || 0;
            var retry = d.ai_retry_count || 0;
            var local = d.local_fallback_count || 0;
            var qualityColor = (ai + retry) > local ? '#16a34a' : ((ai + retry) > 0 ? '#F59E0B' : '#DC2626');
            html += '<div style="margin-top:4px;font-size:11px;color:' + qualityColor + ';">';
            html += '🤖 ' + modeLabel + ' (' + (elapsed / 1000).toFixed(1) + 's) — AI优质: ' + ai + ' 个';
            if (retry > 0) html += ', <span style="color:#7c3aed;">AI重试成功: ' + retry + ' 个</span>';
            html += ', 本地兜底: ' + local + ' 个';
            html += '</div>';
        }
        if (d.v7_manifest) {
            var m = d.v7_manifest;
            html += '<div style="margin-top:6px;font-size:11px;color:#2563eb;">🔧 v7流水线: 变异' + (m.survivors + m.culled) + '个 → 存活' + m.survivors + '个 → 绞杀' + m.culled + '个 → 锁定' + m.total + '个</div>';
        }
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;padding:10px;background:#f9fafb;border-radius:6px;">';
        html += '<strong>📦 批量操作:</strong> ';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-copy-all">📋 复制全部 Prompt</button> ';
        html += '<button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-download-all">⬇️ 下载全部</button>';
        html += '</div>';

        // 分镜卡片
        panels.forEach(function(p, idx) {
            var sceneColor = ['#4A90E2','#F5A623','#7ED321','#D0506E','#9013FE','#50C8D6'][idx % 6];
            html += '<div class="lk3-panel-card" style="border-left-color:' + sceneColor + ';">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">';
            html += '<div>';
            html += '<span style="background:' + sceneColor + ';color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;font-size:11px;">' + escapeHtml(p.panel_id) + '</span> ';
            html += '<span style="font-weight:600;font-size:13px;">' + escapeHtml(p.location || '') + '</span>';
            html += '<span style="font-size:11px;color:#666;margin-left:6px;">' + escapeHtml(p.shot || '') + '/' + escapeHtml(p.angle || '') + '/' + escapeHtml(p.comp || '') + '</span>';
            if (p.prompt_source === 'ai_retry') {
                html += ' <span style="display:inline-block;background:#F4F4F5;color:#7c3aed;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI重试</span>';
            } else if (p.prompt_source === 'ai' && p.ai_degraded) {
                html += ' <span style="display:inline-block;background:#FECACA;color:#b91c1c;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI劣化</span>';
            } else if (p.prompt_source === 'ai') {
                html += ' <span style="display:inline-block;background:#dcfce7;color:#16a34a;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">AI</span>';
            } else if (p.prompt_source === 'local') {
                html += ' <span style="display:inline-block;background:#FEF3C7;color:#d97706;padding:1px 5px;border-radius:3px;font-size:9px;font-weight:bold;">本地</span>';
            }
            html += '</div>';
            html += '<button type="button" class="lk3-btn lk3-btn-sm linked3-genesis-copy" data-idx="' + idx + '">📋 复制</button>';
            html += '</div>';
            if (p.action) html += '<div style="font-size:12px;color:#3F3F46;margin-bottom:3px;"><strong>动作:</strong> ' + escapeHtml(p.action) + '</div>';
            if (p.mood) html += '<div style="font-size:11px;color:#71717A;margin-bottom:3px;"><strong>氛围:</strong> ' + escapeHtml(p.mood) + '</div>';
            if (p.core_info || p.plot_point) {
                html += '<div style="background:#faf5ff;border-left:3px solid #7c3aed;padding:4px 8px;margin:4px 0;border-radius:3px;font-size:11px;">';
                if (p.core_info) html += '<div style="color:#7c3aed;"><strong>🦴 语义核:</strong> ' + escapeHtml(p.core_info) + '</div>';
                if (p.plot_point) html += '<div style="color:#9333ea;margin-top:2px;"><strong>📍 情节点:</strong> ' + escapeHtml(p.plot_point) + '</div>';
                html += '</div>';
            }
            if (p.character_details && p.character_details.length > 0) {
                html += '<div style="margin-bottom:4px;">';
                p.character_details.forEach(function(c) {
                    html += '<span style="display:inline-block;background:#E0F2FE;color:#0369A1;padding:1px 6px;border-radius:3px;font-size:10px;margin-right:3px;">' + escapeHtml(c.id) + ' ' + escapeHtml(c.role) + '</span>';
                });
                html += '</div>';
            }
            html += '<div class="lk3-prompt-box">';
            html += '<textarea readonly class="linked3-genesis-prompt" data-idx="' + idx + '">' + escapeHtml(p.prompt_with_params || p.prompt_en || '') + '</textarea>';
            html += '</div>';
            if (p.pqs) {
                var pp = p.pqs.passed || 0;
                var tt = p.pqs.total || 0;
                html += '<div style="margin-top:3px;font-size:10px;">PQS: ' + pp + '/' + tt + ' ' + (pp === tt ? '✅' : '⚠️') + '</div>';
            }
            if (p.v7_pipeline) {
                var v7Status = p.v7_status || 'unknown';
                var v7Score = p.v7_score || 0;
                var v7Color = v7Status === 'locked' ? '#16a34a' : (v7Status === 'fallback' ? '#F59E0B' : '#71717A');
                html += '<div style="margin-top:2px;font-size:10px;color:' + v7Color + ';">🔧 v7: ' + v7Status + ' (score: ' + v7Score + '/40)</div>';
            }
            html += '</div>';
        });

        el.innerHTML = html;

        // 绑定复制
        el.querySelectorAll('.linked3-genesis-copy').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = btn.dataset.idx;
                var ta = el.querySelector('.linked3-genesis-prompt[data-idx="' + idx + '"]');
                if (ta) {
                    navigator.clipboard.writeText(ta.value).then(function() {
                        btn.textContent = '✓ 已复制';
                        setTimeout(function() { btn.textContent = '📋 复制'; }, 1500);
                    });
                }
            });
        });

        var copyAll = document.getElementById('linked3-genesis-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = panels.map(function(p) {
                    return '# ' + p.panel_id + ' ' + p.location + '\n' + (p.prompt_with_params || p.prompt_en || '');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + panels.length + ' 个分镜 Prompt');
                });
            });
        }

        var dlBtn = document.getElementById('linked3-genesis-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = panels.map(function(p) {
                    return '# ' + p.panel_id + ' ' + p.location + '\n' + (p.prompt_with_params || p.prompt_en || '');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'genesis-panels-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }
    }

    // ============================================================
    // 工具函数
    // ============================================================
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }

    // 暴露给 onclick 使用的函数
    window.lk3EscapeHtml = escapeHtml;

})();
</script>
