<?php

declare(strict_types=1);
/**
 * BatchEngine — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale
 */

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class BatchEngine {
    private static ?BatchEngine $instance = null;
    private int $batchSize = 10;

    public static function instance(): BatchEngine {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

}
