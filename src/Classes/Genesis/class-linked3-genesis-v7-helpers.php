<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_V7_Helpers
{
    public static function instance() : mixed { return Linked3_Genesis_V7_Generator::instance(); }

    public function loadAll() : mixed { return Linked3_Genesis_V7_Loader::loadAll(); }

}
