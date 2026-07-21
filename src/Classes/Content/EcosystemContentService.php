<?php

declare(strict_types=1);
/**
 * Ecosystem Content Service — template loading, content generation, and self-check.
 *
 * Extracted from EcosystemAjax (God Class split pilot, 2026-07-20).
 * Original method signatures preserved as public static for backward compat.
 *
 * @package Linked3\Content
 * @since 10.7.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class EcosystemContentService
{
    /**
     * Load a content template by category.
     *
     * Tries (in order):
     * 1. Cross-ecosystem shared pool (wp_options)
     * 2. TemplateManager
     * 3. CloudTemplateFactory
     * 4. Fallback default
     *
     * @param string $category
     * @return array
     */
    public static function load_template(string $category): array
    {
        // v10.7.0: load from cross-ecosystem shared pool first
        $shared_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
        foreach ($shared_templates as $tpl) {
            if (($tpl['type'] ?? '') === $category) return $tpl;
        }

        // Delegate to TemplateManager (if exists)
        if (class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            try {
                $mgr = new \Linked3\Classes\Templates\TemplateManager();
                $templates = $mgr->get_by_category($category);
                if (!empty($templates)) return $templates[0];
            } catch (\Throwable $e) {}
        }

        // v10.7.0: delegate to CloudTemplateFactory (if exists)
        if (class_exists('\Linked3\Classes\Content\CloudTemplateFactory')) {
            try {
                $factory = new \CloudTemplateFactory();
                if (method_exists($factory, 'load_template_by_category')) {
                    $tpl = $factory->load_template_by_category($category);
                    if (!empty($tpl)) return $tpl;
                }
            } catch (\Throwable $e) {}
        }

        return ['name' => $category . '_default', 'type' => $category];
    }

    /**
     * Generate article content from topic, keywords, and template.
     *
     * Delegates to LongFormWriter if available, otherwise uses AI dispatch
     * with template-enhanced prompt.
     *
     * @param string $topic
     * @param array  $keywords
     * @param array  $template
     * @param string $tone
     * @param int    $word_count
     * @return string Generated content (empty = AI failure, caller should error)
     */
    public static function generate_content(
        string $topic,
        array $keywords,
        array $template = [],
        string $tone = 'professional',
        int $word_count = 800
    ): string {
        // Delegate to LongFormWriter (if exists)
        if (class_exists('\Linked3\Classes\Content\LongFormWriter')) {
            try {
                $writer = new \LongFormWriter();
                if (method_exists($writer, 'generate')) {
                    $result = $writer->generate($topic, implode(',', $keywords), ['word_count' => $word_count, 'tone' => $tone]);
                    if (is_string($result) && !empty($result)) return $result;
                }
            } catch (\Throwable $e) {}
        }

        $prompt = self::buildTemplateEnhancedPrompt($topic, $keywords, $template, $tone, $word_count);
        $prompt = self::applyGlobalFormatSettings($prompt);
        $ai_content = EcosystemImageService::call_ai($prompt, max(1000, intval($word_count * 1.5)));

        if (empty($ai_content)) {
            return '';
        }
        return self::postProcessContent($ai_content);
    }

    /**
     * 构建模板增强的 AI prompt.
     */
    private static function buildTemplateEnhancedPrompt(string $topic, array $keywords, array $template, string $tone, int $word_count): string
    {
        $cfg = $template['config'] ?? $template;
        $prompt_parts = [];
        if (!empty($cfg['role'])) $prompt_parts[] = '你的角色: ' . $cfg['role'];
        if (!empty($cfg['scene'])) $prompt_parts[] = '适用场景: ' . $cfg['scene'];
        if (!empty($cfg['background'])) $prompt_parts[] = '背景: ' . $cfg['background'];
        $goals = $cfg['goals'] ?? [];
        if (is_array($goals) && !empty($goals)) $prompt_parts[] = '目标: ' . implode('、', $goals);
        $skills = $cfg['skills'] ?? [];
        if (is_array($skills) && !empty($skills)) $prompt_parts[] = '技能要求: ' . implode('、', $skills);
        if (!empty($cfg['style'])) $prompt_parts[] = '风格: ' . $cfg['style'];
        $limits = $cfg['limit'] ?? [];
        if (is_array($limits) && !empty($limits)) $prompt_parts[] = '限制: ' . implode('、', $limits);
        $steps = $cfg['step'] ?? [];
        if (is_array($steps) && !empty($steps)) $prompt_parts[] = '写作步骤: ' . implode(' → ', $steps);
        if (!empty($cfg['output'])) $prompt_parts[] = '输出格式: ' . $cfg['output'];

        $kw_str = implode('、', array_slice($keywords, 0, 8));
        $prompt = "请为主题「{$topic}」撰写一篇文章。\n\n";
        if (!empty($prompt_parts)) {
            $prompt .= "模版要求:\n" . implode("\n", $prompt_parts) . "\n\n";
        }
        $prompt .= "关键词: {$kw_str}\n";
        $prompt .= "字数: 约{$word_count}字\n";
        $prompt .= "语气: {$tone}\n\n";
        $prompt .= "严格要求:\n1. 内容具体、有信息量, 不要空话套话\n2. 不要使用「赋能/闭环/抓手/底层逻辑/范式/矩阵」等AI高频词\n3. 适合博客/公众号发布\n4. 直接输出正文, 不要说明\n";
        return $prompt;
    }

    /**
     * 应用全局格式设置 (HTML / AI 摘要 / 标签).
     */
    private static function applyGlobalFormatSettings(string $prompt): string
    {
        $adv_settings = wp_parse_args(
            (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []),
            ['require_html' => false, 'require_tag' => false, 'enable_ai_summary' => false]
        );
        if (class_exists('\Linked3\Classes\Content\AIEnhancer')) {
            try {
                $enhancer = new \AIEnhancer();
                $prompt = $enhancer->apply_format_requirements($prompt, $adv_settings);
            } catch (\Throwable $e) {}
        } elseif (!empty($adv_settings['require_html'])) {
            $prompt .= "\n返回的文章内容必须用 HTML 标签格式,不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。文章标题用 H1 标签。";
        }
        return $prompt;
    }

    /**
     * 后处理 AI 内容: Markdown→HTML + AI 标识符 + self-check.
     */
    private static function postProcessContent(string $ai_content): string
    {
        $adv_settings = wp_parse_args(
            (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []),
            ['require_html' => false]
        );
        // v11.8.0: convert Markdown to HTML if required
        if (!empty($adv_settings['require_html'])
            && class_exists('\Linked3\Classes\Content\MarkdownHtmlConverter')
            && strpos($ai_content, '<') === false) {
            try {
                $ai_content = \MarkdownHtmlConverter::convert($ai_content);
            } catch (\Throwable $e) {}
        }
        // v11.8.0: append AI identifier suffix
        if (class_exists('\Linked3\Classes\Content\AIEnhancer')) {
            try {
                $ai_content = (new \AIEnhancer())->append_identifier_suffix($ai_content);
            } catch (\Throwable $e) {}
        }
        return self::self_check_content($ai_content);
    }

    /**
     * v10.7.5: feicai4.0 21条AI痕迹识别 — de-AI self-check.
     *
     * @param string $content
     * @return string Cleaned content
     */
    public static function self_check_content(string $content): string
    {
        // 1. Remove overclaim words
        $overclaims = ['最好', '第一', '唯一', '100%', '绝对', '完美', '无敌', '顶级', '极致'];
        foreach ($overclaims as $word) {
            $content = str_replace($word, '优秀', $content);
        }

        // 2. Remove inflated phrases
        $inflations = [
            '标志着…新时代' => '',
            '从更宏观层面看' => '',
            '具有重大意义' => '很重要',
            '产生深远影响' => '影响很大',
        ];
        foreach ($inflations as $from => $to) {
            $content = str_replace($from, $to, $content);
        }

        // 3. Remove pseudo-depth verbs
        $pseudoVerbs = [
            '提升…能力' => '改善',
            '促进…发展' => '推动',
            '推动…进程' => '推进',
            '赋能…' => '支持',
        ];
        foreach ($pseudoVerbs as $from => $to) {
            $content = preg_replace('/' . preg_quote($from, '/') . '/u', $to, $content);
        }

        // 4. Remove advertising tone
        $ads = ['卓越', '一站式', '全方位', '极致体验'];
        foreach ($ads as $word) {
            $content = str_replace($word, '全面', $content);
        }

        // 5. Remove AI frequency words
        $aiWords = ['赋能', '闭环', '抓手', '底层逻辑', '范式', '矩阵'];
        foreach ($aiWords as $word) {
            $content = str_replace($word, '', $content);
        }

        // 6. Remove empty endings
        $emptyEndings = ['未来可期', '值得期待', '前景广阔'];
        foreach ($emptyEndings as $word) {
            $content = str_replace($word, '建议立即行动', $content);
        }

        return $content;
    }
}
