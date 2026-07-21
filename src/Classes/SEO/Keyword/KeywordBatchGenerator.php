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

        // 加载模板配置 (v2.6.0: template_id 是 get_all() 的 1-based 索引)
        $tpl_config = [];
        if ($template_id > 0 && class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
            $all = $tpl_mgr->get_all();
            $idx = $template_id - 1;
            if (isset($all[$idx])) {
                $tpl_config = $all[$idx]['config'] ?? [];
            }
        }

        // 读高级设置
        $require_html = false;
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $adv = $enhancer->get_settings();
            $require_html = !empty($adv['require_html']);
        }

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) {
                continue;
            }

            try {
                if (!empty($custom_prompt)) {
                    $prompt = str_replace(['{keyword}', '{word_count}'], [$keyword, $tpl_config['word_count'] ?? 1200], $custom_prompt);
                    if (strpos($custom_prompt, '{keyword}') === false) {
                        $prompt = $custom_prompt . "\n\n关键词: " . $keyword;
                    }
                    $result = AIDispatcher::instance()->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'keyword_batch'],
                        ['fallback_providers' => []]
                    );
                } else {
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
                        $user = str_replace(['{keyword}', '{title}', '{word_count}'], [$keyword, $keyword, $tpl_config['word_count'] ?? 1200], $tpl_config['prompt']);
                    }
                    if ($additional) {
                        $user .= "\n\n额外要求:{$additional}";
                    }

                    try { // v19.3.0: AI 调用容错
                        $result = AIDispatcher::instance()->chat(
                            [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                            ['temperature' => $tpl_config['temperature'] ?? 0.7, 'max_tokens' => $tpl_config['max_tokens'] ?? 2000, 'module' => 'keyword_batch'],
                            ['fallback_providers' => []]
                        );
                    } catch (\Throwable $e) {
                        // FIX: 原 line 613 调用 $this->log(...) 把 Logger 对象当方法调用 (Fatal error)
                        $this->log->error('keyword', "关键词批量生成失败 [{$keyword}]: " . $e->getMessage());
                        continue;
                    }
                }

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
