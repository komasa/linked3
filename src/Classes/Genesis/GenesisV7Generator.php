<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class GenesisV7Generator
{
    public static function instance(): GenesisSeedLibrary {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        $this->libDir = __DIR__ . '/lib';
        $this->loadAll();
    }

}
