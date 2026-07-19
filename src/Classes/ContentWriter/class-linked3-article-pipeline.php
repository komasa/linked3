<?php
namespace Linked3\Classes\ContentWriter;
use Linked3\Classes\Content\Pipeline\Linked3_Content_Pipeline_Interface;
use Linked3\Classes\Core\Linked3_AI_Dispatcher;
if (!defined('ABSPATH')) exit;

final class Linked3_Article_Pipeline implements Linked3_Content_Pipeline_Interface
{
    public static function type(): string { return 'article'; }
    public static function label(): string { return __('文章写作', 'linked3'); }

    public function prepare(array $input): array
    {
        $keyword = sanitize_text_field($input['topic'] ?? $input['script'] ?? '');
        if (empty($keyword)) throw new \InvalidArgumentException(__('请输入关键词或主题', 'linked3'));
        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        return [
            'keyword' => $keyword,
            'provider' => $provider,
            'model' => $saved_models[$provider] ?? 'Qwen/Qwen2.5-32B-Instruct',
            'style' => $input['style'] ?? 'professional',
            'word_count' => (int) ($input['options']['word_count'] ?? 1200),
        ];
    }

    public function generate(array $context, ?callable $progressCb = null): array
    {
        if ($progressCb) $progressCb(10, 'generating', __('正在生成文章...', 'linked3'));
        $prompt = sprintf("请围绕「%s」写一篇约%d字的WordPress博客文章。风格：%s。使用Markdown格式，包含标题、正文、小标题。", $context['keyword'], $context['word_count'], $context['style']);
        try {
            $result = Linked3_AI_Dispatcher::instance()->chat([['role' => 'user', 'content' => $prompt]], ['provider' => $context['provider'], 'model' => $context['model'], 'temperature' => 0.7, 'max_tokens' => $context['word_count'] * 2, 'module' => 'content_writer']);
        } catch (\Throwable $e) { throw new \RuntimeException(__('AI 调用失败: ', 'linked3') . $e->getMessage()); }
        $content = $result['content'] ?? '';
        if (empty($content)) throw new \RuntimeException(__('AI 返回空内容', 'linked3'));
        if ($progressCb) $progressCb(80, 'formatting', __('正在格式化...', 'linked3'));
        return ['title' => $this->extract_title($content), 'content' => $content, 'keyword' => $context['keyword']];
    }

    public function deliver(array $result): array
    {
        $post_id = wp_insert_post(['post_title' => $result['title'], 'post_content' => $result['content'], 'post_status' => 'draft', 'post_type' => 'post'], true);
        if (is_wp_error($post_id)) return ['success' => false, 'message' => $post_id->get_error_message()];
        return ['success' => true, 'post_id' => $post_id, 'title' => $result['title'], 'edit_url' => get_edit_post_link($post_id, 'raw')];
    }

    public static function get_styles(): array
    {
        return [['id' => 'professional', 'name' => __('专业', 'linked3')], ['id' => 'casual', 'name' => __('轻松', 'linked3')], ['id' => 'academic', 'name' => __('学术', 'linked3')], ['id' => 'marketing', 'name' => __('营销', 'linked3')]];
    }

    private function extract_title(string $content): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $m)) return trim($m[1]);
        $lines = explode("\n", trim($content));
        return mb_substr(trim($lines[0] ?? 'Untitled'), 0, 100);
    }
}
