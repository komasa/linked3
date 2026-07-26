<?php
/**
 * 改写润色子面板 v17.1 — 全系统XX化架构 (人类化只是子集)
 *
 * v17.1 架构:
 *   XX化体系: 人物化(任正非/刘小排/雷军/张一鸣/罗翔/吴敬琏) + 行业大拿 + 古典文学
 *   改写: 语义保真/同义重构/视角转换/降重去重
 *   润色: 语法修正/用词升级/节奏优化/逻辑强化
 *   扩写: 细节填充/案例补充/论证展开/场景描写
 *   缩写: 核心提取/冗余删除/精炼表达/TL;DR
 *   人类化: G1脱壳/G2变异/G3坍缩/情绪注入/口语盐化/瑕疵植入
 *
 * @package Linked3
 * @version 17.2.0
 */
if (!defined('ABSPATH')) exit;
$nonce_rw = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载风格DNA
$writing_styles = [];
if (class_exists('SystemInstructionBuilder')) {
    $writing_styles = \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder::get_style_options();
}
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('✏️ 改写润色 — 全系统XX化架构', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;">
        6大类30+模式。XX化(人物化)是人类化的超集——不只是去AI味,而是注入特定人物的思想DNA。
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('📝 原文', 'linked3'); ?></label>
            <textarea id="rewrite-input" class="linked3-eco-input" rows="10" style="width:100%;font-size:13px;line-height:1.6;" placeholder="<?php echo esc_attr__('粘贴需要处理的文本...', 'linked3'); ?>"></textarea>
            <div style="font-size:11px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('字数:', 'linked3'); ?><span id="rewrite-input-count">0</span></div>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('✨ 结果', 'linked3'); ?></label>
            <textarea id="rewrite-output" class="linked3-eco-input" rows="10" style="width:100%;font-size:13px;line-height:1.6;background:#FAFAFA;" placeholder="<?php echo esc_attr__('处理结果将显示在这里...', 'linked3'); ?>" readonly></textarea>
            <div style="font-size:11px;color:#A1A1AA;margin-top:4px;font-variant-numeric:tabular-nums;"><?php echo esc_html__('字数:', 'linked3'); ?><span id="rewrite-output-count">0</span><?php echo esc_html__('· 变化:', 'linked3'); ?><span id="rewrite-change">0</span>%</div>
        </div>
    </div>

    <!-- 模式选择 (v17.1: 6大类30+模式) -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('🎯 处理模式', 'linked3'); ?></label>
            <select class="linked3-eco-select" id="rewrite-mode" style="width:100%;">
                <optgroup label="🧬 XX化 (人物化 — 注入思想DNA)">
                    <option value="renzhenfei"><?php echo esc_html__('任正非化 (危机+灰度+熵减)', 'linked3'); ?></option>
                    <option value="liuxiaopai"><?php echo esc_html__('刘小排化 (极简+反共识+真诚)', 'linked3'); ?></option>
                    <option value="leijun"><?php echo esc_html__('雷军化 (性价比+工程师+口语)', 'linked3'); ?></option>
                    <option value="zhangyiming"><?php echo esc_html__('张一鸣化 (理性+算法+延迟满足)', 'linked3'); ?></option>
                    <option value="luoxiang"><?php echo esc_html__('罗翔化 (法理+人文+自嘲)', 'linked3'); ?></option>
                    <option value="wujinglian"><?php echo esc_html__('吴敬琏化 (制度+历史+忧患)', 'linked3'); ?></option>
                </optgroup>
                <optgroup label="✏️ 改写">
                    <option value="rewrite_fidelity"><?php echo esc_html__('语义保真改写', 'linked3'); ?></option>
                    <option value="rewrite_synonym"><?php echo esc_html__('同义重构', 'linked3'); ?></option>
                    <option value="rewrite_perspective"><?php echo esc_html__('视角转换', 'linked3'); ?></option>
                    <option value="rewrite_dedup"><?php echo esc_html__('降重去重', 'linked3'); ?></option>
                </optgroup>
                <optgroup label="💎 润色">
                    <option value="polish_grammar"><?php echo esc_html__('语法修正', 'linked3'); ?></option>
                    <option value="polish_vocabulary"><?php echo esc_html__('用词升级', 'linked3'); ?></option>
                    <option value="polish_rhythm"><?php echo esc_html__('节奏优化', 'linked3'); ?></option>
                    <option value="polish_logic"><?php echo esc_html__('逻辑强化', 'linked3'); ?></option>
                </optgroup>
                <optgroup label="📈 扩写">
                    <option value="expand_detail"><?php echo esc_html__('细节填充', 'linked3'); ?></option>
                    <option value="expand_argument"><?php echo esc_html__('论证展开', 'linked3'); ?></option>
                    <option value="expand_scene"><?php echo esc_html__('场景描写', 'linked3'); ?></option>
                    <option value="expand_case"><?php echo esc_html__('案例补充', 'linked3'); ?></option>
                </optgroup>
                <optgroup label="📉 缩写">
                    <option value="shorten_core"><?php echo esc_html__('核心提取', 'linked3'); ?></option>
                    <option value="shorten_redundancy"><?php echo esc_html__('冗余删除', 'linked3'); ?></option>
                    <option value="shorten_tldr">TL;DR</option>
                    <option value="shorten_bullets"><?php echo esc_html__('要点提炼', 'linked3'); ?></option>
                </optgroup>
                <optgroup label="👤 人类化 (反AI脱壳)">
                    <option value="humanize_g1"><?php echo esc_html__('G1初代脱壳 (剥骨+破壁+绞杀+缝合)', 'linked3'); ?></option>
                    <option value="humanize_g2"><?php echo esc_html__('G2重组变异 (倒装+断句+降维)', 'linked3'); ?></option>
                    <option value="humanize_g3"><?php echo esc_html__('G3终极坍缩 (0%AI+100%混沌)', 'linked3'); ?></option>
                    <option value="humanize_emotion"><?php echo esc_html__('情绪注入 (消除机械中立)', 'linked3'); ?></option>
                    <option value="humanize_oral"><?php echo esc_html__('口语盐化 (注入偏见+自嘲)', 'linked3'); ?></option>
                    <option value="humanize_flaw"><?php echo esc_html__('瑕疵植入 (漏冠词/介词)', 'linked3'); ?></option>
                </optgroup>
            </select>
        </div>
        <div>
            <label class="lk3-form-label"><?php echo esc_html__('⚡ 强度', 'linked3'); ?></label>
            <select class="linked3-eco-select" id="rewrite-intensity" style="width:100%;">
                <option value="light"><?php echo esc_html__('轻度 (最小改动)', 'linked3'); ?></option>
                <option value="medium" selected><?php echo esc_html__('中度 (平衡改动)', 'linked3'); ?></option>
                <option value="heavy"><?php echo esc_html__('重度 (深度重构)', 'linked3'); ?></option>
            </select>
        </div>
    </div>

    <!-- v17.2: 人类化可组合模块 (勾选多个将叠加执行) -->
    <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;padding:10px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:6px;"><?php echo esc_html__('🧬 人类化模块叠加 (可选, 勾选多个将组合执行)', 'linked3'); ?></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-g1" class="rw-humanize-module" value="g1"> G1初代脱壳
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-g2" class="rw-humanize-module" value="g2"> G2重组变异
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-g3" class="rw-humanize-module" value="g3"> G3终极坍缩
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-emotion" class="rw-humanize-module" value="emotion"> 💉情绪注入
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-oral" class="rw-humanize-module" value="oral"> 🧂口语盐化
            </label>
            <label style="display:inline-flex;align-items:center;gap:3px;padding:4px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;font-size:11px;cursor:pointer;">
                <input type="checkbox" id="rw-h-flaw" class="rw-humanize-module" value="flaw"> 🔧瑕疵植入
            </label>
        </div>
        <div style="font-size:10px;color:#A1A1AA;margin-top:4px;"><?php echo esc_html__('💡 与上方处理模式叠加。例如: 选"语义保真改写"+勾选G1+G2+G3 = 改写后完整3代脱壳', 'linked3'); ?></div>
    </div>

    <!-- 模式说明 (动态) -->
    <div id="rewrite-mode-desc" style="padding:10px 12px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;margin-bottom:12px;font-size:12px;color:#52525B;line-height:1.6;">
        选择处理模式后, 这里会显示该模式的详细说明。
    </div>

    <!-- 操作按钮 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="linked3-eco-btn linked3-eco-btn-primary" id="rewrite-run"><?php echo esc_html__('✏️ 执行', 'linked3'); ?></button>
        <button class="linked3-eco-btn" id="rewrite-copy"><?php echo esc_html__('📋 复制结果', 'linked3'); ?></button>
        <button class="linked3-eco-btn" id="rewrite-swap"><?php echo esc_html__('🔄 结果→原文 (迭代)', 'linked3'); ?></button>
    </div>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-rewrite.js ?>
