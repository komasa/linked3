<?php

declare(strict_types=1);
/**
 * I18nManager — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale
 */

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class I18nManager {
    private static ?I18nManager $instance = null;
    private array $translations = [];
    private string $locale = 'zh_CN';
    private array $supported = [
        'zh_CN' => '简体中文', 'zh_TW' => '繁體中文', 'en_US' => 'English',
        'ja_JP' => '日本語', 'ko_KR' => '한국어',
    ];

    public static function instance(): I18nManager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->locale = get_locale() ?: 'zh_CN';
    }

}

// =================================================================
// v5.9.0.3: 多站点发布
// =================================================================
