<?php
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class Linked3_Dashboard_Legacy_Delegates
{
    public static function ajax_template_add() : mixed {
        return Ajax\Actions\Linked3_Dashboard_Template_Actions::template_add();
    }

    public static function ajax_template_update() : mixed     {
        return Ajax\Actions\Linked3_Dashboard_Template_Actions::template_update();
    }

    public static function ajax_template_delete() : mixed {
        return Ajax\Actions\Linked3_Dashboard_Template_Actions::template_delete();
    }

    public static function ajax_template_get() : mixed {
        return Ajax\Actions\Linked3_Dashboard_Template_Actions::template_get();
    }

    public static function ajax_sync_models() : mixed { return Linked3_Dashboard_Config_Ajax::ajax_sync_models(); }

}
