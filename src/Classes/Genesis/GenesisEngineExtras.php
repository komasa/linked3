<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisEngineExtras
{
    public function searchKeyword(string $keyword) : mixed { return GenesisEngineHelpers::searchKeyword($keyword); }

    public function getByType(string $type) : mixed { return GenesisEngineHelpers::getByType($type); }

}
