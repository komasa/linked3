<?php
/**
 * Seed Admin — thin delegate (G4.1 split).
 * Original 1415-line God Class split into 3 focused classes:
 *   - Linked3_Seed_Admin_Render (page rendering + menu)
 *   - Linked3_Seed_Admin_Ajax (AJAX handlers)
 *   - Linked3_Seed_Admin_Export (export functions)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @deprecated G4.1 Use the split classes directly.
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Linked3_Seed_Admin
{
    public static function register_menu() : mixed { return Linked3_Seed_Admin_Render::register_menu(); }
    public static function hide_seed_submenus() : mixed { return Linked3_Seed_Admin_Render::hide_seed_submenus(); }
    public static function enqueue_assets($hook) : mixed { return Linked3_Seed_Admin_Render::enqueue_assets($hook); }
    public static function print_inline_assets() : mixed { return Linked3_Seed_Admin_Render::print_inline_assets(); }
    public static function render_list_page() : mixed { return Linked3_Seed_Admin_Render::render_list_page(); }
    public static function render_edit_page() : mixed { return Linked3_Seed_Admin_Render::render_edit_page(); }
    public static function render_new_page() : mixed { return Linked3_Seed_Admin_Render::render_new_page(); }
    public static function handle_bulk_post() : mixed { return Linked3_Seed_Admin_Render::handle_bulk_post(); }
    public static function ajax_save_seed() : mixed { return Linked3_Seed_Admin_Ajax::ajax_save_seed(); }
    public static function ajax_trash_all() : mixed { return Linked3_Seed_Admin_Ajax::ajax_trash_all(); }
    public static function ajax_download_seed() : mixed { return Linked3_Seed_Admin_Ajax::ajax_download_seed(); }
    public static function ajax_export_batch() : mixed { return Linked3_Seed_Admin_Ajax::ajax_export_batch(); }
    public static function export_md($post_id) : mixed { return Linked3_Seed_Admin_Export::export_md($post_id); }
    public static function export_json($post_id) : mixed { return Linked3_Seed_Admin_Export::export_json($post_id); }
    public static function export_batch($filter) : mixed { return Linked3_Seed_Admin_Export::export_batch($filter); }
}
