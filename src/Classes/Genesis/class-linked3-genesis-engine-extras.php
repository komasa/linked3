<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_Engine_Extras
{
    public function searchKeyword(string $keyword) : mixed { return Linked3_Genesis_Engine_Helpers::searchKeyword($keyword); }

    public function getByType(string $type) : mixed { return Linked3_Genesis_Engine_Helpers::getByType($type); }

}
