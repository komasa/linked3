<?php

declare(strict_types=1);
/**
 * Ecosystem Keyword Service — keyword generation and classification.
 *
 * Extracted from EcosystemAjax (God Class split pilot, 2026-07-20).
 * Original method signatures preserved as public static for backward compat.
 *
 * @package Linked3\Content
 * @since 10.7.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class EcosystemKeywordService
{
    /**
     * Generate long-tail keywords from a seed word.
     *
     * Delegates to KeywordManager if available, otherwise falls back to
     * local template-based generation.
     *
     * @param string $seed  Seed keyword
     * @param int    $count Maximum keywords to return
     * @return array
     */
    public static function generate_keywords(string $seed, int $count = 20): array
    {
        // Delegate to KeywordManager (if exists)
        if (class_exists('\Linked3\Classes\SEO\Keyword\KeywordManager')) {
            try {
                $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
                if (method_exists($mgr, 'generate_tail_keywords')) {
                    $result = $mgr->generate_tail_keywords($seed, $count);
                    if (is_array($result) && !empty($result)) return $result;
                }
            } catch (\Throwable $e) { if (function_exists("linked3_log")) linked3_log("app", "warning", $e->getMessage()); else error_log("Linked3: " . $e->getMessage()); }
        }

        // Fallback: local generation
        $keywords = [$seed];
        $templates = [
            '%s是什么', '%s怎么做', '%s教程', '%s攻略', '%s工具',
            '%s软件', '%s推荐', '%s对比', '最好的%s', '免费的%s',
            '%s2026', '%s最新', '%s入门', '%s进阶', '%s实战',
            '%s案例', '%s技巧', '%s方法', '%s指南', '%s大全',
        ];
        foreach ($templates as $tpl) {
            if (count($keywords) >= $count) break;
            $keywords[] = sprintf($tpl, $seed);
        }
        return array_slice(array_unique($keywords), 0, $count);
    }

    /**
     * Classify keywords into primary, long_tail, and question categories.
     *
     * @param array $keywords
     * @return array{primary: array, long_tail: array, question: array}
     */
    public static function classify_keywords(array $keywords): array
    {
        $classified = ['primary' => [], 'long_tail' => [], 'question' => []];
        foreach ($keywords as $kw) {
            if (mb_strpos($kw, '什么') !== false || mb_strpos($kw, '怎么') !== false) {
                $classified['question'][] = $kw;
            } elseif (mb_strlen($kw) > 8) {
                $classified['long_tail'][] = $kw;
            } else {
                $classified['primary'][] = $kw;
            }
        }
        return $classified;
    }
}
