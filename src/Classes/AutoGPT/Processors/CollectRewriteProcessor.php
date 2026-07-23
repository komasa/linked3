<?php

declare(strict_types=1);
/**
 * v3.2.0: 采集改写 Processor — AutoGPT 工作流编排
 *
 * 工作流: URL 列表 → 采集 → AI 改写 → 保存草稿 → (可选)分发
 *
 * 任务 config 字段:
 *   - urls: URL 列表 (每行一个)
 *   - tone: 改写语气 (professional/casual/academic/persuasive)
 *   - complexity: 复杂度 (beginner/intermediate/expert)
 *   - seo_focus: bool SEO 优化
 *   - simplify: bool 简化语言
 *   - publish_directly: bool 直接发布 (默认草稿)
 *   - distribute_platforms: array 分发平台子集
 *   - custom_prompt: 自定义改写提示词 (留空用默认)
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT\Processors
 */

namespace Linked3\Classes\AutoGPT\Processors;

use Linked3\Classes\Collect\Rewriter\ArticleRewriter;
use Linked3\Classes\Collect\Scraper;
use Linked3\Classes\Publish\PublishManager;
use Linked3\Classes\Distribute\DistributeManager;



if (!defined('ABSPATH')) {
    exit;
}
final class CollectRewriteProcessor implements AutoGPTProcessorInterface
{
    public function process(array $task): array {
        $cfg = $task['config'];
        $urls_raw = $cfg['urls'] ?? '';
        $urls = array_filter(array_map('trim', explode("\n", $urls_raw)));
        if (empty($urls)) {
            return ['ok' => false, 'message' => __('未配置 URL 列表。', 'linked3'), 'items_processed' => 0];
        }

        $count = min(count($urls), 5); // 每次最多处理 5 个 URL
        $processed = 0;
        $errors = [];
        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();

        // 读 default provider
        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        for ($i = 0; $i < $count; $i++) {
            $url = $urls[$i] ?? '';
            if (empty($url)) continue;

            try {
                // 1. 采集
                $scraper = new \Linked3\Classes\Collect\Scraper();
                $scraped = $scraper->fetch($url);
                if (is_wp_error($scraped) || empty($scraped['content'])) {
                    $errors[] = "URL {$url} 采集失败: " . (is_wp_error($scraped) ? $scraped->get_error_message() : '无内容');
                    // 采集失败入队重试
                    $repo->enqueue($task['id'], [
                        'type' => 'scrape_retry',
                        'url' => $url,
                        'reason' => '采集失败',
                    ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                    continue;
                }

                // 2. 改写
                $rewriter = new \Linked3\Classes\Collect\Rewriter\ArticleRewriter();
                $rw = $rewriter->rewrite($scraped['content'], [
                    'tone' => $cfg['tone'] ?? 'professional',
                    'complexity' => $cfg['complexity'] ?? 'intermediate',
                    'seo_focus' => !empty($cfg['seo_focus']),
                    'simplify' => !empty($cfg['simplify']),
                    'provider' => $provider,
                    'model' => $model,
                    'user_id' => $task['user_id'],
                ]);

                if (empty($rw['ok']) || empty($rw['content'])) {
                    $errors[] = "URL {$url} 改写失败: " . ($rw['message'] ?? 'unknown');
                    $repo->enqueue($task['id'], [
                        'type' => 'rewrite_retry',
                        'url' => $url,
                        'reason' => $rw['message'] ?? '改写失败',
                    ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                    continue;
                }

                // 3. 保存文章
                $post_title = $scraped['title'] ?? '采集改写 ' . date('Y-m-d H:i');
                $post = [
                    'post_title'   => $post_title,
                    'post_content' => $rw['content'],
                    'post_status'  => !empty($cfg['publish_directly']) ? 'publish' : 'draft',
                    'post_type'    => 'post',
                    'post_author'  => $task['user_id'],
                    'post_excerpt' => mb_substr(wp_strip_all_tags($rw['content']), 0, 200),
                ];
                $post_id = wp_insert_post(wp_slash($post), true);
                if (is_wp_error($post_id)) {
                    $errors[] = "URL {$url} 保存失败: " . $post_id->get_error_message();
                    continue;
                }

                // 4. (可选)分发
                if (!empty($cfg['distribute_platforms']) && is_array($cfg['distribute_platforms'])) {
                    $dist_mgr = DistributeManager::instance();
                    $results = $dist_mgr->distribute_post_to_platforms($post_id, $cfg['distribute_platforms']);
                    foreach ($results as $r) {
                        if (!$r['ok']) {
                            $dist_mgr->enqueue_retry($post_id, $r['platform'], $r['message']);
                        }
                    }
                }

                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "URL {$url} 异常: " . $e->getMessage();
                $repo->enqueue($task['id'], [
                    'type' => 'scrape_retry',
                    'url' => $url,
                    'reason' => $e->getMessage(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
            }
        }

        $ok = ($processed > 0);
        $message = $processed > 0
            ? sprintf(__('已采集改写 %d 篇文章。', 'linked3'), $processed)
            : sprintf(__('%d 个 URL 处理失败。已入队重试。', 'linked3'), $count);
        if (!empty($errors)) {
            $message .= ' 错误: ' . implode('; ', array_slice($errors, 0, 3));
        }
        return ['ok' => $ok, 'message' => $message, 'items_processed' => $processed];
    }
}
