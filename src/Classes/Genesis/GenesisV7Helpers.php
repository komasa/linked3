<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisV7Helpers
{
    public static function instance() : mixed { return GenesisV7Generator::instance(); }

    public function loadAll() : mixed { return GenesisV7Loader::loadAll(); }

}
