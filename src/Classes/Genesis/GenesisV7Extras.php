<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisV7Extras
{
    public static function instance() : mixed { return GenesisV7Helpers::instance(); }

    public function loadAll() : mixed { return GenesisV7Helpers::loadAll(); }

}
