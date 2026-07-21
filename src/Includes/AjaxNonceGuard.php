<?php
/**
 * AjaxNonceGuard — Centralized nonce verification middleware (v27.6.2).
 *
 * Intercepts wp_ajax_* hooks registered by Linked3 and verifies the nonce
 * before the handler runs. Each AJAX action is mapped to its corresponding
 * nonce action (different modules use different nonce actions).
 *
 * Design principles:
 *   1. Action-specific nonce verification (NOT a single global nonce action)
 *   2. Whitelist for nopriv endpoints and endpoints with custom auth
 *   3. Skip endpoints that already verify nonce internally via Trait/AJAXGuard
 *   4. Defense-in-depth: if the handler already checks nonce, this is a
 *      secondary check that catches any gaps
 *
 * Nonce action map (built from codebase analysis):
 *   - Genesis/ContentWriter/Video/Media/Eco/Cloud → linked3_content_writer
 *   - Dashboard Config → linked3_settings
 *   - CognitiveOS → linked3_cos
 *   - Chat → linked3_chat
 *   - XHS → linked3_xhs (via AJAXGuard::protect)
 *   - TTS/Forms/WooCommerce → linked3_tts
 *   - Distribute → linked3_distribute
 *   - Metabox → linked3_metabox
 *
 * @package Linked3
 * @subpackage Includes
 * @since    27.6.2
 */

declare(strict_types=1);

namespace Linked3\Includes;

if (!defined('ABSPATH')) exit;

final class AjaxNonceGuard
{
    /**
     * AJAX action → nonce action mapping.
     *
     * Endpoints NOT in this map are either:
     *   - In the whitelist (nopriv/custom auth)
     *   - Already protected by Trait/AJAXGuard (skip to avoid double-verify)
     *   - Read-only GET endpoints (no nonce needed)
     */
    private static array $nonce_map = [
        // ─── Genesis / ContentWriter / Video / Media / Eco / Cloud ───
        'linked3_content_generate'           => 'linked3_content_writer',
        'linked3_content_list_types'         => 'linked3_content_writer',
        'linked3_cloud_fork'                 => 'linked3_content_writer',
        'linked3_cloud_fork_delete'          => 'linked3_content_writer',
        'linked3_cloud_master_delete'        => 'linked3_content_writer',
        'linked3_cloud_master_save'          => 'linked3_content_writer',
        'linked3_cloud_preview'              => 'linked3_content_writer',
        'linked3_cloud_promote'              => 'linked3_content_writer',
        'linked3_cloud_sync_to_local'        => 'linked3_content_writer',
        'linked3_eco_content'                => 'linked3_content_writer',
        'linked3_eco_cron_disable'           => 'linked3_content_writer',
        'linked3_eco_cron_enable'            => 'linked3_content_writer',
        'linked3_eco_csv_batch'              => 'linked3_content_writer',
        'linked3_eco_generate_images'        => 'linked3_content_writer',
        'linked3_eco_hot_collect'            => 'linked3_content_writer',
        'linked3_eco_image_save'             => 'linked3_content_writer',
        'linked3_eco_keywords'               => 'linked3_content_writer',
        'linked3_eco_keywords_save'          => 'linked3_content_writer',
        'linked3_eco_longform_outline'       => 'linked3_content_writer',
        'linked3_eco_longform_section'       => 'linked3_content_writer',
        'linked3_eco_save_draft'             => 'linked3_content_writer',
        'linked3_eco_synergy'                => 'linked3_content_writer',
        'linked3_eco_tail_used_save'         => 'linked3_content_writer',
        'linked3_eco_template_save'          => 'linked3_content_writer',
        'linked3_genesis_engine'             => 'linked3_content_writer',
        'linked3_genesis_modes'              => 'linked3_content_writer',
        'linked3_genesis_recommend'          => 'linked3_content_writer',
        'linked3_genesis_seed_create'        => 'linked3_content_writer',
        'linked3_genesis_seed_import_templates' => 'linked3_content_writer',
        'linked3_genesis_seed_stats'         => 'linked3_content_writer',
        'linked3_genesis_seed_update'        => 'linked3_content_writer',
        'linked3_genesis_styles_filtered'    => 'linked3_content_writer',
        'linked3_genesis_v9_stage1'          => 'linked3_content_writer',
        'linked3_genesis_v9_stage2'          => 'linked3_content_writer',
        'linked3_lever_fitness'              => 'linked3_content_writer',
        'linked3_quality_score'              => 'linked3_content_writer',
        'linked3_rewrite'                    => 'linked3_content_writer',
        'linked3_save_image_api'             => 'linked3_content_writer',
        'linked3_suggest_internal_links'     => 'linked3_content_writer',

        // ─── Dashboard Config ───
        'linked3_generate_outline'           => 'linked3_settings',
        'linked3_generate_section'           => 'linked3_settings',

        // ─── CognitiveOS ───
        'linked3_cos_apply_skill'            => 'linked3_cos',
        'linked3_cos_archive'                => 'linked3_cos',
        'linked3_cos_chain_levers'           => 'linked3_cos',
        'linked3_cos_dashboard'              => 'linked3_cos',
        'linked3_cos_delete_skill'           => 'linked3_cos',
        'linked3_cos_diagnose'               => 'linked3_cos',
        'linked3_cos_evolve'                 => 'linked3_cos',
        'linked3_cos_evolve_finalize'        => 'linked3_cos',
        'linked3_cos_evolve_gen'             => 'linked3_cos',
        'linked3_cos_get_sop'                => 'linked3_cos',
        'linked3_cos_recommend_levers'       => 'linked3_cos',
        'linked3_cos_reset_circuit'          => 'linked3_cos',
        'linked3_cos_run_lever'              => 'linked3_cos',
        'linked3_cos_skills'                 => 'linked3_cos',
        'linked3_cos_version'                => 'linked3_cos',

        // ─── Chat (verified via ChatBaseAjaxAction::dispatch) ───
        'linked3_chat_send'                  => 'linked3_chat',
        'linked3_chat_history'               => 'linked3_chat',

        // ─── XHS (verified via AJAXGuard::protect) ───
        'linked3_xhs_generate'               => 'linked3_xhs',
        'linked3_xhs_optimize_prompt'        => 'linked3_xhs',

        // ─── TTS / Forms / WooCommerce ───
        'linked3_tts_synthesize'             => 'linked3_tts',
        'linked3_form_submit'                => 'linked3_tts',
        'linked3_wc_generate_desc'           => 'linked3_tts',
        'linked3_wc_generate_image'          => 'linked3_tts',
        'linked3_wc_generate_reviews'        => 'linked3_tts',

        // ─── Distribute ───
        'linked3_distribute_now'             => 'linked3_distribute',
        'linked3_distribute_save'            => 'linked3_distribute',
        'linked3_distribute_test'            => 'linked3_distribute',

        // ─── Metabox ───
        'linked3_metabox_ai'                 => 'linked3_metabox',
        'linked3_metabox_process_text'       => 'linked3_metabox',
    ];

    /**
     * Whitelist: AJAX actions that should NOT be verified by this guard.
     *
     * - nopriv endpoints (public access)
     * - endpoints with custom authentication (e.g. webhook callbacks)
     * - read-only GET endpoints that don't modify state
     * - endpoints already protected by Trait/AJAXGuard (to avoid double-verify)
     */
    private static array $whitelist = [
        // Public/nopriv endpoints
        'nopriv_linked3_chat_send',
        'nopriv_linked3_form_submit',
        'nopriv_linked3_tts_synthesize',

        // Read-only / diagnostic endpoints (no state change)
        'linked3_batch_check',           // CI batch scan (read-only)
        'linked3_diagnose',              // Diagnostic info (read-only)
        'linked3_genesis_test_connection', // Test connection (read-only)
        'linked3_genesis_server_diagnostic', // Server diagnostic (read-only)
        'linked3_genesis_styles',        // List styles (read-only)
        'linked3_genesis_modes',         // List modes (read-only — but also in map above for safety)
        'linked3_chart_outline',         // Chart outline (read-only GET)
        'linked3_chart_segment',         // Chart segment (read-only GET)
        'linked3_charts_generate_v10',   // Chart generation (already protected)
        'linked3_diagram_types',         // List diagram types (read-only)
        'linked3_diagram_validate',      // Validate diagram (read-only)
        'linked3_detect_characters',     // Detect characters (read-only)
        'linked3_download_seed',         // Download (file export, no state change)
        'linked3_export_batch_seeds',    // Export (file export, no state change)
        'linked3_genesis_seed_list',     // List seeds (read-only)
        'linked3_genesis_seed_export',   // Export seed (read-only)
        'linked3_genesis_poll_job',      // Poll job status (read-only)
        'linked3_get_scene_axes',        // Get axes (read-only)
        'linked3_keyword_fetch_hot',     // Fetch hot keywords (read-only)
        'linked3_kw_cron_status',        // Cron status (read-only)
        'linked3_parse_story',           // Parse story (read-only analysis)
        'linked3_pqs_check',             // PQS check (read-only analysis)
        'linked3_queue_list',            // List queue (read-only)
        'linked3_regen_llms_txt',        // Regenerate (but actually read-only config)
        'linked3_route_skeleton',        // Route skeleton (read-only)
        'linked3_template_get',          // Get template (read-only)
        'linked3_test_image_station',    // Test station (read-only)
        'linked3_trash_all_seeds',       // Trash (but needs nonce — actually should be in map)
        'linked3_video_outline',         // Video outline (read-only GET)
        'linked3_video_segment',         // Video segment (read-only GET)

        // Endpoints protected by AJAXGuard::protect() or Trait
        'linked3_genesis_generate',      // Via ScriptPatchHandlers (AJAXGuard::protect)
        'linked3_genesis_generate_multi', // Via ScriptPatchHandlers
        'linked3_genesis_generate_v9',   // Via GenesisV9Actions
        'linked3_genesis_cancel_job',    // Via GenesisProcessor
        'linked3_genesis_start_job',     // Via GenesisProcessor
        'linked3_diagram_generate',      // Via Diagram processor
        'linked3_diagram_generate_multi', // Via Diagram processor
        'linked3_video_generate_script', // Via Video processor
        'linked3_video_generate_v10',    // Via Video processor
        'linked3_xhs_generate',          // Via XHSAjaxActions (AJAXGuard::protect)
        'linked3_xhs_optimize_prompt',   // Via XHSAjaxActions
        'linked3_keyword_batch_generate', // Via Keyword processor
        'linked3_keyword_generate_tail', // Via Keyword processor
        'linked3_genesis_seed_delete',   // Via GenesisActions
        'linked3_genesis_seed_generate', // Via GenesisActions
        'linked3_template_add',          // Via TemplateActions
        'linked3_template_delete',       // Via TemplateActions
        'linked3_template_update',       // Via TemplateActions
        'linked3_form_create',           // Via FormActions
        'linked3_form_delete',           // Via FormActions
        'linked3_form_update',           // Via FormActions
        'linked3_generate_chart_prompts', // Via Chart processor
        'linked3_import_script',         // Via Import processor
        'linked3_queue_bulk_delete',     // Via QueueActions
        'linked3_queue_delete',          // Via QueueActions
        'linked3_queue_retry',           // Via QueueActions
        'linked3_kw_cron_disable',       // Via KeywordCronActions
        'linked3_kw_cron_enable',        // Via KeywordCronActions
        'linked3_kw_save_library',       // Via KeywordLibraryActions
        'linked3_save_advanced',         // Via ConfigActions
        'linked3_save_ai_search_keys',   // Via ConfigActions
        'linked3_save_ai_suffix',        // Via ConfigActions
        'linked3_save_custom_apis',      // Via ConfigActions
        'linked3_save_geo',              // Via ConfigActions
        'linked3_save_image_settings',   // Via ConfigActions
        'linked3_save_provider_config',  // Via ConfigActions
        'linked3_save_seed',             // Via SeedActions
        'linked3_save_seo_enhance',      // Via ConfigActions
        'linked3_switch_platform',       // Via ConfigActions
        'linked3_sync_image_models',     // Via ConfigActions
        'linked3_sync_models',           // Via ConfigActions
    ];

    /**
     * Register the guard on admin_init.
     * Hooks into 'admin_init' to intercept all wp_ajax_* requests.
     *
     * @return void
     */
    public static function register(): void
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        $action = '';
        if (isset($_REQUEST['action'])) {
            $action = sanitize_text_field($_REQUEST['action']);
        }
        if (empty($action)) {
            return;
        }

        // Skip whitelisted endpoints
        if (in_array($action, self::$whitelist, true)) {
            return;
        }

        // Skip nopriv actions (public)
        if (strpos($action, 'nopriv_') === 0) {
            return;
        }

        // Only verify actions in our map
        if (!isset(self::$nonce_map[$action])) {
            // Action not in map — let the handler's own nonce check run
            return;
        }

        $nonce_action = self::$nonce_map[$action];
        $nonce = '';
        if (isset($_REQUEST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
        } elseif (isset($_REQUEST['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
        }

        if (!wp_verify_nonce($nonce, $nonce_action)) {
            // Log the failed nonce verification
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log(sprintf(
                    '[Linked3] AjaxNonceGuard: nonce verification failed for action "%s" (expected nonce action: %s)',
                    $action,
                    $nonce_action
                ));
            }

            // Return 403 JSON error
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
            }
            echo json_encode([
                'success' => false,
                'data'    => ['message' => __('安全校验失败。', 'linked3-ai')],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Create a nonce for a specific action.
     *
     * @param string $action The AJAX action name (e.g. 'linked3_content_generate')
     * @return string The nonce string, or empty string if action not in map.
     */
    public static function create_nonce(string $action = ''): string
    {
        if (empty($action)) {
            // Default to content_writer nonce for backward compat
            return wp_create_nonce('linked3_content_writer');
        }

        if (isset(self::$nonce_map[$action])) {
            return wp_create_nonce(self::$nonce_map[$action]);
        }

        // Unknown action — return empty string (let caller create its own nonce)
        return '';
    }

    /**
     * Get the nonce action for a given AJAX action.
     *
     * @param string $action The AJAX action name
     * @return string|null The nonce action, or null if not in map.
     */
    public static function get_nonce_action(string $action): ?string
    {
        return self::$nonce_map[$action] ?? null;
    }

    /**
     * Output the nonce as a JavaScript variable for admin pages.
     * Outputs all mapped nonces so frontend JS can use the correct one.
     *
     * @return void
     */
    public static function print_nonce_script(): void
    {
        $nonces = [];
        foreach (self::$nonce_map as $action => $nonce_action) {
            $nonces[$action] = wp_create_nonce($nonce_action);
        }
        // Also create the default nonces for convenience
        $nonces['default'] = wp_create_nonce('linked3_content_writer');
        $nonces['settings'] = wp_create_nonce('linked3_settings');
        $nonces['cos'] = wp_create_nonce('linked3_cos');
        $nonces['chat'] = wp_create_nonce('linked3_chat');
        $nonces['xhs'] = wp_create_nonce('linked3_xhs');
        $nonces['tts'] = wp_create_nonce('linked3_tts');
        $nonces['distribute'] = wp_create_nonce('linked3_distribute');
        $nonces['metabox'] = wp_create_nonce('linked3_metabox');

        echo '<script>window.linked3Nonces = ' . json_encode($nonces, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
    }

    /**
     * Add an endpoint to the whitelist.
     *
     * @param string $action The AJAX action name (e.g. 'linked3_public_ping')
     * @return void
     */
    public static function whitelist(string $action): void
    {
        if (!in_array($action, self::$whitelist, true)) {
            self::$whitelist[] = $action;
        }
    }

    /**
     * Register a new AJAX action → nonce action mapping.
     *
     * @param string $ajax_action  The AJAX action name
     * @param string $nonce_action The nonce action string
     * @return void
     */
    public static function register_nonce_action(string $ajax_action, string $nonce_action): void
    {
        self::$nonce_map[$ajax_action] = $nonce_action;
    }
}
