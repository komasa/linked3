<?php

declare(strict_types=1);
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
use Linked3\Classes\Publish\PublishManager;
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
final class ContentWritingProcessor implements AutoGPTProcessorInterface
{
    public function process(array $task) : mixed {
        // Phase 1: Validate config
        $cfg = $task['config'];
        $keyword = $cfg['keyword'] ?? '';
        if (!$keyword) {
            return ['ok' => false, 'message' => __('未配置关键词。', 'linked3'), 'items_processed' => 0];
        }
        $count = (int) ($cfg['count_per_run'] ?? 1);

        // Phase 2: Prepare shared context (template, html flag, repo)
        $ctx = $this->prepare_context($cfg);

        // Phase 3: Process each iteration
        $processed = 0;
        $errors = [];
        for ($i = 0; $i < $count; $i++) {
            try {
                $this->process_single_iteration($i, $task, $ctx, $errors);
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Iteration {$i} failed: " . $e->getMessage();
                $ctx['repo']->enqueue($task['id'], [
                    'type'      => 'ai_retry',
                    'iteration' => $i,
                    'keyword'   => $keyword,
                    'reason'    => $e->getMessage(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
            }
        }

        // Phase 4: Build result
        return $this->build_result($processed, $count, $errors);
    }

    /**
     * Prepare shared context: template config, HTML flag, repository.
     *
     * @param array $cfg Task config
     * @return array{tpl_config: array, require_html: bool, repo: object}
     */
    private function prepare_context(array $cfg) : array {
        $tplMgr = new \Linked3\Classes\Templates\TemplateManager();
        $all_templates = $tplMgr->get_all();
        $tplConfig = [];
        $tpl_id = (int) ($cfg['template_id'] ?? 0);
        if ($tpl_id > 0) {
            $idx = $tpl_id - 1;
            if (isset($all_templates[$idx])) {
                $tplConfig = is_array($all_templates[$idx]['config']) ? $all_templates[$idx]['config'] : [];
            }
        }
        if (empty($tplConfig)) {
            $tplConfig = $tplMgr->default_templates()[1]['config'] ?? [];
        }

        $require_html = false;
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $adv = $enhancer->get_settings();
            $require_html = !empty($adv['require_html']);
        }

        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();

        return [
            'tpl_config'   => $tplConfig,
            'require_html' => $require_html,
            'repo'         => $repo,
        ];
    }

    /**
     * Process a single content writing iteration.
     *
     * @param int   $i       Iteration index
     * @param array $task    Task data
     * @param array $ctx     Context from prepare_context()
     * @param array $errors  Errors array (by reference, appended to)
     */
    private function process_single_iteration(int $i, array $task, array $ctx, array &$errors) : void {
        $cfg = $task['config'];
        $keyword = $cfg['keyword'] ?? '';
        $tplConfig = $ctx['tpl_config'];
        $require_html = $ctx['require_html'];

        // Step 1: Build prompts
        [$sys, $user, $provider, $model] = $this->build_prompts($keyword, $tplConfig, $require_html);

        // Step 2: Call AI
        $content = $this->call_ai($sys, $user, $provider, $model, $tplConfig, $task['user_id']);

        // Step 3: Post-process content (HTML conversion, identifier suffix)
        $content = $this->post_process_content($content, $require_html);

        // Step 4: Build post data
        $post = $this->build_post_data($keyword, $content, $cfg, $task['user_id'], $i);

        // Step 5: Publish post
        $post_id = $this->publish_post($post, $cfg, $task, $ctx['repo'], $errors);

        // Step 6: SEO metadata
        if ($post_id) {
            $this->generate_seo_metadata($post_id, $post, $keyword, $content, $tplConfig, $provider, $model, $task['user_id'], $errors);
        }

        // Step 7: Image injection
        if ($post_id && !empty($cfg['inject_images'])) {
            $this->inject_images($post_id, $content, $keyword, $errors);
        }

        // Step 8: Distribute to platforms
        if ($post_id && !empty($cfg['distribute_platforms']) && is_array($cfg['distribute_platforms'])) {
            $this->distribute_post($post_id, $cfg['distribute_platforms'], $errors);
        }
    }

    /**
     * Build system/user prompts and determine provider/model.
     *
     * @return array [sys, user, provider, model]
     */
    private function build_prompts(string $keyword, array $tplConfig, bool $require_html) : array {
        $prompt_mode = $tplConfig['prompt_mode'] ?? 'default';
        $word_count = (int) ($tplConfig['word_count'] ?? 1200);

        if ($prompt_mode === 'custom' && !empty($tplConfig['custom_content_prompt'])) {
            $sys = '你是一位专业内容写作器。请严格按照用户的指令生成内容。';
            if ($require_html) {
                $sys = '【强制格式要求】你必须输出 HTML 标签格式(使用 H2/H3/p/ul/li/strong 等标签),严禁输出 Markdown 语法。不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。正文不要包含 H1 标题。 ' . $sys;
            }
            $user = str_replace(
                ['{topic}', '{keywords}', '{word_count}'],
                [$keyword, $keyword, (string)$word_count],
                $tplConfig['custom_content_prompt']
            );
        } else {
            $sys = (new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder())->build([
                'tone'        => $tplConfig['tone'] ?? 'professional',
                'language'    => 'zh-CN',
                'complexity'  => $tplConfig['complexity'] ?? 'intermediate',
                'seo_focus'   => true,
                'require_html'=> $require_html,
            ]);
            $user = (new \Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder())->build([
                'keyword'    => $keyword,
                'word_count' => $word_count,
            ]);
        }

        $provider = $tplConfig['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $tplConfig['model'] ?? ($saved_models[$provider] ?? '');
        if (empty($model)) $model = 'Qwen/Qwen2.5-7B-Instruct';

        return [$sys, $user, $provider, $model];
    }

    /**
     * Call AI dispatcher and return content.
     *
     * @return string AI-generated content
     * @throws \Exception On AI call failure
     */
    private function call_ai(string $sys, string $user, string $provider, string $model, array $tplConfig, int $userId) : string {
        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                [
                    'provider'    => $provider,
                    'model'       => $model,
                    'temperature' => $tplConfig['temperature'] ?? 0.7,
                    'max_tokens'  => $tplConfig['max_tokens'] ?? 2000,
                    'module'      => 'autogpt',
                    'user_id'     => $userId,
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
        } catch (\Throwable $e) {
            throw new \Exception('AI 调用失败: ' . $e->getMessage());
        }
        return $result['content'];
    }

    /**
     * Post-process content: HTML conversion and AI identifier suffix.
     */
    private function post_process_content(string $content, bool $require_html) : string {
        if ($require_html && class_exists('\\Linked3\\Classes\\ContentWriter\\Prompt\\MarkdownHtmlConverter')) {
            $content = MarkdownHtmlConverter::convert($content, true);
        }
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $content = $enhancer->append_identifier_suffix($content);
        }
        return $content;
    }

    /**
     * Build post data array.
     */
    private function build_post_data(string $keyword, string $content, array $cfg, int $userId, int $i) : array {
        return [
            'post_title'   => ucfirst($keyword) . ' — ' . date('Y-m-d') . ' #' . ($i + 1),
            'post_content' => $content,
            'post_status'  => !empty($cfg['publish_directly']) ? 'publish' : 'draft',
            'post_type'    => 'post',
            'post_author'  => $userId,
        ];
    }

    /**
     * Publish post to target or local WordPress.
     *
     * @return int Post ID (0 on failure)
     */
    private function publish_post(array $post, array $cfg, array $task, $repo, array &$errors) : int {
        $target_id = (int) ($cfg['publish_target_id'] ?? 0);

        if ($target_id) {
            $r = PublishManager::instance()->publish_to_target($target_id, $task['user_id'], $post);
            if (is_wp_error($r)) {
                $errors[] = "Publish target #{$target_id} failed: " . $r->get_error_message();
                $repo->enqueue($task['id'], [
                    'type'       => 'publish_retry',
                    'target_id'  => $target_id,
                    'post_data'  => $post,
                    'reason'     => $r->get_error_message(),
                ], gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS));
                return 0;
            }
            if (!empty($r['remote_id'])) {
                $errors[] = "Published to target #{$target_id} (remote_id={$r['remote_id']})";
            }
            return 0;
        }

        $post_id = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($post_id)) {
            $errors[] = "wp_insert_post failed: " . $post_id->get_error_message();
            return 0;
        }
        return $post_id;
    }

    /**
     * Generate SEO metadata and save to post.
     */
    private function generate_seo_metadata(int $postId, array $post, string $keyword, string $content, array $tplConfig, string $provider, string $model, int $userId, array &$errors) : void {
        if (!class_exists('\\Linked3\\Classes\\ContentWriter\\SeoMetaGenerator')) return;

        try {
            $seo_meta = \Linked3\Classes\ContentWriter\SeoMetaGenerator::generate_all([
                'title'           => $post['post_title'],
                'topic'           => $keyword,
                'keywords'        => $keyword,
                'content'         => $content,
                'template_config' => $tplConfig,
                'provider'        => $provider,
                'model'           => $model,
                'user_id'         => $userId,
                'gen_title'       => false,
                'gen_meta'        => true,
                'gen_keyword'     => true,
                'gen_excerpt'     => true,
                'gen_tags'        => true,
            ]);
            \Linked3\Classes\ContentWriter\SeoMetaGenerator::save_to_post($postId, $seo_meta);
            $errors[] = "SEO 元数据已生成 (meta/keyword/excerpt/tags)";
        } catch (\Throwable $e) {
            $errors[] = "SEO 元数据生成失败: " . $e->getMessage();
        }
    }

    /**
     * Inject images into post content.
     */
    private function inject_images(int $postId, string $content, string $keyword, array &$errors) : void {
        if (!class_exists('\\Linked3\\Classes\\ContentWriter\\ImageInjector')) return;

        try {
            $injector = new \Linked3\Classes\ContentWriter\ImageInjector();
            $img_settings = (array) get_option(LINKED3_OPTION_PREFIX . 'image_settings', []);
            $img_count = (int) ($img_settings['image_count'] ?? 1);
            $new_content = $injector->inject($content, $keyword, $img_count, $img_settings);
            if ($new_content !== $content) {
                wp_update_post(['ID' => $postId, 'post_content' => $new_content]);
                $errors[] = "已注入 {$img_count} 张配图";
            }
        } catch (\Throwable $e) {
            $errors[] = "图片注入失败: " . $e->getMessage();
        }
    }

    /**
     * Distribute post to external platforms.
     */
    private function distribute_post(int $postId, array $platforms, array &$errors) : void {
        $dist_mgr = DistributeManager::instance();
        $results = $dist_mgr->distribute_post_to_platforms($postId, $platforms);
        foreach ($results as $r) {
            if (!$r['ok']) {
                $errors[] = "Distribute to {$r['platform']} failed: " . $r['message'];
                $dist_mgr->enqueue_retry($postId, $r['platform'], $r['message']);
            }
        }
    }

    /**
     * Build final result array.
     */
    private function build_result(int $processed, int $count, array $errors) : array {
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
