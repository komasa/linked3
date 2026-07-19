<?php
namespace Linked3\Classes\ContentWriter\Ajax\Actions;
use Linked3\Classes\ContentWriter\Ajax\Linked3_Content_Writer_Base_Ajax_Action;
use Linked3\Classes\ContentWriter\Prompt\Linked3_Tags_Prompt_Builder;


if (!defined('ABSPATH')) exit;
/**
 * Generate tags action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Ajax.Actions
 * @since      27.1.0
 */

final class Linked3_Generate_Tags_Action extends Linked3_Content_Writer_Base_Ajax_Action
{
    public function handle()
    : void {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if (!$title) $this->send_error(__('需要标题。', 'linked3'), 400);
        $prompt = (new \Linked3\Classes\ContentWriter\Prompt\Linked3_Tags_Prompt_Builder())->build(['title' => $title, 'keyword' => $keyword]);
        try { // v19.3.0: AI 调用容错
            $result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'Qwen/Qwen2.5-7B-Instruct', 'temperature' => 0.6, 'max_tokens' => 100, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
        } catch (\Throwable $e) {
            $this->send_error('AI 调用失败: ' . $e->getMessage(), 502);
        }
        \Linked3\Classes\Core\Linked3_Token_Manager::instance()->record(get_current_user_id(), '', $result['usage']['total_tokens']);
        $tags = array_filter(array_map('trim', explode(',', $result['content'] ?? '')));
        $this->send_success(['tags' => array_slice(array_values($tags), 0, 10)]);
    }
}
