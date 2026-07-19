<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class Linked3_Genesis_V7_Generator
{
    public static function instance(): Linked3_Genesis_SeedLibrary {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        $this->libDir = __DIR__ . '/lib';
        $this->loadAll();
    }

}
