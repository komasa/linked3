<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class DashboardLegacyExtras
{
    public static function ajax_template_add() : mixed { return DashboardLegacyDelegates::ajax_template_add(); }

    public static function ajax_template_update() : mixed { return DashboardLegacyDelegates::ajax_template_update(); }

    public static function ajax_template_delete() : mixed { return DashboardLegacyDelegates::ajax_template_delete(); }

    public static function ajax_template_get() : mixed { return DashboardLegacyDelegates::ajax_template_get(); }

    public static function ajax_sync_models() : mixed { return DashboardLegacyDelegates::ajax_sync_models(); }

}
