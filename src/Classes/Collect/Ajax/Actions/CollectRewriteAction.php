<?php

declare(strict_types=1);
namespace Linked3\Classes\Collect\Ajax\Actions;
use Linked3\Classes\Collect\Ajax\CollectBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Collect rewrite action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Collect.Ajax.Actions
 * @since      27.1.0
 */

final class CollectRewriteAction extends CollectBaseAjaxAction
{
    public function handle()
    : void {
        $content = wp_unslash($_POST['content'] ?? '');
        if (empty($content)) $this->send_error('需要内容', 400);
        // v3.3.0: 改读 default_provider + saved_models
        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $default_model = $saved_models[$default_provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        $opts = [
            'tone' => sanitize_text_field($_POST['tone'] ?? 'professional'),
            'complexity' => sanitize_text_field($_POST['complexity'] ?? 'intermediate'),
            'seo_focus' => !empty($_POST['seo_focus']),
            'simplify' => !empty($_POST['simplify']),
            'provider' => sanitize_text_field($_POST['provider'] ?? $default_provider),
            'model' => sanitize_text_field($_POST['model'] ?? $default_model),
            'custom_prompt' => sanitize_textarea_field($_POST['custom_prompt'] ?? ''),
        ];

        // 如果有自定义提示词,直接用 (不经过 Article_Rewriter 的 build_prompt)
        if (!empty($opts['custom_prompt'])) {
            $prompt = str_replace('{content}', $content, $opts['custom_prompt']);
            if (strpos($prompt, '{content}') === false) {
                $prompt = $opts['custom_prompt'] . "\n\n" . $content;
            }
            try {
                $result = \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    [
                        'provider' => $opts['provider'],
                        'model' => $opts['model'],
                        'temperature' => 0.8,
                        'max_tokens' => 4000,
                        'module' => 'collect',
                    ],
                    ['fallback_providers' => ['deepseek', 'zhipu']]
                );
                $rewritten = $result['content'];
            } catch (\Throwable $e) {
                $this->send_error('改写失败: ' . $e->getMessage(), 502);
            }
        } else {
            $result = $this->rewriter()->rewrite($content, $opts);
            if (!$result['ok']) $this->send_error($result['message'], 502);
            $rewritten = $result['content'];
        }

        // 如果需要保存
        $post_status = sanitize_text_field($_POST['post_status'] ?? '');
        $saved_id = 0;
        if ($post_status === 'draft' || $post_status === 'publish') {
            $title = mb_substr(wp_strip_all_tags($content), 0, 50);
            $saved_id = wp_insert_post(wp_slash([
                'post_title' => $title,
                'post_content' => $rewritten,
                'post_status' => $post_status,
                'post_type' => 'post',
                'post_author' => get_current_user_id(),
            ]), true);
        }

        $this->send_success([
            'content' => $rewritten,
            'saved_id' => $saved_id,
            'saved_url' => $saved_id ? get_permalink($saved_id) : '',
            'message' => $saved_id ? sprintf('已保存为%s (ID: %d)', $post_status === 'publish' ? '已发布' : '草稿', $saved_id) : '',
        ]);
    }
}
