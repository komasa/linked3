<?php

declare(strict_types=1);
/**
 * Seed Admin — thin delegate (G4.1 split).
 * Original 1415-line God Class split into 3 focused classes:
 *   - SeedAdminRender (page rendering + menu)
 *   - SeedAdminAjax (AJAX handlers)
 *   - SeedAdminExport (export functions)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @deprecated G4.1 Use the split classes directly.
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SeedAdmin
{
    public static function register_menu() : mixed { return SeedAdminRender::register_menu(); }
    public static function hide_seed_submenus() : mixed { return SeedAdminRender::hide_seed_submenus(); }
    public static function enqueue_assets($hook) : mixed { return SeedAdminRender::enqueue_assets($hook); }
    public static function print_inline_assets() : mixed { return SeedAdminRender::print_inline_assets(); }
    public static function render_list_page() : mixed { return SeedAdminRender::render_list_page(); }
    public static function render_edit_page() : mixed { return SeedAdminRender::render_edit_page(); }
    public static function render_new_page() : mixed { return SeedAdminRender::render_new_page(); }
    public static function handle_bulk_post() : mixed { return SeedAdminRender::handle_bulk_post(); }
    public static function ajax_save_seed() : mixed { return SeedAdminAjax::ajax_save_seed(); }
    public static function ajax_trash_all() : mixed { return SeedAdminAjax::ajax_trash_all(); }
    public static function ajax_download_seed() : mixed { return SeedAdminAjax::ajax_download_seed(); }
    public static function ajax_export_batch() : mixed { return SeedAdminAjax::ajax_export_batch(); }
    public static function export_md($post_id) : mixed { return SeedAdminExport::export_md($post_id); }
    public static function export_json($post_id) : mixed { return SeedAdminExport::export_json($post_id); }
    public static function export_batch($filter) : mixed { return SeedAdminExport::export_batch($filter); }
}
