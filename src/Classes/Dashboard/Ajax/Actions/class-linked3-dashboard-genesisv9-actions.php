<?php
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\Linked3_Dashboard_Base_Ajax_Action;
use Linked3\Classes\Dashboard\Linked3_Genesis_V9_Processor;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard genesis v9 actions.
 *
 * G2.3: All implementations now delegate to Linked3_Genesis_V9_Processor.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 * @migrated   G2.3 (2026-07-18)
 */

class Linked3_Dashboard_GenesisV9_Actions extends Linked3_Dashboard_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_content_writer';
    const REQUIRED_CAP = 'edit_posts';

    public static function register()
    : void {
        add_action('wp_ajax_linked3_genesis_generate_v9', [__CLASS__, 'genesis_generate_v9']);
        add_action('wp_ajax_linked3_genesis_v9_stage1', [__CLASS__, 'genesis_v9_stage1']);
        add_action('wp_ajax_linked3_genesis_v9_stage2', [__CLASS__, 'genesis_v9_stage2']);
    }

    public static function genesis_generate_v9() : mixed { return Linked3_Genesis_V9_Processor::ajax_genesis_generate_v9(); }
    public static function genesis_v9_stage1() : mixed { return Linked3_Genesis_V9_Processor::ajax_genesis_v9_stage1(); }
    public static function genesis_v9_stage2() : mixed { return Linked3_Genesis_V9_Processor::ajax_genesis_v9_stage2(); }
}
