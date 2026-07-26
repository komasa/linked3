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
    use SeedAdminConstants;

    // v27.8.3: The 6 shared constants (PAGE_SLUG_LIST/EDIT/NEW, CAPABILITY,
    // NONCE_ACTION, NONCE_ACTION_TRASH) now live in the SeedAdminConstants
    // trait. This eliminates the duplication that caused the v27.8.0 Fatal
    // Error (constants were missing from some split classes).

    static function register_menu(): void {
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

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    static function enqueue_assets($hook): void {
        // Enqueue seed admin CSS on all Linked3 admin pages (submenu hiding is global)
        wp_enqueue_style(
            'linked3-seed-admin',
            LINKED3_URL . 'assets/css/linked3-seed-admin.css',
            [],
            LINKED3_VERSION
        );

        if (strpos($hook, self::PAGE_SLUG_LIST) === false
            && strpos($hook, self::PAGE_SLUG_EDIT) === false
            && strpos($hook, self::PAGE_SLUG_NEW) === false) {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-tabs', false, ['jquery'], false, true);

        // v29.1.0 Step 4: External JS file + wp_localize_script
        wp_enqueue_script(
            'linked3-seed-admin-js',
            LINKED3_URL . 'admin/js/linked3-seed-admin.js',
            ['jquery', 'linked3-fetch'],
            LINKED3_VERSION,
            true
        );
        wp_localize_script('linked3-seed-admin-js', 'linked3_seed', [
            'list_url' => esc_js(admin_url('admin.php?page=' . self::PAGE_SLUG_LIST)),
        ]);
    }

    public static function print_inline_assets() : void {
        // v29.1.0 Step 4: All inline JS extracted to admin/js/linked3-seed-admin.js
        return null;
    }

    public static function render_list_page() : mixed { return SeedAdminPages::render_list_page(); }

        public static function render_edit_page() : mixed { return SeedAdminPages::render_edit_page(); }

        public static function render_new_page() : mixed { return SeedAdminPages::render_new_page(); }

    static function handle_bulk_post(): void {
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
            $files = SeedAdminExport::export_batch($filter);
            if ($fmt === 'json') {
                $merged = [];
                foreach ($files as $f) {
                    $merged[] = json_decode(file_get_contents($f), true);
                    @unlink($f);
                }
                $content = wp_json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="linked3-seeds-batch.json"');
                printf('%s', $content);
                exit;
            } else {
                $content = '';
                foreach ($files as $f) {
                    $content .= file_get_contents($f) . "\n\n---\n\n";
                    @unlink($f);
                }
                header('Content-Type: text/markdown; charset=utf-8');
                header('Content-Disposition: attachment; filename="linked3-seeds-batch.md"');
                printf('%s', $content);
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG_LIST));
        exit;
    }


}
