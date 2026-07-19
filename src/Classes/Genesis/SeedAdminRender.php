<?php

declare(strict_types=1);
/**
 * Seed Admin — Rendering (G4.1 split from SeedAdmin).
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @since      27.5.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SeedAdminRender
{
    public static function register_menu()
    : void {
        add_submenu_page(
            'linked3-dashboard',
            __('Seed DNA 库', 'linked3'),
            __('Seed DNA', 'linked3'),
            'manage_options',
            self::PAGE_SLUG_LIST,
            [__CLASS__, 'render_list_page']
        );

        // 编辑 / 新建 走隐藏子菜单 (避免污染侧边栏)
        add_submenu_page(
            'linked3-dashboard',
            __('编辑 Seed', 'linked3'),
            __('编辑 Seed', 'linked3'),
            'manage_options',
            self::PAGE_SLUG_EDIT,
            [__CLASS__, 'render_edit_page']
        );
        add_submenu_page(
            'linked3-dashboard',
            __('新建 Seed', 'linked3'),
            __('新建 Seed', 'linked3'),
            'manage_options',
            self::PAGE_SLUG_NEW,
            [__CLASS__, 'render_new_page']
        );

        add_action('admin_head', [__CLASS__, 'hide_seed_submenus']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function hide_seed_submenus()
    : void {
        echo '<style>
        #toplevel_page_linked3-dashboard .wp-submenu li a[href*="' . esc_attr(self::PAGE_SLUG_EDIT) . '"] { display:none; }
        #toplevel_page_linked3-dashboard .wp-submenu li a[href*="' . esc_attr(self::PAGE_SLUG_NEW) . '"] { display:none; }
        </style>';
    }

    public static function enqueue_assets($hook)
    : void {
        if (strpos($hook, self::PAGE_SLUG_LIST) === false
            && strpos($hook, self::PAGE_SLUG_EDIT) === false
            && strpos($hook, self::PAGE_SLUG_NEW) === false) {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-tabs', false, ['jquery'], false, true);

        // Inline CSS + JS
        add_action('admin_footer', [__CLASS__, 'print_inline_assets']);
    }

    public static function print_inline_assets() : mixed {
        ?>
        <style>
            .linked3-seed-wrap { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:20px; margin:15px 0; }
            .linked3-seed-wrap h2 { margin-top:0; }
            .linked3-seed-toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:15px; }
            .linked3-seed-toolbar input[type="search"], .linked3-seed-toolbar select { min-width:160px; }
            .linked3-seed-group { margin-bottom:20px; border:1px solid #e0e0e0; border-radius:4px; overflow:hidden; }
            .linked3-seed-group > summary { background:#f6f7f7; padding:10px 15px; cursor:pointer; font-weight:600; font-size:14px; list-style:none; display:flex; align-items:center; gap:8px; }
            .linked3-seed-group > summary::-webkit-details-marker { display:none; }
            .linked3-seed-group > summary::before { content:"\25B6"; font-size:10px; transition:transform .2s; }
            .linked3-seed-group[open] > summary::before { transform:rotate(90deg); }
            .linked3-seed-group .group-count { background:#dcdcde; color:#1d2327; border-radius:10px; padding:1px 8px; font-size:11px; font-weight:400; }
            .linked3-seed-table { width:100%; border-collapse:collapse; }
            .linked3-seed-table th, .linked3-seed-table td { padding:8px 12px; text-align:left; border-bottom:1px solid #f0f0f1; font-size:13px; }
            .linked3-seed-table th { background:#f6f7f7; font-weight:600; }
            .linked3-seed-table tr:hover { background:#f6f7f7; }
            .linked3-seed-badges .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; margin-right:4px; }
            .badge-fixed { background:#dcfce7; color:#16a34a; }
            .badge-variable { background:#fef3c7; color:#d97706; }
            .badge-cat-char { background:#dbeafe; color:#1d4ed8; }
            .badge-cat-brand { background:#fce7f3; color:#be185d; }
            .badge-cat-scene { background:#d1fae5; color:#047857; }
            .badge-cat-prop { background:#fef3c7; color:#92400e; }
            .badge-cat-style { background:#e9d5ff; color:#7e22ce; }
            .badge-cat-soul { background:#f1f5f9; color:#475569; }
            .row-actions { font-size:12px; color:#646970; }
            .row-actions a { color:#2271b1; }
            .linked3-tabs { margin-top:15px; }
            .linked3-tabs .nav-tab-wrapper { margin-bottom:0; }
            .linked3-tabs .tab-panel { display:none; border:1px solid #c3c4c7; border-top:none; padding:20px; background:#fff; }
            .linked3-tabs .tab-panel.active { display:block; }
            .linked3-form-row { margin-bottom:15px; display:grid; grid-template-columns:180px 1fr; gap:12px; align-items:start; }
            .linked3-form-row label { font-weight:600; padding-top:5px; }
            .linked3-form-row input[type="text"], .linked3-form-row input[type="number"], .linked3-form-row select, .linked3-form-row textarea { width:100%; max-width:600px; }
            .linked3-form-row textarea { min-height:80px; font-family:monospace; }
            .linked3-visual-row { display:grid; grid-template-columns:120px 1fr; gap:10px; margin-bottom:10px; align-items:center; }
            .linked3-wizard { max-width:800px; }
            .linked3-wizard .step-indicator { display:flex; margin-bottom:20px; }
            .linked3-wizard .step-indicator .step { flex:1; text-align:center; padding:8px 4px; font-size:11px; color:#646970; border-bottom:2px solid #e0e0e0; position:relative; }
            .linked3-wizard .step-indicator .step.done { color:#16a34a; border-bottom-color:#16a34a; }
            .linked3-wizard .step-indicator .step.active { color:#2271b1; border-bottom-color:#2271b1; font-weight:600; }
            .linked3-wizard .step-pane { display:none; }
            .linked3-wizard .step-pane.active { display:block; }
            .linked3-wizard .step-nav { margin-top:20px; display:flex; justify-content:space-between; }
            .linked3-empty { text-align:center; padding:40px 20px; color:#646970; }
            .linked3-empty .dashicons { font-size:48px; width:48px; height:48px; opacity:0.4; }
            .linked3-bulk-bar { background:#f6f7f7; padding:8px 12px; border-radius:4px; margin:10px 0; display:flex; gap:8px; align-items:center; }
            .linked3-danger-zone { border:1px solid #d63638; background:#fef2f2; padding:15px; border-radius:4px; margin-top:20px; }
            .linked3-notice-info { background:#e5f1f9; border-left:4px solid #2271b1; padding:10px 15px; margin:10px 0; }
        </style>
        <script>
        jQuery(function($) {
            // Tabs
            $('.linked3-tabs').each(function() {
                var $wrap = $(this);
                $wrap.find('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    $wrap.find('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    $wrap.find('.tab-panel').removeClass('active');
                    $wrap.find($(this).attr('href')).addClass('active');
                });
            });
            // Wizard
            $('.linked3-wizard').each(function() {
                var $wiz = $(this);
                function showStep(i) {
                    $wiz.find('.step-pane').removeClass('active');
                    $wiz.find('.step-pane').eq(i).addClass('active');
                    $wiz.find('.step-indicator .step').removeClass('active done');
                    $wiz.find('.step-indicator .step').each(function(idx) {
                        if (idx < i) $(this).addClass('done');
                        else if (idx === i) $(this).addClass('active');
                    });
                    $wiz.data('step', i);
                }
                showStep(0);
                $wiz.on('click', '.step-next', function(e) {
                    e.preventDefault();
                    var i = $wiz.data('step');
                    if (i < 6) showStep(i + 1);
                });
                $wiz.on('click', '.step-prev', function(e) {
                    e.preventDefault();
                    var i = $wiz.data('step');
                    if (i > 0) showStep(i - 1);
                });
            });
            // Bulk action confirm
            $(document).on('change', '.linked3-bulk-action-select', function() {
                var action = $(this).val();
                if (!action) return;
                var ids = $('.linked3-seed-cb:checked').map(function(){ return $(this).val(); }).get();
                if (ids.length === 0) { alert('请先勾选 Seed'); $(this).val(''); return; }
                if (action === 'trash' && !confirm('确定要软删除选中的 ' + ids.length + ' 个 Seed 吗？(30天内可恢复)')) { $(this).val(''); return; }
                $('#linked3-bulk-action-input').val(action);
                $('#linked3-bulk-ids-input').val(ids.join(','));
                $('#linked3-bulk-form').submit();
            });
            // Trash-all confirm
            $(document).on('click', '.linked3-trash-all-btn', function(e) {
                e.preventDefault();
                if (!confirm('警告: 此操作将软删除所有 Seed! 30天内可在回收站恢复, 确认前请先导出备份。')) return;
                if (!prompt('请输入 CONFIRM 以继续清空:')) return;
                var $btn = $(this);
                $.post(ajaxurl, {
                    action: 'linked3_trash_all_seeds',
                    nonce: $btn.data('nonce'),
                    confirm: 'CONFIRM'
                }, function(resp) {
                    if (resp.success) {
                        alert('已软删除 ' + resp.data.count + ' 个 Seed');
                        location.reload();
                    } else {
                        alert('失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
                    }
                });
            });
            // Save seed (edit page form submit)
            $(document).on('click', '.linked3-save-seed-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $form = $('#linked3-seed-edit-form');
                var data = $form.serializeArray();
                data.push({name: 'action', value: 'linked3_save_seed'});
                data.push({name: 'nonce', value: $btn.data('nonce')});
                $btn.prop('disabled', true).text('保存中...');
                $.post(ajaxurl, data, function(resp) {
                    $btn.prop('disabled', false).text('保存');
                    if (resp.success) {
                        $form.find('.linked3-save-ok').remove();
                        $btn.after(' <span class="linked3-save-ok" style="color:#16a34a;">✓ 已保存</span>');
                        setTimeout(function(){ $('.linked3-save-ok').fadeOut(); }, 2000);
                        if (resp.data.post_id && !$('input[name="post_id"]').length) {
                            $form.append('<input type="hidden" name="post_id" value="' + resp.data.post_id + '">');
                        }
                    } else {
                        alert('保存失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
                    }
                });
            });
            // Wizard save
            $(document).on('click', '.linked3-wizard-save', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $form = $('#linked3-seed-new-form');
                var data = $form.serializeArray();
                data.push({name: 'action', value: 'linked3_save_seed'});
                data.push({name: 'nonce', value: $btn.data('nonce')});
                data.push({name: 'is_new', value: '1'});
                $btn.prop('disabled', true).text('保存中...');
                $.post(ajaxurl, data, function(resp) {
                    $btn.prop('disabled', false).text('保存 Seed');
                    if (resp.success) {
                        alert('Seed 已创建, ID=' + resp.data.seed_id);
                        location.href = '<?php echo esc_js(admin_url('admin.php?page=' . self::PAGE_SLUG_LIST)); ?>';
                    } else {
                        alert('保存失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
                    }
                });
            });
        });
        </script>
        <?php
    }

        public static function render_list_page() : mixed { return SeedAdminPages::render_list_page(); }

        public static function render_edit_page() : mixed { return SeedAdminPages::render_edit_page(); }

        public static function render_new_page() : mixed { return SeedAdminPages::render_new_page(); }

    public static function handle_bulk_post()
    : void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('无权限', 'linked3'));
        }
        if (!isset($_POST['linked3_seed_bulk_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_seed_bulk_nonce'])), 'linked3_seed_bulk')) {
            wp_die(__('无效的 nonce', 'linked3'));
        }
        $action = isset($_POST['linked3_bulk_action']) ? sanitize_key($_POST['linked3_bulk_action']) : '';
        $ids    = isset($_POST['linked3_bulk_ids']) ? array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_POST['linked3_bulk_ids']))))) : [];

        if (empty($ids)) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG_LIST));
            exit;
        }

        if ($action === 'trash') {
            foreach ($ids as $pid) {
                GenesisSeedCPT::trash($pid);
            }
            wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG_LIST, 'msg' => 'trashed'], admin_url('admin.php')));
            exit;
        } elseif ($action === 'export_md' || $action === 'export_json') {
            $fmt = $action === 'export_md' ? 'md' : 'json';
            $filter = ['post_ids' => $ids, 'format' => $fmt];
            $files = self::export_batch($filter);
            if ($fmt === 'json') {
                $merged = [];
                foreach ($files as $f) {
                    $merged[] = json_decode(file_get_contents($f), true);
                    @unlink($f);
                }
                $content = wp_json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="linked3-seeds-batch.json"');
                echo $content;
                exit;
            } else {
                $content = '';
                foreach ($files as $f) {
                    $content .= file_get_contents($f) . "\n\n---\n\n";
                    @unlink($f);
                }
                header('Content-Type: text/markdown; charset=utf-8');
                header('Content-Disposition: attachment; filename="linked3-seeds-batch.md"');
                echo $content;
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG_LIST));
        exit;
    }

}
