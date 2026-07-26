<?php
/**
 * 关键词子面板 v17.0 — 全功能链整合 (热词采集 + 三维度生成 + 历史)
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的关键词管理界面规范
 *   - 保留全部功能: 热词采集/AI长尾词/三维度分类/批量文章入口
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_kw = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
$kw_seed_preset = isset($_GET['kw_seed']) ? sanitize_text_field($_GET['kw_seed']) : '';

// 加载持久化热词库/长尾词库
$saved_hot = (array) get_option(LINKED3_OPTION_PREFIX . 'hot_keywords', []);
$saved_tail = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []);
$saved_hot_str = implode("\n", $saved_hot);
$saved_tail_str = implode("\n", $saved_tail);
$saved_hot_count = count($saved_hot);
$saved_tail_count = count($saved_tail);

// v16.0.14 [公理α: H↓ 消除"用过没"不确定性] [公理β: dim↓ 0维自动替代手动记忆]
// 长尾词使用状态持久化: 记录每个长尾词是否已用于生成文章
$saved_tail_used = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords_used', []);
$saved_tail_used_json = wp_json_encode($saved_tail_used);
$saved_tail_used_count = count($saved_tail_used);
?>

<div class="linked3-eco-card">
    <h3><?php echo esc_html__('🔑 关键词全功能链 — 热词采集 + AI生成 + 三维度分类', 'linked3'); ?></h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;"><?php echo esc_html__('① 热词采集 → ② AI长尾词生成 → ③ 三维度分类 → ④ 批量文章入口', 'linked3'); ?></p>

    <!-- ① 热词采集 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;"><?php echo esc_html__('🔥 第①步:热词采集 (多源)', 'linked3'); ?></h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;align-items:center;">
        <select class="linked3-eco-select" id="kw-source" style="width:120px;">
            <?php
            // v16.0.15 [公理α: H↓ 消除选源不确定性] [公理β: dim↓ 0维默认替代1维选择]
            // 默认"全部源": 用户无需决策即可获得最大覆盖, 单源作为高级选项
            $kw_sources = [
                'all'    => __('🌐 全部源 (推荐)', 'linked3'),
                'baidu'  => __('百度', 'linked3'),
                'sogou'  => __('搜狗', 'linked3'),
                '360'    => '360',
                'zhihu'  => __('知乎', 'linked3'),
                'weibo'  => __('微博', 'linked3'),
                'douyin' => __('抖音', 'linked3'),
            ];
            $kw_source_default = 'all'; // v16.0.15: 默认全部源
            foreach ($kw_sources as $src_val => $src_label) {
                $selected = ($src_val === $kw_source_default) ? ' selected' : '';
                echo '<option value="' . esc_attr($src_val) . '"' . $selected . '>' . esc_html($src_label) . '</option>';
            }
            ?>
        </select>
        <input type="text" class="linked3-eco-input" id="kw-seed" placeholder="种子词(可选, 留空采集实时热榜)" style="flex:1;min-width:200px;" value="<?php echo esc_attr($kw_seed_preset); ?>">
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-fetch-hot"><?php echo esc_html__('🔥 采集热词', 'linked3'); ?></button>
    </div>

    <!-- 热词库 (持久化) -->
    <div style="margin-bottom:12px;">
        <label style="font-size:12px;color:#71717A;">
            📋 热词库 <span id="kw-hot-count" style="color:#999;">(<?php echo (int)$saved_hot_count; ?>个, 自动保存)</span>
        </label>
        <textarea id="kw-hot-list" rows="6" class="linked3-eco-input" style="width:100%;font-family:monospace;line-height:1.6;" placeholder="点击「采集热词」后结果会显示在这里。也可手动输入, 每行一个。编辑后自动保存。"><?php echo esc_textarea($saved_hot_str); ?></textarea>
    </div>

    <!-- ② AI长尾词生成 (v17.2.0 R1: 支持多热词批量) -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;"><?php echo esc_html__('🔑 第②步:AI 生成长尾关键词', 'linked3'); ?></h4>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;align-items:center;">
        <span style="font-size:12px;color:#71717A;"><?php echo esc_html__('生成', 'linked3'); ?></span>
        <input type="number" class="linked3-eco-input" id="kw-count" value="20" min="5" max="100" style="width:70px;">
        <span style="font-size:12px;color:#71717A;"><?php echo esc_html__('个', 'linked3'); ?></span>
        <!-- v17.2.0 R1: 单种子词 vs 全热词库批量 -->
        <button class="linked3-eco-btn" id="kw-generate"><?php echo esc_html__('🔑 单种子生成长尾词', 'linked3'); ?></button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-generate-multi"><?php echo esc_html__('🔥 用全部热词批量生成', 'linked3'); ?></button>
        <label style="font-size:12px;color:#71717A;"><input type="checkbox" id="kw-append" checked><?php echo esc_html__('追加到长尾词库', 'linked3'); ?></label>
    </div>
    <p style="font-size:11px;color:#9ca3af;margin:0 0 8px 0;"><?php echo esc_html__('💡 单种子: 基于输入的一个种子词生成长尾词 | 全热词: 遍历热词库每个热词各生成长尾词(覆盖面广)', 'linked3'); ?></p>

    <!-- 长尾词库 (持久化) v11.9.1: 增强可见性 — 醒目卡片+空状态引导 -->
    <!-- v16.0.14: 增加使用状态徽章 (已用/未用) -->
    <div style="margin-bottom:12px;background:#FAFAFA;border:2px solid #0F172A;border-radius:8px;padding:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label style="font-size:14px;font-weight:600;color:#0F172A;">
                📋 长尾词库 <span id="kw-tail-count" style="color:#0F172A;font-weight:normal;">(<?php echo (int)$saved_tail_count; ?>个)</span>
                <span id="kw-tail-used-count" class="lk3-badge lk3-badge-success" style="margin-left:8px;font-size:11px;">已用 <?php echo (int)$saved_tail_used_count; ?></span>
                <span id="kw-tail-unused-count" class="lk3-badge lk3-badge-warning" style="margin-left:4px;font-size:11px;">未用 <?php echo (int)max(0, $saved_tail_count - $saved_tail_used_count); ?></span>
            </label>
            <div style="display:flex;gap:6px;">
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-export" style="font-size:11px;"><?php echo esc_html__('⬇️ 导出', 'linked3'); ?></button>
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-clear" style="font-size:11px;color:#DC2626;"><?php echo esc_html__('🗑️ 清空', 'linked3'); ?></button>
                <button class="linked3-eco-btn linked3-eco-btn-sm" id="kw-tail-reset-used" style="font-size:11px;color:#71717A;" title="<?php echo esc_attr__('重置所有使用状态', 'linked3'); ?>">↺ 重置状态</button>
            </div>
        </div>
        <textarea id="kw-tail-list" rows="6" class="linked3-eco-input" style="width:100%;font-family:monospace;line-height:1.6;background:#fff;" placeholder="长尾词库为空。请先: ①采集热词 → ②点击「单种子生成长尾词」或「用全部热词批量生成」→ 长尾词会自动保存到这里。&#10;&#10;也可手动输入, 每行一个长尾词, 编辑后自动保存。"><?php echo esc_textarea($saved_tail_str); ?></textarea>
        <div id="kw-tail-status-preview" style="margin-top:6px;font-size:11px;color:#71717A;"></div>
        <?php if ($saved_tail_count == 0) : ?>
        <p style="font-size:11px;color:#0F172A;margin:6px 0 0 0;"><?php echo esc_html__('💡 长尾词库当前为空。生成长尾词后会自动保存到这里, 后续可用于CSV批量生成文章或一键生态生产。', 'linked3'); ?></p>
        <?php endif; ?>
    </div>

    <!-- ③ 三维度分类结果 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;"><?php echo esc_html__('📊 第③步:三维度分类结果', 'linked3'); ?></h4>
    <div id="kw-result" style="margin-top:8px;"></div>

    <!-- ④ 批量生成文章入口 -->
    <h4 style="font-size:13px;margin:12px 0 6px;color:#3F3F46;"><?php echo esc_html__('📝 第④步:用长尾词库生成文章', 'linked3'); ?></h4>
    <div style="background:#F4F4F5;border:1px solid #86efac;border-radius:6px;padding:12px;">
        <p style="font-size:12px;color:#166534;margin:0 0 10px 0;">当前长尾词库: <strong id="kw-tail-count-display"><?php echo esc_html($saved_tail_count); ?></strong> 个词。选择生成方式:</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <!-- v11.0.6 #7: 一键跳转到CSV批量, 自动填入长尾词库 -->
            <button class="linked3-eco-btn" id="kw-to-csv-batch"><?php echo esc_html__('📊 用长尾词库CSV批量生成', 'linked3'); ?></button>
            <!-- v11.0.6 #7: 一键跳转到生态协同, 自动填入第一个长尾词作为主题 -->
            <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-to-synergy"><?php echo esc_html__('🚀 用首个长尾词一键生态生产', 'linked3'); ?></button>
        </div>
        <p style="font-size:11px;color:#71717A;margin:8px 0 0 0;"><?php echo esc_html__('💡 CSV批量: 每个长尾词生成一篇文章 (适合批量生产)', 'linked3'); ?><br><?php echo esc_html__('💡 生态协同: 用第一个长尾词作为主题, 走完整5阶段流程 (适合单篇精修)', 'linked3'); ?></p>
    </div>

    <!-- ⑤ 定时任务 -->
    <details style="margin-top:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;">
        <summary style="cursor:pointer;font-weight:600;color:#666;font-size:12px;"><?php echo esc_html__('⏰ 定时获取热词 + 生成长尾词 (AutoGPT)', 'linked3'); ?></summary>
        <div style="margin-top:8px;font-size:12px;">
            <p style="color:#71717A;"><?php echo esc_html__('设置定时任务, 自动采集热词并生成长尾词, 追加到长尾词库。', 'linked3'); ?></p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <label>频率:
                    <select class="linked3-eco-select" id="kw-cron-freq" style="width:120px;">
                        <option value="hourly"><?php echo esc_html__('每小时', 'linked3'); ?></option>
                        <option value="twicedaily" selected><?php echo esc_html__('每天两次', 'linked3'); ?></option>
                        <option value="daily"><?php echo esc_html__('每天', 'linked3'); ?></option>
                    </select>
                </label>
                <label>每次生成:
                    <input type="number" class="linked3-eco-input" id="kw-cron-count" value="30" min="5" max="100" style="width:60px;">
                    个
                </label>
                <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-cron-enable"><?php echo esc_html__('启用定时任务', 'linked3'); ?></button>
                <button class="linked3-eco-btn linked3-eco-btn-secondary" id="kw-cron-disable"><?php echo esc_html__('禁用', 'linked3'); ?></button>
            </div>
            <div id="kw-cron-status" style="margin-top:6px;"></div>
        </div>
    </details>
</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-keywords.js
// Pass partial-local $saved_tail_used_json to JS via inline script
wp_add_inline_script('linked3-eco-keywords', 'window.linked3_eco_keywords.saved_tail_used = ' . wp_json_encode($saved_tail_used_json ?? '{}') . ';', 'after');
?>
