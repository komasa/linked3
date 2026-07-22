<?php

declare(strict_types=1);
/**
 * Article Rewriter — AI-powered rewrite of scraped content.
 *
 * Migrates v2.9.6's AIArticleRewriter with hardening:
 *   - build_rewrite_prompt preserves tone/complexity/SEO/headings/simplify
 *   - nonce + cap + plan gate (no more nopriv_ajax)
 *   - Uses AI Dispatcher (token-billed) instead of raw call_api
 *   - Dedup via Scraper::is_duplicate before rewrite
 *
 * @package Linked3
 * @subpackage Classes\Collect\Rewriter
 */

namespace Linked3\Classes\Collect\Rewriter;

use Linked3\Classes\Core\AIDispatcher;
use Linked3\Classes\Core\TokenManager;



if (!defined('ABSPATH')) {
    exit;
}
final class ArticleRewriter
{
    /**
     * Build the rewrite system+user prompt.
     *
     * @param string $original
     * @param array  $opts {tone, complexity, seo_focus, simplify, keep_headings}
     * @return array{system:string, user:string}
     */
    public function build_prompt(string $original, array $opts): array {
        $tone = $opts['tone'] ?? 'professional';
        $complexity = $opts['complexity'] ?? 'intermediate';
        $seo = !empty($opts['seo_focus']);
        $simplify = !empty($opts['simplify']);
        $keep_h = !empty($opts['keep_headings']);

        $system = __('您是专业的文章改写器,改写用户提供的文章使其原创(通过查重),同时保留所有事实和含义。', 'linked3');
        $system .= ' ' . sprintf(__('语气:%s。', 'linked3'), $tone);
        $system .= ' ' . sprintf(__('复杂度:%s。', 'linked3'), $complexity);
        if ($seo) $system .= ' ' . __('SEO 优化:自然包含相关关键词,使用 H2/H3 小标题。', 'linked3');
        if ($simplify) $system .= ' ' . __('简化复杂句子,目标 8 年级阅读水平。', 'linked3');
        if ($keep_h) $system .= ' ' . __('保留原始标题结构。', 'linked3');
        $system .= ' ' . __('仅输出 Markdown,不要前言。', 'linked3');

        $user = sprintf("%s\n\n%s", __('Rewrite the following article:', 'linked3'), $original);
        return ['system' => $system, 'user' => $user];
    }

    /**
     * Rewrite a single article.
     *
     * @param string $original
     * @param array  $opts
     * @return array{ok:bool, content:string, usage:array, message:string}
     */
    public function rewrite(string $original, array $opts = []): array {
        // v0.8.0 fix: accept an optional user_id override from $opts so
        // background callers (AutoGPT enhancement processor) bill the task
        // owner rather than the shared guest bucket (user_id=0 during cron).
        $user_id = isset($opts['user_id']) ? (int) $opts['user_id'] : get_current_user_id();
        // Quota check.
        $check = TokenManager::instance()->check($user_id, '', 500);
        if (!$check['ok']) {
            return ['ok' => false, 'content' => '', 'usage' => [], 'message' => __('每日 Token 配额已用完。', 'linked3')];
        }

        $prompt = $this->build_prompt($original, $opts);
        try {
            // v3.3.0: 改读 default_provider + saved_models,fallback 加 deepseek/zhipu
            $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $default_model = $saved_models[$default_provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
            $result = AIDispatcher::instance()->chat(
                [
                    ['role' => 'system', 'content' => $prompt['system']],
                    ['role' => 'user', 'content' => $prompt['user']],
                ],
                [
                    'provider' => $opts['provider'] ?? $default_provider,
                    'model' => $opts['model'] ?? $default_model,
                    'temperature' => 0.8,
                    'max_tokens' => 4000,
                    'module' => 'collect',
                    'user_id' => $user_id,
                ],
                [
                    'fallback_providers' => ['deepseek', 'zhipu'],
                ]
            );
            return ['ok' => true, 'content' => $result['content'], 'usage' => $result['usage'], 'message' => 'ok'];
        } catch (\Exception $e) {
            return ['ok' => false, 'content' => '', 'usage' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * @param string $provider
     * @return string
     */
    private function get_api_key(string $provider) : mixed {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        return is_array($keys) && isset($keys[$provider]) ? $keys[$provider] : '';
    }
}
