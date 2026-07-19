<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class DashboardLegacyDelegates
{
    public static function ajax_template_add() : mixed {
        return Ajax\Actions\DashboardTemplateActions::template_add();
    }

    public static function ajax_template_update() : mixed     {
        return Ajax\Actions\DashboardTemplateActions::template_update();
    }

    public static function ajax_template_delete() : mixed {
        return Ajax\Actions\DashboardTemplateActions::template_delete();
    }

    public static function ajax_template_get() : mixed {
        return Ajax\Actions\DashboardTemplateActions::template_get();
    }

    public static function ajax_sync_models() : mixed { return DashboardConfigAjax::ajax_sync_models(); }

}
