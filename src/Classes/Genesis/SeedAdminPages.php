<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * SeedAdminPages — G8 extraction.
 * @since 27.13.0
 */
class SeedAdminPages
{
    static function render_list_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('无权限', 'linked3'));
        }

        [$search, $cat_filt, $type_filt] = self::parseListFilters();
        $query = self::buildSeedQuery($search, $cat_filt, $type_filt);
        $groups = self::groupPostsByCategory($query->posts);

        $list_url   = admin_url('admin.php?page=' . self::PAGE_SLUG_LIST);
        $new_url    = admin_url('admin.php?page=' . self::PAGE_SLUG_NEW);
        $trash_nonce = wp_create_nonce(self::NONCE_ACTION_TRASH);
        $bulk_form_url = admin_url('admin-post.php');

        echo '<div class="wrap linked3-seed-wrap">';
        echo '<h2>' . esc_html__('Seed DNA 库', 'linked3') . ' <a class="page-title-action" href="' . esc_url($new_url) . '">' . esc_html__('新建 Seed', 'linked3') . '</a></h2>';
        echo '<div class="linked3-notice-info">' . esc_html__('提示: Seed DNA 是分镜的"视觉基因库", 必须在分镜生成之前定义。固定风格 Seed (CharacterSeed/BrandSeed) 跨分镜不变, 可变场景 Seed (SceneSeed/PropSeed) 随分镜推进演化。', 'linked3') . '</div>';

        self::renderListToolbar($search, $cat_filt, $type_filt);
        self::renderBulkForm($bulk_form_url);
        self::renderBulkActionBar($list_url, $trash_nonce);
        echo '<script>jQuery(function($){ $("#linked3-cb-all").on("change", function(){ $(".linked3-seed-cb").prop("checked", this.checked); }); });</script>';

        if (empty($query->posts)) {
            self::renderEmptyState($new_url);
        } else {
            self::renderGroupedSeedTables($groups, $cat_filt);
        }

        self::maybe_handle_download();
        self::maybe_handle_export_all();

        echo '</div>';
    }

    /**
     * 解析列表页筛选参数.
     */
    private static function parseListFilters(): array {
        return [
            isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            isset($_GET['cat']) ? sanitize_key($_GET['cat']) : '',
            isset($_GET['type']) ? sanitize_key($_GET['type']) : '',
        ];
    }

    /**
     * 构造 WP_Query.
     */
    private static function buildSeedQuery(string $search, string $cat_filt, string $type_filt): \WP_Query {
        $meta_query = ['relation' => 'AND'];
        if ($cat_filt) $meta_query[] = ['key' => 'seed_category', 'value' => $cat_filt];
        if ($type_filt) $meta_query[] = ['key' => 'seed_type', 'value' => $type_filt];
        if (count($meta_query) === 1) $meta_query = [];

        $args = [
            'post_type'      => 'linked3_seed',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if ($search) $args['s'] = $search;
        if ($meta_query) $args['meta_query'] = $meta_query;
        return new \WP_Query($args);
    }

    /**
     * 按 seed_category 分组.
     */
    private static function groupPostsByCategory(array $posts): array {
        $groups = [];
        foreach (self::$CATEGORIES as $cat => $_) {
            $groups[$cat] = [];
        }
        foreach ($posts as $p) {
            $cat = get_post_meta($p->ID, 'seed_category', true);
            if (!isset($groups[$cat])) $groups[$cat] = [];
            $groups[$cat][] = $p;
        }
        return $groups;
    }

    /**
     * 渲染列表页工具栏 (搜索+筛选).
     */
    private static function renderListToolbar(string $search, string $cat_filt, string $type_filt): void {
        echo '<form method="get" class="linked3-seed-toolbar">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG_LIST) . '" />';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('搜索 Seed 名称...', 'linked3') . '" />';
        echo '<select name="cat"><option value="">' . esc_html__('全部分类', 'linked3') . '</option>';
        foreach (self::$CATEGORIES as $c => $l) {
            echo '<option value="' . esc_attr($c) . '"' . selected($cat_filt, $c, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select>';
        echo '<select name="type"><option value="">' . esc_html__('全部类型', 'linked3') . '</option>';
        foreach (self::$TYPES as $t => $l) {
            echo '<option value="' . esc_attr($t) . '"' . selected($type_filt, $t, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select>';
        submit_button(__('筛选', 'linked3'), 'secondary', 'filter', false);
        echo '</form>';
    }

    /**
     * 渲染批量操作隐藏 form.
     */
    private static function renderBulkForm(string $bulk_form_url): void {
        echo '<form id="linked3-bulk-form" method="post" action="' . esc_url($bulk_form_url) . '">';
        wp_nonce_field('linked3_seed_bulk', 'linked3_seed_bulk_nonce');
        echo '<input type="hidden" name="action" value="linked3_seed_bulk" />';
        echo '<input type="hidden" name="linked3_bulk_action" id="linked3-bulk-action-input" value="" />';
        echo '<input type="hidden" name="linked3_bulk_ids" id="linked3-bulk-ids-input" value="" />';
        echo '</form>';
    }

    /**
     * 渲染批量操作栏.
     */
    private static function renderBulkActionBar(string $list_url, string $trash_nonce): void {
        echo '<div class="linked3-bulk-bar">';
        echo '<input type="checkbox" id="linked3-cb-all" /> <label for="linked3-cb-all">' . esc_html__('全选', 'linked3') . '</label>';
        echo '<select class="linked3-bulk-action-select">';
        echo '<option value="">' . esc_html__('批量操作...', 'linked3') . '</option>';
        echo '<option value="trash">' . esc_html__('软删除', 'linked3') . '</option>';
        echo '<option value="export_md">' . esc_html__('导出 MD', 'linked3') . '</option>';
        echo '<option value="export_json">' . esc_html__('导出 JSON', 'linked3') . '</option>';
        echo '</select>';
        echo '<span style="margin-left:auto;">';
        echo '<button type="button" class="button button-primary" onclick="location.href=\'' . esc_js(add_query_arg(['export' => 'all_md'], $list_url)) . '\'">' . esc_html__('导出全部 MD', 'linked3') . '</button> ';
        echo '<button type="button" class="button" onclick="location.href=\'' . esc_js(add_query_arg(['export' => 'all_json'], $list_url)) . '\'">' . esc_html__('导出全部 JSON', 'linked3') . '</button> ';
        echo '<button type="button" class="button button-link-delete linked3-trash-all-btn" data-nonce="' . esc_attr($trash_nonce) . '">' . esc_html__('清空所有 Seed', 'linked3') . '</button>';
        echo '</span>';
        echo '</div>';
    }

    /**
     * 渲染空状态.
     */
    private static function renderEmptyState(string $new_url): void {
        echo '<div class="linked3-empty">';
        echo '<span class="dashicons dashicons-id-alt"></span>';
        echo '<h3>' . esc_html__('还没有 Seed DNA', 'linked3') . '</h3>';
        echo '<p>' . esc_html__('Seed DNA 是漫画/分镜生成的视觉基因库, 先创建一个 Seed 开始吧。', 'linked3') . '</p>';
        echo '<p><a class="button button-primary button-large" href="' . esc_url($new_url) . '">' . esc_html__('+ 新建 Seed', 'linked3') . '</a></p>';
        echo '</div>';
    }

    /**
     * 按分类分组渲染表格.
     */
    private static function renderGroupedSeedTables(array $groups, string $cat_filt): void {
        foreach (self::$CATEGORIES as $cat => $label) {
            if (empty($groups[$cat])) continue;
            $count = count($groups[$cat]);
            echo '<details class="linked3-seed-group"' . ($cat_filt === $cat || (!$cat_filt && $cat === 'char') ? ' open' : '') . '>';
            echo '<summary><span class="badge badge-cat-' . esc_attr($cat) . '">' . esc_html($label) . '</span> <span class="group-count">' . esc_html($count) . ' 个</span></summary>';
            echo '<table class="linked3-seed-table"><thead><tr>';
            echo '<th style="width:30px;"></th><th>' . esc_html__('Seed ID', 'linked3') . '</th><th>' . esc_html__('名称', 'linked3') . '</th><th>' . esc_html__('类型', 'linked3') . '</th><th>' . esc_html__('父 Seed', 'linked3') . '</th><th>' . esc_html__('操作', 'linked3') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($groups[$cat] as $p) {
                self::renderSeedRow($p);
            }
            echo '</tbody></table>';
            echo '</details>';
        }
    }

    /**
     * 渲染单个 Seed 行.
     */
    private static function renderSeedRow(\WP_Post $p): void {
        $sid = get_post_meta($p->ID, 'seed_id', true);
        $type = get_post_meta($p->ID, 'seed_type', true);
        $parent = get_post_meta($p->ID, 'parent_seed', true);
        $row_edit = add_query_arg(['page' => self::PAGE_SLUG_EDIT, 'post_id' => $p->ID], admin_url('admin.php'));
        $dl_md = add_query_arg(['page' => self::PAGE_SLUG_LIST, 'download' => 'md', 'post_id' => $p->ID, '_wpnonce' => wp_create_nonce(self::NONCE_ACTION)], admin_url('admin.php'));
        $dl_json = add_query_arg(['page' => self::PAGE_SLUG_LIST, 'download' => 'json', 'post_id' => $p->ID, '_wpnonce' => wp_create_nonce(self::NONCE_ACTION)], admin_url('admin.php'));
        echo '<tr>';
        echo '<td><input type="checkbox" class="linked3-seed-cb" name="seed_ids[]" value="' . esc_attr($p->ID) . '" /></td>';
        echo '<td><code>' . esc_html($sid ?: '—') . '</code></td>';
        echo '<td><strong><a href="' . esc_url($row_edit) . '">' . esc_html($p->post_title) . '</a></strong>';
        echo '<div class="row-actions"><a href="' . esc_url($row_edit) . '">' . esc_html__('编辑', 'linked3') . '</a> | <a href="' . esc_url($dl_md) . '">' . esc_html__('下载 MD', 'linked3') . '</a> | <a href="' . esc_url($dl_json) . '">' . esc_html__('下载 JSON', 'linked3') . '</a></div>';
        echo '</td>';
        echo '<td><span class="badge badge-' . esc_attr($type ?: 'fixed') . '">' . esc_html(self::$TYPES[$type] ?? $type) . '</span></td>';
        echo '<td>' . esc_html($parent ?: '—') . '</td>';
        echo '<td><a class="button button-small" href="' . esc_url($row_edit) . '">' . esc_html__('编辑', 'linked3') . '</a></td>';
        echo '</tr>';
    }

    static function render_edit_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('无权限', 'linked3'));
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $seed = $post_id ? GenesisSeedCPT::get($post_id) : null;

        if (!$seed) {
            echo '<div class="wrap linked3-seed-wrap"><p>' . esc_html__('Seed 不存在, 请从列表页进入。', 'linked3') . '</p></div>';
            return;
        }

        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $list_url = admin_url('admin.php?page=' . self::PAGE_SLUG_LIST);

        echo '<div class="wrap linked3-seed-wrap">';
        echo '<h2>' . esc_html__('编辑 Seed DNA', 'linked3') . ': ' . esc_html($seed['title']) . ' <a class="page-title-action" href="' . esc_url($list_url) . '">' . esc_html__('返回列表', 'linked3') . '</a></h2>';

        echo '<form id="linked3-seed-edit-form">';
        echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '" />';

        echo '<div class="linked3-tabs">';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#tab-basic" class="nav-tab nav-tab-active">' . esc_html__('基本', 'linked3') . '</a>';
        echo '<a href="#tab-visual" class="nav-tab">' . esc_html__('视觉 DNA', 'linked3') . '</a>';
        echo '<a href="#tab-personality" class="nav-tab">' . esc_html__('性格 DNA', 'linked3') . '</a>';
        echo '<a href="#tab-priority" class="nav-tab">' . esc_html__('优先级 & 锁定', 'linked3') . '</a>';
        echo '<a href="#tab-adapter" class="nav-tab">' . esc_html__('AI 适配', 'linked3') . '</a>';
        echo '</h2>';

        // Tab 1: Basic
        echo '<div id="tab-basic" class="tab-panel active">';
        self::render_basic_fields($seed);
        echo '</div>';

        // Tab 2: Visual DNA
        echo '<div id="tab-visual" class="tab-panel">';
        self::render_visual_dna_fields($seed);
        echo '</div>';

        // Tab 3: Personality DNA
        echo '<div id="tab-personality" class="tab-panel">';
        self::render_personality_dna_fields($seed);
        echo '</div>';

        // Tab 4: Priority & Lock
        echo '<div id="tab-priority" class="tab-panel">';
        self::render_priority_lock_fields($seed);
        echo '</div>';

        // Tab 5: AI Adapter
        echo '<div id="tab-adapter" class="tab-panel">';
        self::render_ai_adapter_fields($seed);
        echo '</div>';

        echo '</div>'; // .linked3-tabs

        echo '<p style="margin-top:15px;"><button type="button" class="button button-primary button-large linked3-save-seed-btn" data-nonce="' . esc_attr($nonce) . '">' . esc_html__('保存', 'linked3') . '</button></p>';

        echo '</form>';
        echo '</div>';
    }

    static function render_new_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('无权限', 'linked3'));
        }

        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $list_url = admin_url('admin.php?page=' . self::PAGE_SLUG_LIST);

        // 父 Seed 候选列表
        $all_seeds_q = new \WP_Query([
            'post_type'      => 'linked3_seed',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        echo '<div class="wrap linked3-seed-wrap">';
        echo '<h2>' . esc_html__('新建 Seed DNA', 'linked3') . ' <a class="page-title-action" href="' . esc_url($list_url) . '">' . esc_html__('返回列表', 'linked3') . '</a></h2>';

        echo '<div class="linked3-notice-info">' . esc_html__('7 步前置门禁: 按顺序完成 7 个步骤, 系统会在每步检查最小输入, 不满足将阻止前进。', 'linked3') . '</div>';

        echo '<form id="linked3-seed-new-form" class="linked3-wizard">';

        // 步骤指示器
        echo '<div class="step-indicator">';
        $steps = ['1.选分类', '2.选类型', '3.视觉DNA', '4.优先级', '5.锁定', '6.继承父Seed', '7.保存'];
        foreach ($steps as $i => $s) {
            echo '<div class="step"' . ($i === 0 ? ' active' : '') . '>' . esc_html($s) . '</div>';
        }
        echo '</div>';

        // Step 1: 选分类
        echo '<div class="step-pane active" data-step="0">';
        echo '<h3>' . esc_html__('第 1 步 · 选择 Seed 分类', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('分类决定 Seed 在分镜中的角色定位, 不可后期修改。', 'linked3') . '</p>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('分类', 'linked3') . '</label><select name="seed_category" required>';
        foreach (self::$CATEGORIES as $c => $l) {
            echo '<option value="' . esc_attr($c) . '">' . esc_html($l) . ' (' . esc_html($c) . ')</option>';
        }
        echo '</select></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('Seed 名称', 'linked3') . '</label><input type="text" name="title" required placeholder="给 Seed 起个名字" /></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('Seed ID', 'linked3') . '</label><input type="text" name="seed_id" placeholder="例如: C2_白龙_v1 (留空自动生成)" /></div>';
        echo '<div class="step-nav"><span></span><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 2: 选类型
        echo '<div class="step-pane" data-step="1">';
        echo '<h3>' . esc_html__('第 2 步 · 选择 Seed 类型', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('固定 = 跨分镜不变 (CharacterSeed/BrandSeed); 可变 = 随分镜推进演化 (SceneSeed/PropSeed)。', 'linked3') . '</p>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('类型', 'linked3') . '</label><select name="seed_type" required>';
        foreach (self::$TYPES as $t => $l) {
            echo '<option value="' . esc_attr($t) . '">' . esc_html($l) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('所属项目', 'linked3') . '</label><input type="text" name="project_ref" placeholder="文章 ID / 项目名 (可选)" /></div>';
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 3: 视觉 DNA
        echo '<div class="step-pane" data-step="2">';
        echo '<h3>' . esc_html__('第 3 步 · 填写视觉 DNA', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('至少填写一个视觉字段才能进入下一步。', 'linked3') . '</p>';
        foreach (self::$VISUAL_FIELDS as $key => $label) {
            echo '<div class="linked3-form-row"><label>' . esc_html($label) . '</label>';
            echo '<textarea name="visual_dna[' . esc_attr($key) . ']" placeholder="' . esc_attr($label) . ' 描述..."></textarea>';
            echo '</div>';
        }
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 4: 优先级
        echo '<div class="step-pane" data-step="3">';
        echo '<h3>' . esc_html__('第 4 步 · 设置优先级', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('每行一条规则, 可留空。', 'linked3') . '</p>';
        foreach (self::$PRIORITY_GROUPS as $key => $label) {
            echo '<div class="linked3-form-row"><label>' . esc_html($label) . '</label>';
            echo '<textarea name="priority[' . esc_attr($key) . ']" placeholder="' . esc_attr__('每行一条', 'linked3') . '"></textarea>';
            echo '</div>';
        }
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 5: 锁定
        echo '<div class="step-pane" data-step="4">';
        echo '<h3>' . esc_html__('第 5 步 · 设置锁定项', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('锁定字段名 (如 face, costume), 锁定后不会被后续步骤修改。每行一个, 可留空。', 'linked3') . '</p>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('锁定字段', 'linked3') . '</label>';
        echo '<textarea name="lock" placeholder="' . esc_attr__('face' . "\n" . 'costume', 'linked3') . '"></textarea>';
        echo '</div>';
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 6: 继承父 Seed
        echo '<div class="step-pane" data-step="5">';
        echo '<h3>' . esc_html__('第 6 步 · 继承父 Seed (可选)', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('从父 Seed 继承 visual_dna / personality_dna, 覆盖子 Seed 已填字段。留空表示无父 Seed。', 'linked3') . '</p>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('父 Seed', 'linked3') . '</label><select name="parent_seed"><option value="">' . esc_html__('— 无 (顶层 Seed) —', 'linked3') . '</option>';
        foreach ($all_seeds_q->posts as $p) {
            $psid = get_post_meta($p->ID, 'seed_id', true);
            echo '<option value="' . esc_attr($psid ?: 'pid-' . $p->ID) . '">' . esc_html($p->post_title . ' (' . ($psid ?: $p->ID) . ')') . '</option>';
        }
        echo '</select></div>';
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary step-next">' . esc_html__('下一步 →', 'linked3') . '</button></div>';
        echo '</div>';

        // Step 7: 保存
        echo '<div class="step-pane" data-step="6">';
        echo '<h3>' . esc_html__('第 7 步 · 确认 & 保存', 'linked3') . '</h3>';
        echo '<p class="description">' . esc_html__('AI 适配字段可留空, 创建后可在编辑页填写。', 'linked3') . '</p>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('Midjourney prompt', 'linked3') . '</label><textarea name="ai_adapter[mj]" placeholder="MJ 专用 prompt (可选)"></textarea></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('Stable Diffusion prompt', 'linked3') . '</label><textarea name="ai_adapter[sd]" placeholder="SD 专用 prompt (可选)"></textarea></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('Flux prompt', 'linked3') . '</label><textarea name="ai_adapter[flux]" placeholder="Flux 专用 prompt (可选)"></textarea></div>';
        echo '<div class="linked3-form-row"><label>' . esc_html__('DALL-E prompt', 'linked3') . '</label><textarea name="ai_adapter[dalle]" placeholder="DALL-E 专用 prompt (可选)"></textarea></div>';
        echo '<div class="step-nav"><button type="button" class="button step-prev">' . esc_html('← 上一步', 'linked3') . '</button><button type="button" class="button button-primary button-large linked3-wizard-save" data-nonce="' . esc_attr($nonce) . '">' . esc_html__('💾 保存 Seed', 'linked3') . '</button></div>';
        echo '</div>';

        echo '</form>';

        echo '<div class="linked3-danger-zone" style="margin-top:30px;">';
        echo '<h4 style="margin-top:0;color:#d63638;">' . esc_html__('危险区: 清空所有 Seed', 'linked3') . '</h4>';
        echo '<p>' . esc_html__('软删除所有 Seed (30天可恢复), 创建前请确保旧 Seed 已备份。', 'linked3') . '</p>';
        echo '<button type="button" class="button button-link-delete linked3-trash-all-btn" data-nonce="' . esc_attr(wp_create_nonce(self::NONCE_ACTION_TRASH)) . '">' . esc_html__('清空所有 Seed', 'linked3') . '</button>';
        echo '</div>';

        echo '</div>';
    }

}
