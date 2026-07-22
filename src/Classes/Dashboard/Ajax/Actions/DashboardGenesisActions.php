<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\GenesisProcessor;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard genesis actions.
 *
 * G2.3: All implementations now delegate to GenesisProcessor.
 * The Processor owns all Genesis business logic (extracted from God Class).
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 * @migrated   G2.3 (2026-07-18)
 */

class DashboardGenesisActions extends DashboardBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_content_writer';
    const REQUIRED_CAP = 'edit_posts';

    public static function register(): void {
        // Genesis generation endpoints
        add_action('wp_ajax_linked3_genesis_generate', [__CLASS__, 'genesis_generate']);
        add_action('wp_ajax_linked3_genesis_styles', [__CLASS__, 'genesis_styles']);
        add_action('wp_ajax_linked3_genesis_generate_multi', [__CLASS__, 'genesis_generate_multi']);
        add_action('wp_ajax_linked3_genesis_test_connection', [__CLASS__, 'genesis_test_connection']);

        // Genesis job lifecycle
        add_action('wp_ajax_linked3_genesis_start_job', [__CLASS__, 'genesis_start_job']);
        add_action('wp_ajax_linked3_genesis_poll_job', [__CLASS__, 'genesis_poll_job']);
        add_action('wp_ajax_linked3_genesis_cancel_job', [__CLASS__, 'genesis_cancel_job']);

        // Genesis SEED management
        add_action('wp_ajax_linked3_genesis_seed_generate', [__CLASS__, 'genesis_seed_generate']);
        add_action('wp_ajax_linked3_genesis_seed_list', [__CLASS__, 'genesis_seed_list']);
        add_action('wp_ajax_linked3_genesis_seed_delete', [__CLASS__, 'genesis_seed_delete']);
        add_action('wp_ajax_linked3_genesis_seed_export', [__CLASS__, 'genesis_seed_export']);
        add_action('wp_ajax_linked3_genesis_server_diagnostic', [__CLASS__, 'genesis_server_diagnostic']);

        // G2.3: Cron hook (was only in Legacy register() before — now properly registered here)
        add_action('linked3_genesis_run_job', [GenesisProcessor::class, 'cron_genesis_run_job']);
    }

    public static function genesis_generate() : mixed { return GenesisProcessor::ajax_genesis_generate(); }
    public static function genesis_styles() : mixed { return GenesisProcessor::ajax_genesis_styles(); }
    public static function genesis_generate_multi() : mixed { return GenesisProcessor::ajax_genesis_generate_multi(); }
    public static function genesis_test_connection() : mixed { return GenesisProcessor::ajax_genesis_test_connection(); }
    public static function genesis_start_job() : mixed { return GenesisProcessor::ajax_genesis_start_job(); }
    public static function genesis_poll_job() : mixed { return GenesisProcessor::ajax_genesis_poll_job(); }
    public static function genesis_cancel_job() : mixed { return GenesisProcessor::ajax_genesis_cancel_job(); }
    public static function genesis_seed_generate() : mixed { return GenesisProcessor::ajax_genesis_seed_generate(); }
    public static function genesis_seed_list() : mixed { return GenesisProcessor::ajax_genesis_seed_list(); }
    public static function genesis_seed_delete() : mixed { return GenesisProcessor::ajax_genesis_seed_delete(); }
    public static function genesis_seed_export() : mixed { return GenesisProcessor::ajax_genesis_seed_export(); }
    public static function genesis_server_diagnostic() : mixed { return GenesisProcessor::ajax_genesis_server_diagnostic(); }
}
