<?php
/**
 * Dashboard partial: 视频脚本 v10.1.0 — 智谱清言首尾帧 + Motion Prompt + SEED连续性
 *
 * v10.1.0 重构 (基于 /genesis 三脚本统一架构 + feicai4.0 Motion方法论):
 *   公理1: SEED先行 — 共享Stage 0 SEED中心, 角色SEED确保跨帧一致
 *   公理2: 首尾帧模式 — 每组2图(首帧+尾帧) + 1个Motion Prompt → 5-10秒视频
 *   公理3: Motion Prompt方法论 — 吸取feicai4.0 (图片已见/简洁优先/具体动作/运动限制)
 *
 * 智谱清言视频生成适配:
 *   - 首尾帧: 上传2张图 + 输入Motion Prompt → 生成5-10秒视频
 *   - 中短视频: N组首尾帧, 组间衔接(连续性保障)
 *   - SEED连续性: 角色SEED的visual_dna注入每帧, 确保角色不漂移
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-23
 */
if (!defined('ABSPATH')) exit;

$nonce_v  = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载画风风格列表 (与漫画脚本共享)
$styles = [];
if (class_exists('GenesisAtomIndex')) {
    $idx = GenesisAtomIndex::instance();
    $raw = $idx->getStyles();
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $styles[$sid] = $label;
        }
    }
}

// 加载Motion Prompt选项
$motionOptions = [];
if (class_exists('MotionPromptEngine')) {
    $motionOptions = MotionPromptEngine::get_all_options();
}
?>

<style>
.lk3-video-wrap{max-width:1200px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
.lk3-video-stage{background:#fff;border:1px solid #E4E4E7;border-radius:10px;padding:20px;margin-bottom:16px;}
.lk3-video-stage-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.lk3-video-stage-title{font-size:16px;font-weight:700;color:#18181B;display:flex;align-items:center;gap:8px;}
.lk3-video-stage-desc{font-size:12px;color:#71717A;margin-bottom:16px;line-height:1.6;}
.lk3-video-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:16px;}
.lk3-video-form-label{display:block;font-size:12px;font-weight:600;color:#52525B;margin-bottom:4px;}
.lk3-video-form-control{width:100%;padding:8px 10px;border:1px solid #D4D4D8;border-radius:6px;font-size:13px;background:#fff;}
.lk3-video-form-control:focus{outline:none;border-color:#7C3AED;box-shadow:0 0 0 3px rgba(139,92,246,.1);}
.lk3-video-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #D4D4D8;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;background:#fff;color:#52525B;}
.lk3-video-btn:hover{background:#F4F4F5;}
.lk3-video-btn-primary{background:#7C3AED;color:#fff;border-color:#7C3AED;}
.lk3-video-btn-primary:hover{background:#7c3aed;}
.lk3-video-btn-sm{padding:4px 10px;font-size:11px;}
.lk3-video-group-card{background:#fff;border:1px solid #E4E4E7;border-left:4px solid #7C3AED;border-radius:6px;padding:12px;margin-bottom:12px;}
.lk3-video-frame-box{background:#0f172a;border-radius:4px;padding:6px;margin-top:6px;}
.lk3-video-frame-box textarea{width:100%;min-height:50px;font-size:11px;font-family:"SF Mono",Monaco,monospace;background:#18181B;color:#E4E4E7;border:none;border-radius:3px;padding:8px;resize:vertical;line-height:1.5;}
.lk3-video-motion-box{background:#1e1b4b;border-radius:4px;padding:6px;margin-top:6px;border:1px solid #6d28d9;}
.lk3-video-motion-box textarea{width:100%;min-height:40px;font-size:11px;font-family:"SF Mono",Monaco,monospace;background:#312e81;color:#F4F4F5;border:none;border-radius:3px;padding:8px;resize:vertical;line-height:1.5;}
.lk3-video-hint{background:#FAFAFA;border-left:3px solid #7C3AED;padding:8px 12px;border-radius:4px;font-size:12px;color:#7C3AED;margin-bottom:12px;line-height:1.6;}
.lk3-video-axis-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px;}
.lk3-video-axis-card{background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;padding:10px;}
.lk3-video-axis-label{font-size:11px;font-weight:600;color:#52525B;margin-bottom:4px;}
@media(max-width:768px){.lk3-video-form-grid{grid-template-columns:1fr;}.lk3-video-axis-grid{grid-template-columns:1fr;}}
</style>

<div class="lk3-video-wrap">

<h2>视频脚本生成 <span style="font-size:12px;color:#666;font-weight:normal;">v10.1.0 智谱清言首尾帧 · Motion Prompt · SEED连续性</span></h2>

<div class="lk3-video-hint">
    <strong>🎬 智谱清言视频生成流程:</strong><br>
    1️⃣ <strong>SEED先行</strong> — 在「SEED中心」定义角色/场景, 确保跨帧一致<br>
    2️⃣ <strong>首尾帧生成</strong> — 每组生成2张图Prompt (首帧+尾帧), 粘贴到Midjourney/DALL-E生成静态图<br>
    3️⃣ <strong>Motion Prompt</strong> — 每组1个运动提示词 (50-200字符), 描述2图间的运动变化<br>
    4️⃣ <strong>智谱清言合成</strong> — 上传首尾帧2张图 + 输入Motion Prompt → 生成5-10秒视频<br>
    5️⃣ <strong>中短视频</strong> — 多组首尾帧拼接, 组间转场衔接
</div>

<!-- ===== Stage 1: 剧本输入 ===== -->
<div class="lk3-video-stage">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>📝</span> Stage 1 · 剧本输入</h3>
    </div>
    <p class="lk3-video-stage-desc">粘贴剧本/故事。AI将拆解为分镜节点, 每个节点生成一组(首帧+尾帧+Motion Prompt)。</p>
    <textarea id="linked3-video-script" class="lk3-video-form-control" rows="6" placeholder="粘贴剧本或故事...&#10;&#10;示例: 少年站在大学校门前, 手握录取通知书, 微风吹动他的头发。他深吸一口气, 迈步走向校门..." style="font-size:13px;line-height:1.6;"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
        <span style="font-size:11px;color:#71717A;" id="lk3-video-script-stats">0 字</span>
        <button type="button" class="lk3-video-btn lk3-video-btn-sm" onclick="document.getElementById('lk3-video-config').scrollIntoView({behavior:'smooth'})">下一步: 配置 →</button>
    </div>
</div>

<!-- ===== Stage 2: 生成配置 ===== -->
<div class="lk3-video-stage" id="lk3-video-config">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>⚙️</span> Stage 2 · 生成配置</h3>
    </div>
    <p class="lk3-video-stage-desc">配置画风、分镜数量、Motion参数。画风风格决定画面视觉基因, Motion参数决定运动方式。</p>

    <div class="lk3-video-form-grid">

        <!-- v2.0: 画风风格库融合面板 (内嵌画风下拉, 视觉绑定, 修复"看不见"; 合并双AI按钮) -->
        <?php
        $style_select_id        = 'linked3-video-style';
        $topic_input_id         = 'linked3-video-script';
        $visual_style_select_id = ''; // 视频脚本无信息图技法下拉, 留空不联动
        $nonce                  = wp_create_nonce('linked3_content_writer');
        $ajax_url               = admin_url('admin-ajax.php');
        $instance               = 'video';
        include __DIR__ . '/style-fusion-panel-v2.php';
        ?>

        <div>
            <label class="lk3-video-form-label">📊 视频组数 <span style="font-size:10px;color:#A1A1AA;">(每组5-10秒)</span></label>
            <input type="number" id="linked3-video-group-count" class="lk3-video-form-control" value="5" min="1" max="20">
        </div>
        <div>
            <label class="lk3-video-form-label">✂️ 分镜模式</label>
            <select id="linked3-video-split-mode" class="lk3-video-form-control">
                <option value="auto">auto (动态: AI按剧情拆分)</option>
                <option value="fixed">fixed (固定: 严格按组数生成)</option>
                <option value="sentence">sentence (按句: 每句1组)</option>
            </select>
        </div>
        <div>
            <label class="lk3-video-form-label">🤖 Motion自动推导</label>
            <select id="linked3-video-motion-auto" class="lk3-video-form-control">
                <option value="yes">是 (根据情绪自动推导镜头/动作)</option>
                <option value="no">否 (手动指定Motion参数)</option>
            </select>
        </div>
    </div>

    <!-- Motion参数 (手动模式) -->
    <div id="lk3-video-motion-manual" style="display:none;background:#FAFAFA;padding:12px;border-radius:6px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:#52525B;">🎬 Motion参数 (手动指定)</div>
        <div style="font-size:11px;color:#71717A;margin-bottom:10px;">💡 吸取feicai4.0方法论: 镜头运动≤2种, 主体运动≤2种, 50-200字符最佳</div>
        <div class="lk3-video-form-grid">
            <div>
                <label class="lk3-video-form-label">📷 镜头运动</label>
                <select id="lk3-video-camera" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['camera_movements'])): foreach ($motionOptions['camera_movements'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?> (<?php echo esc_html($info['mood']); ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label">🏃 主体动作</label>
                <select id="lk3-video-action" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['subject_actions'])): foreach ($motionOptions['subject_actions'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label">💨 速度</label>
                <select id="lk3-video-speed" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['speed_modifiers'])): foreach ($motionOptions['speed_modifiers'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label">🎭 氛围</label>
                <select id="lk3-video-atmosphere" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['atmosphere_styles'])): foreach ($motionOptions['atmosphere_styles'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- SEED引用 -->
    <div style="margin-bottom:12px;">
        <label class="lk3-video-form-label">🧬 SEED引用 <span style="font-size:10px;color:#A1A1AA;">(从SEED中心选择, 确保跨帧一致)</span></label>
        <input type="hidden" id="linked3-video-seed-refs" value="">
        <div id="linked3-video-seed-selected-list" style="min-height:32px;padding:8px;border:1px dashed #D4D4D8;border-radius:6px;background:#FAFAFA;">
            <span style="color:#A1A1AA;font-size:12px;">未选择SEED — 前往「SEED中心」选择角色/场景SEED</span>
        </div>
    </div>

    <button type="button" class="lk3-video-btn lk3-video-btn-primary" id="linked3-video-gen">🎬 生成视频脚本</button>
</div>

<!-- ===== Stage 3: 生成结果 ===== -->
<div class="lk3-video-stage" id="lk3-video-result-stage" style="display:none;">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>🎞️</span> Stage 3 · 视频脚本结果</h3>
        <span id="linked3-video-status" style="font-size:12px;color:#71717A;"></span>
    </div>
    <p class="lk3-video-stage-desc">每组包含: 首帧Prompt + 尾帧Prompt + Motion Prompt。将首尾帧粘贴到生图工具生成2张图, 再上传智谱清言+Motion Prompt生成视频。</p>
    <div id="linked3-video-result"></div>
</div>

</div>

<script>
(function(){
    'use strict';
    var nonce = '<?php echo esc_js($nonce_v); ?>';
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';

    // 字数统计
    var scriptEl = document.getElementById('linked3-video-script');
    var statsEl = document.getElementById('lk3-video-script-stats');
    if (scriptEl && statsEl) {
        scriptEl.addEventListener('input', function() {
            statsEl.textContent = scriptEl.value.length + ' 字';
        });
    }

    // Motion手动/自动切换
    var motionAutoSel = document.getElementById('linked3-video-motion-auto');
    var motionManual = document.getElementById('lk3-video-motion-manual');
    if (motionAutoSel && motionManual) {
        motionAutoSel.addEventListener('change', function() {
            motionManual.style.display = this.value === 'no' ? 'block' : 'none';
        });
    }

    // 生成按钮
    var genBtn = document.getElementById('linked3-video-gen');
    var statusEl = document.getElementById('linked3-video-status');
    var resultStage = document.getElementById('lk3-video-result-stage');
    var resultEl = document.getElementById('linked3-video-result');

    if (genBtn) {
        genBtn.addEventListener('click', function() {
            var script = document.getElementById('linked3-video-script').value.trim();
            if (!script || script.length < 20) {
                alert('请输入至少20字的剧本');
                return;
            }

            genBtn.disabled = true;
            genBtn.textContent = '⏳ 生成中...';
            statusEl.textContent = '正在生成视频脚本...';
            resultEl.innerHTML = '<div style="text-align:center;padding:30px;color:#71717A;"><div class="spinner is-active" style="float:none;margin:0 auto 10px;"></div>正在拆解剧本, 生成首尾帧+Motion Prompt...</div>';
            resultStage.style.display = 'block';

            var fd = new FormData();
            fd.append('action', 'linked3_video_generate_v10');
            fd.append('nonce', nonce);
            fd.append('script', script);
            fd.append('style', document.getElementById('linked3-video-style').value);
            fd.append('group_count', document.getElementById('linked3-video-group-count').value);
            fd.append('split_mode', document.getElementById('linked3-video-split-mode').value);
            fd.append('motion_auto', document.getElementById('linked3-video-motion-auto').value);
            fd.append('seed_refs', document.getElementById('linked3-video-seed-refs').value);

            if (document.getElementById('linked3-video-motion-auto').value === 'no') {
                fd.append('camera', document.getElementById('lk3-video-camera').value);
                fd.append('action_type', document.getElementById('lk3-video-action').value);
                fd.append('speed', document.getElementById('lk3-video-speed').value);
                fd.append('atmosphere', document.getElementById('lk3-video-atmosphere').value);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    genBtn.disabled = false;
                    genBtn.textContent = '🎬 生成视频脚本';
                    if (!res.success) {
                        statusEl.textContent = '✗ 生成失败';
                        statusEl.style.color = '#DC2626';
                        resultEl.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(res.data.message || '生成失败') + '</div>';
                        return;
                    }
                    statusEl.textContent = '✓ 生成完成, 共 ' + (res.data.total_groups || 0) + ' 组';
                    statusEl.style.color = '#16a34a';
                    renderVideoResult(res.data);
                })
                .catch(function(e) {
                    genBtn.disabled = false;
                    genBtn.textContent = '🎬 生成视频脚本';
                    statusEl.textContent = '✗ 网络错误';
                    statusEl.style.color = '#DC2626';
                    resultEl.innerHTML = '<div style="color:#DC2626;padding:12px;">✗ ' + escapeHtml(e.message) + '</div>';
                });
        });
    }

    function renderVideoResult(data) {
        var groups = data.groups || [];
        var html = '';

        // 概览
        html += '<div style="background:#F4F4F5;border:1px solid #86efac;padding:10px 12px;margin-bottom:12px;border-radius:6px;">';
        html += '<strong style="color:#16a34a;">✓ 视频脚本生成成功</strong> — ' + (data.total_groups || 0) + '组, 每组5-10秒, 总时长约' + ((data.total_groups || 0) * 7) + '秒';
        html += '</div>';

        // 批量操作
        html += '<div style="margin-bottom:12px;">';
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-copy-all">📋 复制全部</button> ';
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-download-all">⬇️ 下载全部</button> ';
        // v11.8.0: SOP闭环 — 保存草稿 + 去发布
        html += '<button class="lk3-video-btn lk3-video-btn-sm" id="lk3-video-save-draft">💾 保存为草稿</button> ';
        html += '<a href="<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish")); ?>" class="lk3-video-btn lk3-video-btn-sm" style="text-decoration:none;display:inline-block;">📤 去发布</a>';
        html += '</div>';

        // 分组卡片
        groups.forEach(function(g, idx) {
            var arcColor = ['#0F172A','#F59E0B','#EF4444','#10B981'][idx % 4];
            html += '<div class="lk3-video-group-card" style="border-left-color:' + arcColor + ';">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
            html += '<div>';
            html += '<span style="background:' + arcColor + ';color:#fff;padding:2px 6px;border-radius:3px;font-weight:bold;font-size:11px;">组' + (idx + 1) + '</span> ';
            html += '<span style="font-size:12px;color:#71717A;">' + escapeHtml(g.arc_position || '') + ' · ' + escapeHtml(g.emotion || '') + '</span>';
            html += '</div>';
            html += '<span style="font-size:10px;color:#A1A1AA;">转场: ' + escapeHtml(g.transition || '') + '</span>';
            html += '</div>';

            if (g.beat_text) {
                html += '<div style="font-size:11px;color:#52525B;margin-bottom:6px;background:#FAFAFA;padding:4px 8px;border-radius:3px;">📝 ' + escapeHtml(g.beat_text) + '</div>';
            }

            // v11.2.0 #2: 动态变化预览 (首尾帧差异对比)
            var firstFrame = g.first_frame || '';
            var lastFrame = g.last_frame || '';
            var motionPrompt = g.motion_prompt || '';
            html += '<div style="margin-bottom:8px;padding:8px;background:#FEF3C7;border:1px solid #F59E0B;border-radius:4px;">';
            html += '<div style="font-size:11px;font-weight:600;color:#92400E;margin-bottom:4px;">🎬 动态变化预览 (首帧→尾帧)</div>';
            html += '<div style="font-size:11px;color:#71717A;line-height:1.6;">';
            html += '<strong>首帧状态:</strong> ' + escapeHtml((firstFrame.match(/about to[^,。]+/) || ['动作起始状态'])[0]) + '<br>';
            html += '<strong>尾帧状态:</strong> ' + escapeHtml((lastFrame.match(/has [^,。]+/) || ['动作完成状态'])[0]) + '<br>';
            html += '<strong>动态过渡:</strong> ' + escapeHtml(motionPrompt.substring(0, 100)) + (motionPrompt.length > 100 ? '...' : '');
            html += '</div></div>';

            // 首帧
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#0F172A;">🟦 首帧 Prompt (粘贴到生图工具)</div>';
            html += '<div class="lk3-video-frame-box"><textarea readonly>' + escapeHtml(firstFrame) + '</textarea></div>';
            var firstCaption = '画面说明: ' + (g.beat_text || '本组首帧画面') + '。情绪: ' + (g.emotion || 'neutral') + '。此帧为动作起始状态, 蓄势待发。';
            html += '<div style="font-size:11px;color:#71717A;background:#FAFAFA;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #0F172A;">💬 ' + escapeHtml(firstCaption) + '</div>';
            html += '</div>';

            // 尾帧
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#10B981;">🟩 尾帧 Prompt (粘贴到生图工具)</div>';
            html += '<div class="lk3-video-frame-box"><textarea readonly>' + escapeHtml(lastFrame) + '</textarea></div>';
            var lastCaption = '画面说明: ' + (g.beat_text || '本组尾帧画面') + '的完成状态。情绪: ' + (g.emotion || 'neutral') + '。此帧为动作结束状态, 与首帧有明显差异。';
            html += '<div style="font-size:11px;color:#71717A;background:#ecfdf5;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #10B981;">💬 ' + escapeHtml(lastCaption) + '</div>';
            html += '</div>';

            // Motion Prompt
            html += '<div style="margin-bottom:6px;"><div style="font-size:11px;font-weight:600;color:#7c3aed;">🎬 Motion Prompt (上传智谱清言, 2图间运动)</div>';
            html += '<div class="lk3-video-motion-box"><textarea readonly>' + escapeHtml(g.motion_prompt || '') + '</textarea></div>';
            // v11.0.7 #10: Motion图说
            var motionCaption = '运动说明: 描述首帧→尾帧之间的动态变化。转场: ' + (g.transition || '默认') + '。将首尾帧2张图+此Motion Prompt上传智谱清言, 生成5-10秒视频。';
            html += '<div style="font-size:11px;color:#71717A;background:#FAFAFA;padding:4px 8px;border-radius:3px;margin-top:2px;border-left:3px solid #7c3aed;">💬 ' + escapeHtml(motionCaption) + '</div>';
            html += '</div>';

            html += '</div>';
        });

        resultEl.innerHTML = html;

        // 批量操作绑定
        var copyAll = document.getElementById('lk3-video-copy-all');
        if (copyAll) {
            copyAll.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '=== 组' + (i+1) + ' ===\n【首帧】\n' + (g.first_frame||'') + '\n\n【尾帧】\n' + (g.last_frame||'') + '\n\n【Motion】\n' + (g.motion_prompt||'');
                });
                navigator.clipboard.writeText(parts.join('\n\n---\n\n')).then(function() {
                    alert('已复制 ' + groups.length + ' 组视频脚本');
                });
            });
        }
        var dlBtn = document.getElementById('lk3-video-download-all');
        if (dlBtn) {
            dlBtn.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '=== 组' + (i+1) + ' ===\n【首帧】\n' + (g.first_frame||'') + '\n\n【尾帧】\n' + (g.last_frame||'') + '\n\n【Motion】\n' + (g.motion_prompt||'');
                });
                var blob = new Blob([parts.join('\n\n---\n\n')], {type:'text/plain'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'video-script-' + Date.now() + '.txt';
                a.click();
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            });
        }

        // v11.8.0: SOP闭环 — 保存为草稿
        var saveDraftBtn = document.getElementById('lk3-video-save-draft');
        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', function() {
                var parts = groups.map(function(g, i) {
                    return '## 组' + (i+1) + '\n\n### 首帧\n' + (g.first_frame||'') + '\n\n### 尾帧\n' + (g.last_frame||'') + '\n\n### Motion\n' + (g.motion_prompt||'');
                });
                var title = prompt('请输入文章标题:', '视频脚本-' + Date.now());
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

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
        });
    }
})();
</script>
