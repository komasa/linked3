<?php

declare(strict_types=1);
/**
 * 批量文章生成器 — 从关键词列表批量 AI 生成 + 发布文章。
 *
 * 从原 KeywordManager::batch_generate_from_keywords + append_ai_suffix + append_ai_summary 提取。
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Classes\Core\AIDispatcher;
use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class KeywordBatchGenerator
{
    /** @var Logger */
    private $log;

    public function __construct(Logger $log)
    {
        $this->log = $log;
    }

    /** 批量生成文章。 */
    public function generate(array $keywords, array $opts = []): array
    {
        $post_status   = $opts['post_status'] ?? 'draft';
        $additional    = $opts['additional_requirements'] ?? '';
        $template_id   = (int)($opts['template_id'] ?? 0);
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $generated     = 0;
        $errors         = [];

        $tpl_config   = $this->load_template_config($template_id);
        $require_html = $this->load_require_html_setting();

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            try {
                $result = $this->generate_for_keyword(
                    $keyword,
                    $tpl_config,
                    $custom_prompt,
                    $additional,
                    $require_html
                );

                $content = $result['content'] ?? '';
                $content = $this->append_ai_suffix($content);

                if (!empty($opts['enable_ai_summary'])) {
                    $content = $this->append_ai_summary($content);
                }

                wp_insert_post(wp_slash([
                    'post_title'   => $keyword,
                    'post_content' => $content,
                    'post_status'  => $post_status,
                    'post_type'    => 'post',
                    'post_author'  => get_current_user_id(),
                ]), true);
                $generated++;
            } catch (\Throwable $e) {
                $errors[] = $keyword . ': ' . $e->getMessage();
            }
        }

        return ['generated' => $generated, 'errors' => $errors];
    }

    /**
     * Load template configuration by 1-based index.
     *
     * @param int $template_id
     * @return array
     */
    private function load_template_config(int $template_id): array
    {
        if ($template_id <= 0 || !class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            return [];
        }
        $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
        $all     = $tpl_mgr->get_all();
        $idx     = $template_id - 1;
        return $all[$idx]['config'] ?? [];
    }

    /**
     * Check whether the AIEnhancer require_html setting is active.
     *
     * @return bool
     */
    private function load_require_html_setting(): bool
    {
        if (!class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            return false;
        }
        $enhancer = new \Linked3\Classes\Core\AIEnhancer();
        $adv      = $enhancer->get_settings();
        return !empty($adv['require_html']);
    }

    /**
     * Generate AI content for a single keyword.
     *
     * @param string $keyword
     * @param array  $tpl_config
     * @param string $custom_prompt
     * @param string $additional
     * @param bool   $require_html
     * @return array
     */
    private function generate_for_keyword(
        string $keyword,
        array $tpl_config,
        string $custom_prompt,
        string $additional,
        bool $require_html
    ): array {
        if (!empty($custom_prompt)) {
            return $this->generate_with_custom_prompt($keyword, $custom_prompt, $tpl_config);
        }
        return $this->generate_with_template($keyword, $tpl_config, $additional, $require_html);
    }

    /**
     * Generate using a custom prompt template.
     *
     * @param string $keyword
     * @param string $custom_prompt
     * @param array  $tpl_config
     * @return array
     */
    private function generate_with_custom_prompt(string $keyword, string $custom_prompt, array $tpl_config): array
    {
        $prompt = str_replace(
            ['{keyword}', '{word_count}'],
            [$keyword, $tpl_config['word_count'] ?? 1200],
            $custom_prompt
        );
        if (strpos($custom_prompt, '{keyword}') === false) {
            $prompt = $custom_prompt . "\n\n关键词: " . $keyword;
        }
        return AIDispatcher::instance()->chat(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'keyword_batch'],
            ['fallback_providers' => []]
        );
    }

    /**
     * Generate using system + user prompt from template config.
     *
     * @param string $keyword
     * @param array  $tpl_config
     * @param string $additional
     * @param bool   $require_html
     * @return array
     */
    private function generate_with_template(
        string $keyword,
        array $tpl_config,
        string $additional,
        bool $require_html
    ): array {
        $sys = (new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder())->build([
            'tone'         => $tpl_config['tone'] ?? 'professional',
            'language'     => 'zh-CN',
            'complexity'   => $tpl_config['complexity'] ?? 'intermediate',
            'seo_focus'    => true,
            'require_html' => $require_html,
        ]);
        $user = (new \Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder())->build([
            'keyword'    => $keyword,
            'word_count' => $tpl_config['word_count'] ?? 1200,
        ]);
        if (!empty($tpl_config['prompt'])) {
            $user = str_replace(
                ['{keyword}', '{title}', '{word_count}'],
                [$keyword, $keyword, $tpl_config['word_count'] ?? 1200],
                $tpl_config['prompt']
            );
        }
        if ($additional) {
            $user .= "\n\n额外要求:{$additional}";
        }

        try {
            return AIDispatcher::instance()->chat(
                [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                [
                    'temperature' => $tpl_config['temperature'] ?? 0.7,
                    'max_tokens'  => $tpl_config['max_tokens'] ?? 2000,
                    'module'      => 'keyword_batch',
                ],
                ['fallback_providers' => []]
            );
        } catch (\Throwable $e) {
            $this->log->error('keyword', "关键词批量生成失败 [{$keyword}]: " . $e->getMessage());
            return ['content' => ''];
        }
    }

    /** AI 附加备注 (原版 enable_random_identifier + AI 标识符后缀)。 */
    private function append_ai_suffix(string $content): string
    {
        $enabled = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_enabled', 0);
        if (!$enabled) {
            return $content;
        }
        $suffix = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_text', '');
        if (empty($suffix)) {
            $suffix = '本文基于公开技术资料和厂商官方信息整合撰写,以确保信息的时效性与客观性。我们建议您将所有信息作为决策参考,并最终以各云厂商官方页面的最新公告为准。';
        }
        return $content . "\n\n---\n" . $suffix;
    }

    /** AI 摘要 (原版 enable_ai_summary)。 */
    private function append_ai_summary(string $content): string
    {
        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => '为以下文章生成一段100字以内的摘要:\n\n' . mb_substr($content, 0, 2000)]],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'gpt-4o-mini', 'temperature' => 0.3, 'max_tokens' => 200, 'module' => 'keyword_batch'],
                ['fallback_providers' => []]
            );
            return $content . "\n\n**摘要:** " . trim($result['content'] ?? '');
        } catch (\Throwable $e) {
            return $content;
        }
    }
}
