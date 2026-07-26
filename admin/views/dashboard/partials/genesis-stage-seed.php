<?php
/**
 * Partial: genesis-stage-seed
 * Extracted from: tab-genesis.php
 * v29.1.0 Step 5: Template splitting
 */
if (!defined('ABSPATH')) exit;
?>
<!-- ===== Stage 0: SEED 中心 (置顶) ===== -->
<div class="lk3-stage" id="lk3-stage-0">
    <div class="lk3-stage-header">
        <h3 class="lk3-stage-title"><span class="lk3-stage-icon">🧬</span><?php echo esc_html__('Stage 0 · SEED 中心', 'linked3'); ?></h3>
        <div style="display:flex;gap:8px;">
            <button type="button" class="lk3-btn lk3-btn-sm" id="lk3-seed-refresh-cats"><?php echo esc_html__('↻ 刷新', 'linked3'); ?></button>
            <button type="button" class="lk3-btn lk3-btn-sm lk3-btn-primary" id="lk3-seed-create-new"><?php echo esc_html__('＋ 新建 SEED', 'linked3'); ?></button>
            <button type="button" class="lk3-btn lk3-btn-sm" id="lk3-seed-import-tpl"><?php echo esc_html__('📥 从模板导入', 'linked3'); ?></button>
        </div>
    </div>
    <p class="lk3-stage-desc">
        <strong><?php echo esc_html__('公理1', 'linked3'); ?></strong>: SEED 是漫画一致性的信息基 (低熵), 必须先于剧本定义。
        <strong><?php echo esc_html__('公理2', 'linked3'); ?></strong>: 🔒<code>fixed</code><?php echo esc_html__('=不可变基因 (如人物样貌), 🔄', 'linked3'); ?><code>variable</code>=可变基因 (如每日服装)。
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
            <h4><?php echo esc_html__('SEED 脚本生成器 — 从全剧本一键生成 SEED 库', 'linked3'); ?></h4>
        </div>
        <p class="lk3-seed-gen-desc">
            <strong><?php echo esc_html__('解决"先有鸡还是先有蛋"', 'linked3'); ?></strong><?php echo esc_html__(': 粘贴任意剧本/故事, AI 自动提取角色/场景/道具/风格, 生成完整 SEED 库。', 'linked3'); ?><br>
            生成的 SEED 可共享用于 <strong><?php echo esc_html__('图文脚本', 'linked3'); ?></strong> / <strong><?php echo esc_html__('漫画脚本', 'linked3'); ?></strong> / <strong><?php echo esc_html__('视频脚本', 'linked3'); ?></strong>, 一次生成, 多场景复用。
        </p>
        <div class="lk3-form-grid" style="margin-bottom:10px;">
            <div>
                <label class="lk3-form-label"><?php echo esc_html__('📋 脚本类型 (决定 SEED 提取侧重点)', 'linked3'); ?></label>
                <select id="lk3-seedgen-script-type" class="lk3-form-control">
                    <option value="comic"><?php echo esc_html__('📖 漫画脚本 — 侧重视觉描述/角色外貌/场景氛围', 'linked3'); ?></option>
                    <option value="image"><?php echo esc_html__('🖼️ 图文脚本 — 侧重产品特征/品牌元素/构图风格', 'linked3'); ?></option>
                    <option value="video"><?php echo esc_html__('🎬 视频脚本 — 侧重镜头运动/场景转换/节奏情绪', 'linked3'); ?></option>
                </select>
            </div>
            <div>
                <label class="lk3-form-label"><?php echo esc_html__('🎨 视觉风格 (影响 SEED 的画风基因)', 'linked3'); ?></label>
                <select id="lk3-seedgen-style" class="lk3-form-control">
                    <option value="auto"><?php echo esc_html__('自动 (AI 根据剧本推断)', 'linked3'); ?></option>
                    <?php if (!empty($styles)): foreach ($styles as $sid => $sname): ?>
                    <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html($sname); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>
        <textarea id="lk3-seedgen-script" class="lk3-form-control" rows="5" placeholder="<?php echo esc_attr__('粘贴完整剧本或故事... AI 将自动提取角色、场景、道具、风格, 生成 SEED 库。&#10;&#10;例如: 林隐是一名驱魔师, 25岁, 短黑发, 左眉有疤, 身穿黑色战术夹克。他常出没于雨夜古宅、荒野战场...', 'linked3'); ?>" style="font-size:13px;line-height:1.6;margin-bottom:10px;"></textarea>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="lk3-btn lk3-btn-primary" id="lk3-seedgen-run"><?php echo esc_html__('🥚 从剧本生成 SEED', 'linked3'); ?></button>
            <span id="lk3-seedgen-status" style="font-size:12px;color:#7C3AED;"></span>
        </div>
        <div id="lk3-seedgen-result" style="margin-top:10px;font-size:12px;"></div>
    </div>

    <!-- 已选 SEED 标签区 -->
    <div style="margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label class="lk3-form-label" style="margin:0;"><?php echo esc_html__('📌 已选 SEED 引用 (将注入 Prompt)', 'linked3'); ?></label>
            <div style="display:flex;gap:6px;">
                <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-seed-pick"><?php echo esc_html__('🔍 从库中选择', 'linked3'); ?></button>
                <button type="button" class="lk3-btn lk3-btn-sm" id="linked3-genesis-seed-clear"><?php echo esc_html__('✕ 清空', 'linked3'); ?></button>
            </div>
        </div>
        <!-- 保留原有 hidden input (JS兼容) -->
        <input type="hidden" id="linked3-genesis-seed-refs" value="">
        <div class="lk3-seed-tags" id="linked3-genesis-seed-selected-list">
            <span style="color:#A1A1AA;font-size:12px;" id="seed-empty-hint"><?php echo esc_html__('未选择任何 SEED — 点击「从库中选择」或上方卡片', 'linked3'); ?></span>
        </div>
        <div style="font-size:11px;color:#71717A;margin-top:4px;"><?php echo esc_html__('已选', 'linked3'); ?><strong id="seed-ref-count">0</strong><?php echo esc_html__('个 SEED', 'linked3'); ?></div>
    </div>
</div>

