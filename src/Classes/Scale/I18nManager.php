<?php

declare(strict_types=1);
/**
 * I18nManager — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale

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

    public function setLocale(string $locale): void {
        if (isset($this->supported[$locale])) $this->locale = $locale;
    }

    public function translate(string $key, string $locale = ''): string {
        $locale = $locale ?: $this->locale;
        return $this->translations[$locale][$key] ?? $key;
    }

    public function loadTranslations(string $locale, array $map): void {
        $this->translations[$locale] = $map;
    }

    public function getSupportedLocales(): array { return $this->supported; }
    public function getCurrentLocale(): string { return $this->locale; }

    public function translateContent(string $content, string $targetLocale): string {
        if ($targetLocale === $this->locale) return $content;
        if (class_exists('\Linked3\Classes\Scale\AIDispatcher')) {
            $langMap = ['zh_CN' => '简体中文', 'zh_TW' => '繁體中文', 'en_US' => 'English', 'ja_JP' => '日本語', 'ko_KR' => '한국어'];
            $targetLang = $langMap[$targetLocale] ?? $targetLocale;
            $prompt = "请将以下内容翻译为{$targetLang}, 保持原文格式:\n\n" . $content;
            try { // v19.3.0: AI 调用容错
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['temperature' => 0.3, 'max_tokens' => 4000, 'module' => 'i18n'],
                ['fallback_providers' => []]
            );
            } catch (\Throwable $e) {
                return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
            }
            return $result['content'] ?? $content;
        }
        return $content;
    }
}

// =================================================================
// v5.9.0.3: 多站点发布
// =================================================================
