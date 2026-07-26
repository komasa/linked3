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
<div class="lk3-video-wrap">

<h2><?php echo esc_html__('视频脚本生成', 'linked3'); ?><span style="font-size:12px;color:#666;font-weight:normal;"><?php echo esc_html__('v10.1.0 智谱清言首尾帧 · Motion Prompt · SEED连续性', 'linked3'); ?></span></h2>

<div class="lk3-video-hint">
    <strong><?php echo esc_html__('🎬 智谱清言视频生成流程:', 'linked3'); ?></strong><br>
    1️⃣ <strong><?php echo esc_html__('SEED先行', 'linked3'); ?></strong><?php echo esc_html__('— 在「SEED中心」定义角色/场景, 确保跨帧一致', 'linked3'); ?><br>
    2️⃣ <strong><?php echo esc_html__('首尾帧生成', 'linked3'); ?></strong><?php echo esc_html__('— 每组生成2张图Prompt (首帧+尾帧), 粘贴到Midjourney/DALL-E生成静态图', 'linked3'); ?><br>
    3️⃣ <strong>Motion Prompt</strong><?php echo esc_html__('— 每组1个运动提示词 (50-200字符), 描述2图间的运动变化', 'linked3'); ?><br>
    4️⃣ <strong><?php echo esc_html__('智谱清言合成', 'linked3'); ?></strong><?php echo esc_html__('— 上传首尾帧2张图 + 输入Motion Prompt → 生成5-10秒视频', 'linked3'); ?><br>
    5️⃣ <strong><?php echo esc_html__('中短视频', 'linked3'); ?></strong> — 多组首尾帧拼接, 组间转场衔接
</div>

<!-- ===== Stage 1: 剧本输入 ===== -->
<div class="lk3-video-stage">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>📝</span><?php echo esc_html__('Stage 1 · 剧本输入', 'linked3'); ?></h3>
    </div>
    <p class="lk3-video-stage-desc"><?php echo esc_html__('粘贴剧本/故事。AI将拆解为分镜节点, 每个节点生成一组(首帧+尾帧+Motion Prompt)。', 'linked3'); ?></p>
    <textarea id="linked3-video-script" class="lk3-video-form-control" rows="6" placeholder="<?php echo esc_attr__('粘贴剧本或故事...&#10;&#10;示例: 少年站在大学校门前, 手握录取通知书, 微风吹动他的头发。他深吸一口气, 迈步走向校门...', 'linked3'); ?>" style="font-size:13px;line-height:1.6;"></textarea>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
        <span style="font-size:11px;color:#71717A;" id="lk3-video-script-stats"><?php echo esc_html__('0 字', 'linked3'); ?></span>
        <button type="button" class="lk3-video-btn lk3-video-btn-sm" onclick="document.getElementById('lk3-video-config').scrollIntoView({behavior:'smooth'})"><?php echo esc_html__('下一步: 配置 →', 'linked3'); ?></button>
    </div>
</div>

<!-- ===== Stage 2: 生成配置 ===== -->
<div class="lk3-video-stage" id="lk3-video-config">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>⚙️</span><?php echo esc_html__('Stage 2 · 生成配置', 'linked3'); ?></h3>
    </div>
    <p class="lk3-video-stage-desc"><?php echo esc_html__('配置画风、分镜数量、Motion参数。画风风格决定画面视觉基因, Motion参数决定运动方式。', 'linked3'); ?></p>

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
            <label class="lk3-video-form-label"><?php echo esc_html__('📊 视频组数', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;"><?php echo esc_html__('(每组5-10秒)', 'linked3'); ?></span></label>
            <input type="number" id="linked3-video-group-count" class="lk3-video-form-control" value="5" min="1" max="20">
        </div>
        <div>
            <label class="lk3-video-form-label"><?php echo esc_html__('✂️ 分镜模式', 'linked3'); ?></label>
            <select id="linked3-video-split-mode" class="lk3-video-form-control">
                <option value="auto"><?php echo esc_html__('auto (动态: AI按剧情拆分)', 'linked3'); ?></option>
                <option value="fixed"><?php echo esc_html__('fixed (固定: 严格按组数生成)', 'linked3'); ?></option>
                <option value="sentence"><?php echo esc_html__('sentence (按句: 每句1组)', 'linked3'); ?></option>
            </select>
        </div>
        <div>
            <label class="lk3-video-form-label"><?php echo esc_html__('🤖 Motion自动推导', 'linked3'); ?></label>
            <select id="linked3-video-motion-auto" class="lk3-video-form-control">
                <option value="yes"><?php echo esc_html__('是 (根据情绪自动推导镜头/动作)', 'linked3'); ?></option>
                <option value="no"><?php echo esc_html__('否 (手动指定Motion参数)', 'linked3'); ?></option>
            </select>
        </div>
    </div>

    <!-- Motion参数 (手动模式) -->
    <div id="lk3-video-motion-manual" style="display:none;background:#FAFAFA;padding:12px;border-radius:6px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;color:#52525B;"><?php echo esc_html__('🎬 Motion参数 (手动指定)', 'linked3'); ?></div>
        <div style="font-size:11px;color:#71717A;margin-bottom:10px;"><?php echo esc_html__('💡 吸取feicai4.0方法论: 镜头运动≤2种, 主体运动≤2种, 50-200字符最佳', 'linked3'); ?></div>
        <div class="lk3-video-form-grid">
            <div>
                <label class="lk3-video-form-label"><?php echo esc_html__('📷 镜头运动', 'linked3'); ?></label>
                <select id="lk3-video-camera" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['camera_movements'])): foreach ($motionOptions['camera_movements'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?> (<?php echo esc_html($info['mood']); ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label"><?php echo esc_html__('🏃 主体动作', 'linked3'); ?></label>
                <select id="lk3-video-action" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['subject_actions'])): foreach ($motionOptions['subject_actions'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label"><?php echo esc_html__('💨 速度', 'linked3'); ?></label>
                <select id="lk3-video-speed" class="lk3-video-form-control">
                    <?php if (!empty($motionOptions['speed_modifiers'])): foreach ($motionOptions['speed_modifiers'] as $key => $info): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label class="lk3-video-form-label"><?php echo esc_html__('🎭 氛围', 'linked3'); ?></label>
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
        <label class="lk3-video-form-label"><?php echo esc_html__('🧬 SEED引用', 'linked3'); ?><span style="font-size:10px;color:#A1A1AA;"><?php echo esc_html__('(从SEED中心选择, 确保跨帧一致)', 'linked3'); ?></span></label>
        <input type="hidden" id="linked3-video-seed-refs" value="">
        <div id="linked3-video-seed-selected-list" style="min-height:32px;padding:8px;border:1px dashed #D4D4D8;border-radius:6px;background:#FAFAFA;">
            <span style="color:#A1A1AA;font-size:12px;"><?php echo esc_html__('未选择SEED — 前往「SEED中心」选择角色/场景SEED', 'linked3'); ?></span>
        </div>
    </div>

    <button type="button" class="lk3-video-btn lk3-video-btn-primary" id="linked3-video-gen"><?php echo esc_html__('🎬 生成视频脚本', 'linked3'); ?></button>
</div>

<!-- ===== Stage 3: 生成结果 ===== -->
<div class="lk3-video-stage" id="lk3-video-result-stage" style="display:none;">
    <div class="lk3-video-stage-header">
        <h3 class="lk3-video-stage-title"><span>🎞️</span><?php echo esc_html__('Stage 3 · 视频脚本结果', 'linked3'); ?></h3>
        <span id="linked3-video-status" style="font-size:12px;color:#71717A;"></span>
    </div>
    <p class="lk3-video-stage-desc"><?php echo esc_html__('每组包含: 首帧Prompt + 尾帧Prompt + Motion Prompt。将首尾帧粘贴到生图工具生成2张图, 再上传智谱清言+Motion Prompt生成视频。', 'linked3'); ?></p>
    <div id="linked3-video-result"></div>
</div>

</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-video.js ?>
