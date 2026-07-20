<?php

declare(strict_types=1);
/**
 * Ecosystem Image Service — image prompt generation, quality check, AI dispatch.
 *
 * Extracted from EcosystemAjax (God Class split pilot, 2026-07-20).
 * Original method signatures preserved as public static for backward compat.
 *
 * @package Linked3\Content
 * @since 10.7.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class EcosystemImageService
{
    /**
     * v10.7.5: feicai4.0 narrative image prompts — full paragraph descriptions.
     *
     * @param string $content
     * @param array  $keywords
     * @return array
     */
    public static function generate_images(string $content, array $keywords): array
    {
        $images = [];
        $kw_str = implode('、', array_slice($keywords, 0, 3));
        $primary_kw = $keywords[0] ?? '内容';

        $types = ['featured', 'content_1', 'content_2'];
        $prompts = [
            'featured' => sprintf(
                '中景，专业信息图设计。画面中央展示「%s」的核心概念，用简洁的图标和流程图呈现%s的关键要素。背景使用浅蓝色渐变，配以白色文字标签。整体风格专业、清晰，适合技术博客封面。画面中不包含任何水印。',
                $primary_kw, $kw_str
            ),
            'content_1' => sprintf(
                '中景，信息图。以「%s」为主题，用三栏对比布局展示核心要点。左侧是问题场景，中间是解决方案，右侧是预期效果。配色采用蓝白灰三色系，文字标签清晰可读。画面中不包含任何水印。',
                $primary_kw
            ),
            'content_2' => sprintf(
                '中景，流程信息图。以「%s」为核心，用箭头流程图展示从输入到输出的完整路径。每个步骤配有简洁图标和关键词标签：%s。背景为浅灰色，强调内容层次感。画面中不包含任何水印。',
                $primary_kw, $kw_str
            ),
        ];

        foreach ($types as $type) {
            $images[] = [
                'type' => $type,
                'prompt' => $prompts[$type],
                'resolution' => '1280*1280',
                'layout' => 'list',
            ];
        }
        return $images;
    }

    /**
     * v10.7.5: feicai4.0 accuracy checklist — quality check.
     *
     * @param array  $keywords
     * @param array  $template
     * @param string $content
     * @param array  $images
     * @return array{score: int, checks: array, passed: bool}
     */
    public static function quality_check(array $keywords, array $template, string $content, array $images): array
    {
        $score = 0;
        $checks = [];

        // Basic checks (4 items, 20 points each)
        $checks['keywords'] = !empty($keywords);
        if ($checks['keywords']) $score += 20;

        $checks['template'] = !empty($template);
        if ($checks['template']) $score += 20;

        $checks['content'] = !empty($content) && mb_strlen($content) > 100;
        if ($checks['content']) $score += 20;

        $checks['images'] = !empty($images);
        if ($checks['images']) $score += 20;

        // v10.7.5: deep quality checks (2 items, 10 points each)
        $checks['logical_structure'] = preg_match('/^##\s/m', $content);
        if ($checks['logical_structure']) $score += 10;

        $aiResidue = ['赋能', '闭环', '抓手', '底层逻辑', '范式', '矩阵', '未来可期'];
        $hasAiResidue = false;
        foreach ($aiResidue as $word) {
            if (mb_strpos($content, $word) !== false) {
                $hasAiResidue = true;
                break;
            }
        }
        $checks['no_ai_traces'] = !$hasAiResidue;
        if ($checks['no_ai_traces']) $score += 10;

        return [
            'score' => $score,
            'checks' => $checks,
            'passed' => $score >= 60,
        ];
    }

    /**
     * v10.9.0 AI dispatch helper — calls AIDispatcher, returns empty on failure.
     *
     * @param string $prompt
     * @param int    $max_tokens
     * @return string AI-generated content (empty = failure)
     */
    public static function call_ai(string $prompt, int $max_tokens = 2000): string
    {
        return self::call_ai_internal($prompt, $max_tokens);
    }

    /**
     * Public AI dispatch entry point — callable from external code.
     *
     * Kept public for backward compat (was public on EcosystemAjax).
     *
     * @param string $prompt
     * @param int    $max_tokens
     * @return string
     */
    public static function call_ai_internal(string $prompt, int $max_tokens = 2000): string
    {
        if (!class_exists('\\Linked3\\Classes\\Core\\AIDispatcher')) {
            return '';
        }
        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $messages = [['role' => 'user', 'content' => $prompt]];
            $options = ['max_tokens' => $max_tokens, 'temperature' => 0.7];
            $config = [];
            $result = $dispatcher->chat($messages, $options, $config);
            return $result['content'] ?? '';
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[linked3 v10.9.0] AI call failed: ' . $e->getMessage());
            }
            return '';
        }
    }
}
