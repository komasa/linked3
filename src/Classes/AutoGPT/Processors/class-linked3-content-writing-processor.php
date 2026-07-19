<?php
/**
 * Content Writing Processor — v3.0.0
 *
 * 修复点:
 *   1. 失败时入队 (enqueue) 而非静默丢弃 — 让 AutoGPT 队列真正起作用
 *   2. AutoGPT → Distribute 显式集成 — 任务 config 增加 distribute_platforms[]
 *   3. 模板改用 TemplateManager (统一数据源,与 content tab 一致)
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT\Processors
 */

namespace Linked3\Classes\AutoGPT\Processors;
use Linked3\Classes\Core\AIDispatcher;
use Linked3\Classes\Core\AIEnhancer;
use Linked3\Classes\Publish\Linked3_Publish_Manager;
use Linked3\Classes\Distribute\DistributeManager;



if (!defined('ABSPATH')) {
    exit;
}

use Linked3\Classes\ContentWriter\{
    ContentTemplateManager,
    Prompt\SystemInstructionBuilder,
    Prompt\UserPromptBuilder,
    Prompt\MarkdownHtmlConverter
};
final class Linked3_Content_Writing_Processor implements Linked3_AutoGPT_Processor_Interface
{
    public function process(array $task) : mixed {
        $cfg = $task['config'];
        $keyword = $cfg['keyword'] ?? '';
        if (!$keyword) return ['ok' => false, 'message' => __('未配置关键词。', 'linked3'), 'items_processed' => 0];
        $count = (int) ($cfg['count_per_run'] ?? 1);
        $processed = 0;
        $errors = [];

        // v3.0.0: 统一用 TemplateManager (与内容写作 tab 一致)
        $tplMgr = new \Linked3\Classes\Templates\TemplateManager();
        $all_templates = $tplMgr->get_all();
        $tplConfig = [];
        $tpl_id = (int) ($cfg['template_id'] ?? 0);
        if ($tpl_id > 0) {
            $idx = $tpl_id - 1; // 1-based → 0-based
            if (isset($all_templates[$idx])) {
                $tplConfig = is_array($all_templates[$idx]['config']) ? $all_templates[$idx]['config'] : [];
            }
        }
        if (empty($tplConfig)) {
            $tplConfig = $tplMgr->default_templates()[1]['config'] ?? []; // 中等文章
        }

        // v3.0.0: 读高级设置 (HTML 格式等)
        $require_html = false;
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $adv = $enhancer->get_settings();
            $require_html = !empty($adv['require_html']);
        }

        $repo = new \Linked3\Classes\AutoGPT\Linked3_AutoGPT_Task_Repository();

        for ($i = 0; $i < $count; $i++) {
            try {
                // 构建 prompt
                $prompt_mode = $tplConfig['prompt_mode'] ?? 'default';
                $word_count = (int) ($tplConfig['word_count'] ?? 1200);
                $topic = $keyword;
                $keywords = $keyword;

                if ($prompt_mode === 'custom' && !empty($tplConfig['custom_content_prompt'])) {
                    $sys = '你是一位专业内容写作器。请严格按照用户的指令生成内容。';
                    if ($require_html) {
                        $sys = '【强制格式要求】你必须输出 HTML 标签格式(使用 H2/H3/p/ul/li/strong 等标签),严禁输出 Markdown 语法。不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。正文不要包含 H1 标题。 ' . $sys;
                    }
                    $user = str_replace(
                        ['{topic}', '{keywords}', '{word_count}'],
                        [$topic, $keywords, (string)$word_count],
                        $tplConfig['custom_content_prompt']
                    );
                } else {
                    $sys = (new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder())->build([
                        'tone' => $tplConfig['tone'] ?? 'professional',
                        'language' => 'zh-CN',
                        'complexity' => $tplConfig['complexity'] ?? 'intermediate',
                        'seo_focus' => true,
                        'require_html' => $require_html,
                    ]);
                    $user = (new \Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder())->build([
                        'keyword' => $keyword, 'word_count' => $word_count,
                    ]);
                }

                $provider = $tplConfig['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
                $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
                $model = $tplConfig['model'] ?? ($saved_models[$provider] ?? '');
                if (empty($model)) $model = 'Qwen/Qwen2.5-7B-Instruct';

                try { // v19.3.0: AI 调用容错
                $result = AIDispatcher::instance()->chat(
                    [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                    [
                        'provider' => $provider, 'model' => $model,
                        'temperature' => $tplConfig['temperature'] ?? 0.7,
                        'max_tokens' => $tplConfig['max_tokens'] ?? 2000,
                        'module' => 'autogpt', 'user_id' => $task['user_id'],
                    ],
                    ['fallback_providers' => ['deepseek', 'zhipu']]
                );
                } catch (\Throwable $e) {
                    return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
                }
                $content = $result['content'];

                // HTML 兜底
                if ($require_html && class_exists('\\Linked3\\Classes\\ContentWriter\\Prompt\\MarkdownHtmlConverter')) {
                    $content = MarkdownHtmlConverter::convert($content, true);
                }
                // AI 标识符后缀
                if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
                    $enhancer = new \Linked3\Classes\Core\AIEnhancer();
                    $content = $enhancer->append_identifier_suffix($content);
                }

                $post = [
                    'post_title'   => ucfirst($keyword) . ' — ' . date('Y-m-d') . ' #' . ($i + 1),
                    'post_content' => $content,
                    'post_status'  => !empty($cfg['publish_directly']) ? 'publish' : 'draft',
                    'post_type'    => 'post',
                    'post_author'  => $task['user_id'],
                ];

                // v3.0.0: 发布到目标
                $target_id = (int) ($cfg['publish_target_id'] ?? 0);
                $post_id = 0;
                if ($target_id) {
                    $r = Linked3_Publish_Manager::instance()->publish_to_target($target_id, $task['user_id'], $post);
                    if (is_wp_error($r)) {
                        $errors[] = "Publish target #{$target_id} failed: " . $r->get_error_message();
                        // v3.0.0: 失败入队,5 分钟后重试
                        $repo->enqueue($task['id'], [
                            'type' => 'publish_retry',
                            'target_id' => $target_id,
                            'post_data' => $post,
                            'reason' => $r->get_error_message(),
                        ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                    } elseif (!empty($r['remote_id'])) {
                        $errors[] = "Published to target #{$target_id} (remote_id={$r['remote_id']})";
                    }
                } else {
                    $post_id = wp_insert_post(wp_slash($post), true);
                    if (is_wp_error($post_id)) {
                        $errors[] = "wp_insert_post failed: " . $post_id->get_error_message();
                        $post_id = 0;
                    }
                }

                // v3.1.0: 生成 SEO 元数据 (5 类: title/meta/keyword/excerpt/tags)
                // AutoGPT 默认全部生成,与 Content Writer UI 路径一致
                if ($post_id && class_exists('\\Linked3\\Classes\\ContentWriter\\SeoMetaGenerator')) {
                    try {
                        $seo_meta = \Linked3\Classes\ContentWriter\SeoMetaGenerator::generate_all([
                            'title' => $post['post_title'],
                            'topic' => $keyword,
                            'keywords' => $keyword,
                            'content' => $content,
                            'template_config' => $tplConfig,
                            'provider' => $provider,
                            'model' => $model,
                            'user_id' => $task['user_id'],
                            'gen_title' => false, // AutoGPT 已用 ucfirst($keyword) 作标题
                            'gen_meta' => true,
                            'gen_keyword' => true,
                            'gen_excerpt' => true,
                            'gen_tags' => true,
                        ]);
                        \Linked3\Classes\ContentWriter\SeoMetaGenerator::save_to_post($post_id, $seo_meta);
                        $errors[] = "SEO 元数据已生成 (meta/keyword/excerpt/tags)";
                    } catch (\Throwable $e) {
                        $errors[] = "SEO 元数据生成失败: " . $e->getMessage();
                    }
                }

                // v3.1.0: 图片注入 (AutoGPT 文章也配图)
                if ($post_id && !empty($cfg['inject_images']) && class_exists('\\Linked3\\Classes\\ContentWriter\\ImageInjector')) {
                    try {
                        $injector = new \Linked3\Classes\ContentWriter\ImageInjector();
                        // 读全局图片设置
                        $img_settings = (array) get_option(LINKED3_OPTION_PREFIX . 'image_settings', []);
                        $img_count = (int) ($img_settings['image_count'] ?? 1);
                        $new_content = $injector->inject($content, $keyword, $img_count, $img_settings);
                        if ($new_content !== $content) {
                            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
                            $errors[] = "已注入 {$img_count} 张配图";
                        }
                    } catch (\Throwable $e) {
                        $errors[] = "图片注入失败: " . $e->getMessage();
                    }
                }

                // v3.0.0: AutoGPT → Distribute 显式集成
                // 任务 config 中的 distribute_platforms[] 指定分发到哪些平台
                if (!empty($cfg['distribute_platforms']) && is_array($cfg['distribute_platforms']) && $post_id) {
                    $dist_mgr = DistributeManager::instance();
                    $results = $dist_mgr->distribute_post_to_platforms($post_id, $cfg['distribute_platforms']);
                    foreach ($results as $r) {
                        if (!$r['ok']) {
                            $errors[] = "Distribute to {$r['platform']} failed: " . $r['message'];
                            // v3.0.0: 分发失败入队重试
                            $dist_mgr->enqueue_retry($post_id, $r['platform'], $r['message']);
                        }
                    }
                }

                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Iteration {$i} failed: " . $e->getMessage();
                // v3.0.0: AI 调用失败也入队,5 分钟后重试
                $repo->enqueue($task['id'], [
                    'type' => 'ai_retry',
                    'iteration' => $i,
                    'keyword' => $keyword,
                    'reason' => $e->getMessage(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
            }
        }

        $ok = ($processed > 0) || ($count === 0);
        $message = $processed > 0
            ? sprintf(__('已写作 %d 篇文章。', 'linked3'), $processed)
            : sprintf(__('%d 次尝试均失败。已入队重试。', 'linked3'), $count);
        if (!empty($errors)) {
            $message .= ' 错误: ' . implode('; ', array_slice($errors, 0, 3));
        }
        return ['ok' => $ok, 'message' => $message, 'items_processed' => $processed];
    }
}
