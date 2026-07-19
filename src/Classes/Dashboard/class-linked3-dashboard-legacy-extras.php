<?php
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class Linked3_Dashboard_Legacy_Extras
{
    public static function ajax_template_add() : mixed { return Linked3_Dashboard_Legacy_Delegates::ajax_template_add(); }

    public static function ajax_template_update() : mixed { return Linked3_Dashboard_Legacy_Delegates::ajax_template_update(); }

    public static function ajax_template_delete() : mixed { return Linked3_Dashboard_Legacy_Delegates::ajax_template_delete(); }

    public static function ajax_template_get() : mixed { return Linked3_Dashboard_Legacy_Delegates::ajax_template_get(); }

    public static function ajax_sync_models() : mixed { return Linked3_Dashboard_Legacy_Delegates::ajax_sync_models(); }

}
