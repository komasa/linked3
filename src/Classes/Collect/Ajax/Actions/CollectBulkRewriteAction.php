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
        // can exceed PHP's 30s default max_execution_time mid-stream, which
        // would silently truncate the SSE response. Bump to 10 min; ignore
        // failure (safe on hosts where set_time_limit is disabled).
        if (function_exists('set_time_limit') && !defined('WP_CLI')) {
            @set_time_limit(600); // phpcs:ignore
        }

        $scraper = $this->scraper();
        $rewriter = $this->rewriter();
        $results = [];
        // v3.3.0: 改读 default_provider + saved_models,不再硬编码 openai
        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $default_model = $saved_models[$default_provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        $opts = [
            'tone' => sanitize_text_field($_POST['tone'] ?? 'professional'),
            'complexity' => sanitize_text_field($_POST['complexity'] ?? 'intermediate'),
            'seo_focus' => !empty($_POST['seo_focus']),
            'provider' => sanitize_text_field($_POST['provider'] ?? $default_provider),
            'model' => sanitize_text_field($_POST['model'] ?? $default_model),
        ];

        // Set SSE headers for streaming progress (progressive output).
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
        }
        foreach ($urls as $i => $url) {
            $fetch = $scraper->fetch($url);
            if (!$fetch['ok']) {
                $this->sse_event('progress', ['index' => $i, 'url' => $url, 'ok' => false, 'message' => $fetch['message']]);
                $results[] = ['url' => $url, 'ok' => false, 'message' => $fetch['message']];
                continue;
            }
            if ($scraper->is_duplicate($url, $fetch['text'])) {
                $this->sse_event('progress', ['index' => $i, 'url' => $url, 'ok' => false, 'message' => __('重复,已跳过。', 'linked3')]);
                $results[] = ['url' => $url, 'ok' => false, 'message' => 'duplicate'];
                continue;
            }
            $rw = $rewriter->rewrite($fetch['text'], $opts);
            $saved_id = 0;
            // v3.8.0: 改写成功后自动保存为草稿
            if ($rw['ok'] && !empty($rw['content'])) {
                $post_title = $fetch['title'] ?: mb_substr(wp_strip_all_tags($fetch['text']), 0, 50);
                $saved_id = wp_insert_post(wp_slash([
                    'post_title'   => $post_title,
                    'post_content' => $rw['content'],
                    'post_status'  => 'draft',
                    'post_type'    => 'post',
                    'post_author'  => get_current_user_id(),
                ]), true);
                if (is_wp_error($saved_id)) $saved_id = 0;
            }
            $this->sse_event('progress', [
                'index' => $i,
                'url' => $url,
                'ok' => $rw['ok'],
                'title' => $fetch['title'],
                'content' => $rw['ok'] ? $rw['content'] : '',
                'saved_id' => $saved_id,
                'message' => $rw['ok'] ? ($saved_id ? sprintf('已保存为草稿 (ID: %d)', $saved_id) : '保存失败') : $rw['message'],
            ]);
            $results[] = ['url' => $url, 'ok' => $rw['ok'], 'title' => $fetch['title'], 'saved_id' => $saved_id, 'message' => $rw['ok'] ? ($saved_id ? '已保存' : '保存失败') : $rw['message']];
            @ob_flush(); @flush(); // phpcs:ignore
        }
        $this->sse_event('done', ['total' => count($urls), 'ok' => count(array_filter($results, function($r){return $r['ok'];}))]);
        exit;
    }

    private function sse_event($event, $data)
    : void {
        echo "event: {$event}\n";
        echo 'data: ' . wp_json_encode($data) . "\n\n";
    }
}
