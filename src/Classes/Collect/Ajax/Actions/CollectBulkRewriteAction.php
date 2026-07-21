<?php

declare(strict_types=1);
namespace Linked3\Classes\Collect\Ajax\Actions;
use Linked3\Classes\Collect\Ajax\CollectBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Collect bulk rewrite action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Collect.Ajax.Actions
 * @since      27.1.0
 */

final class CollectBulkRewriteAction extends CollectBaseAjaxAction
{
    public function handle() : void {
        $urls = array_filter(array_map('esc_url_raw', (array) ($_POST['urls'] ?? [])));
        if (empty($urls)) $this->send_error(__('URLs required.', 'linked3'), 400);
        if (count($urls) > 20) $this->send_error(__('批量运行最多 20 个 URL。', 'linked3'), 400);

        // v0.6.0 hardening: 20 URLs × (scrape 5s + AI rewrite 10s + 2s throttle)
        if (function_exists('set_time_limit') && !defined('WP_CLI')) {
            @set_time_limit(600); // phpcs:ignore
        }

        $scraper = $this->scraper();
        $rewriter = $this->rewriter();
        $opts = $this->buildRewriteOpts();
        $results = [];

        // Set SSE headers for streaming progress (progressive output).
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
        }
        foreach ($urls as $i => $url) {
            $result = $this->processSingleUrl($i, $url, $scraper, $rewriter, $opts);
            $results[] = $result;
            @ob_flush(); @flush(); // phpcs:ignore
        }
        $this->sse_event('done', ['total' => count($urls), 'ok' => count(array_filter($results, function($r){return $r['ok'];}))]);
        exit;
    }

    /**
     * 构建 rewrite 选项 (tone/complexity/seo/provider/model).
     */
    private function buildRewriteOpts(): array
    {
        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $default_model = $saved_models[$default_provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        return [
            'tone' => sanitize_text_field($_POST['tone'] ?? 'professional'),
            'complexity' => sanitize_text_field($_POST['complexity'] ?? 'intermediate'),
            'seo_focus' => !empty($_POST['seo_focus']),
            'provider' => sanitize_text_field($_POST['provider'] ?? $default_provider),
            'model' => sanitize_text_field($_POST['model'] ?? $default_model),
        ];
    }

    /**
     * 处理单个 URL: 抓取 → 去重 → 改写 → 保存草稿 → SSE 推送.
     */
    private function processSingleUrl(int $i, string $url, object $scraper, object $rewriter, array $opts): array
    {
        $fetch = $scraper->fetch($url);
        if (!$fetch['ok']) {
            $this->sse_event('progress', ['index' => $i, 'url' => $url, 'ok' => false, 'message' => $fetch['message']]);
            return ['url' => $url, 'ok' => false, 'message' => $fetch['message']];
        }
        if ($scraper->is_duplicate($url, $fetch['text'])) {
            $this->sse_event('progress', ['index' => $i, 'url' => $url, 'ok' => false, 'message' => __('重复,已跳过。', 'linked3')]);
            return ['url' => $url, 'ok' => false, 'message' => 'duplicate'];
        }

        $rw = $rewriter->rewrite($fetch['text'], $opts);
        $saved_id = $this->saveRewrittenDraft($rw, $fetch);

        $this->sse_event('progress', [
            'index' => $i,
            'url' => $url,
            'ok' => $rw['ok'],
            'title' => $fetch['title'],
            'content' => $rw['ok'] ? $rw['content'] : '',
            'saved_id' => $saved_id,
            'message' => $rw['ok'] ? ($saved_id ? sprintf('已保存为草稿 (ID: %d)', $saved_id) : '保存失败') : $rw['message'],
        ]);
        return ['url' => $url, 'ok' => $rw['ok'], 'title' => $fetch['title'], 'saved_id' => $saved_id, 'message' => $rw['ok'] ? ($saved_id ? '已保存' : '保存失败') : $rw['message']];
    }

    /**
     * 改写成功后自动保存为草稿.
     */
    private function saveRewrittenDraft(array $rw, array $fetch): int
    {
        if (!$rw['ok'] || empty($rw['content'])) {
            return 0;
        }
        $post_title = $fetch['title'] ?: mb_substr(wp_strip_all_tags($fetch['text']), 0, 50);
        $saved_id = wp_insert_post(wp_slash([
            'post_title'   => $post_title,
            'post_content' => $rw['content'],
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
        ]), true);
        return is_wp_error($saved_id) ? 0 : (int) $saved_id;
    }

    private function sse_event($event, $data)
    : void {
        echo "event: {$event}\n";
        echo 'data: ' . wp_json_encode($data) . "\n\n";
    }
}
