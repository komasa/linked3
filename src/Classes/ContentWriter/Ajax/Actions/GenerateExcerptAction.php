<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Ajax\Actions;
use Linked3\Classes\ContentWriter\Ajax\ContentWriterBaseAjaxAction;
use Linked3\Classes\ContentWriter\Prompt\ExcerptPromptBuilder;


if (!defined('ABSPATH')) exit;
/**
 * Generate excerpt action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Ajax.Actions
 * @since      27.1.0
 */

final class GenerateExcerptAction extends ContentWriterBaseAjaxAction
{
    public function handle(): void {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if (!$title) $this->send_error(__('需要标题。', 'linked3'), 400);
        $prompt = (new \Linked3\Classes\ContentWriter\Prompt\ExcerptPromptBuilder())->build(['title' => $title, 'keyword' => $keyword]);
        try { // v19.3.0: AI 调用容错
            $result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'Qwen/Qwen2.5-7B-Instruct', 'temperature' => 0.6, 'max_tokens' => 100, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
        } catch (\Throwable $e) {
            $this->send_error('AI 调用失败: ' . $e->getMessage(), 502);
        }
        \Linked3\Classes\Core\TokenManager::instance()->record(get_current_user_id(), '', $result['usage']['total_tokens']);
        $this->send_success(['excerpt' => trim($result['content'] ?? '')]);
    }
}
