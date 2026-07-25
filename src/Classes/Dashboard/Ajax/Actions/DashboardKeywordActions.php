<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\SEO\Keyword\KeywordManager;
use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\Core\AIDispatcher;
use Linked3\Classes\Dashboard\DashboardConfigAjax;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard keyword actions.
 *
 * Migrated from DashboardAjaxRegistrar (God Class) in G2.1.
 * Owns the 7 keyword management AJAX endpoints.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 * @migrated   G2.1 (2026-07-18)
 */

class DashboardKeywordActions extends DashboardBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_content_writer';
    const REQUIRED_CAP = 'manage_options';

    static function register(): void {
        add_action('wp_ajax_linked3_keyword_fetch_hot', [__CLASS__, 'keyword_fetch_hot']);
        add_action('wp_ajax_linked3_keyword_generate_tail', [__CLASS__, 'keyword_generate_tail']);
        add_action('wp_ajax_linked3_keyword_batch_generate', [__CLASS__, 'keyword_batch_generate']);
        add_action('wp_ajax_linked3_kw_save_library', [__CLASS__, 'kw_save_library']);
        add_action('wp_ajax_linked3_kw_cron_enable', [__CLASS__, 'kw_cron_enable']);
        add_action('wp_ajax_linked3_kw_cron_disable', [__CLASS__, 'kw_cron_disable']);
        add_action('wp_ajax_linked3_kw_cron_status', [__CLASS__, 'kw_cron_status']);
    }

    /**
     * AJAX: 采集热词。
     *
     * v5.1.4: AI fallback 提示词改为从管线模板 'hotword' 读取,
     * 不再硬编码。source 标签改为中文友好名称。
     */
    public static function keyword_fetch_hot() : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
        $seed = sanitize_text_field(wp_unslash($_POST['seed'] ?? ''));
        $source = sanitize_key(wp_unslash($_POST['source'] ?? 'auto'));
        $keywords = $mgr->fetch_baidu_hotwords($seed, 30, $source);

        // v5.1.4: source 中文标签映射
        $source_labels = [
            'auto' => '7源合一',
            'baidu' => '百度',
            'weibo' => '微博',
            'bilibili' => 'B站',
            'toutiao' => '头条',
            'zhihu' => '知乎',
            'google' => 'Google',
            'sogou' => '搜狗',
            'ai_fallback' => 'AI生成',
            'all_failed' => '全部失败',
        ];

        $actual_source = $source;

        // v5.1.4: 采集源失败时,用管线模板 'hotword' 的提示词做 AI fallback
        if (empty($keywords) && class_exists('\\Linked3\\Classes\\Core\\AIDispatcher')) {
            try {
                $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
                $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
                $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

                // v5.1.4: 从管线模板读取提示词 (不再硬编码)
                $ai_prompt = '';
                if (class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
                    $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
                    $pipe_templates = $tpl_mgr->get_pipeline_templates('hotword');
                    if (!empty($pipe_templates)) {
                        $tpl = $pipe_templates[0];
                        $prompt_text = $tpl['config']['prompt'] ?? '';
                        // 用 Placeholder_Resolver 替换占位符
                        if (class_exists('\\Linked3\\Classes\\Pipeline\\PipelinePlaceholderResolver')) {
                            $ai_prompt = \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($prompt_text, [
                                'seed' => $seed,
                                'count' => '20',
                                'topic' => $seed,
                            ]);
                        } else {
                            $ai_prompt = str_replace(['{seed}', '{count}', '{topic}'], [$seed, '20', $seed], $prompt_text);
                        }
                    }
                }

                if (empty($ai_prompt)) {
                    $ai_prompt = $seed
                        ? sprintf('请围绕「%s」这个主题,生成20个相关的热门搜索词或话题,每行一个,只返回词语,不要编号或解释。', $seed)
                        : '请生成20个当前可能热门的中文搜索关键词,覆盖科技/生活/财经/娱乐等领域,每行一个,只返回词语,不要编号或解释。';
                }

                try {
                    $result = AIDispatcher::instance()->chat(
                        [['role' => 'user', 'content' => $ai_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.9, 'max_tokens' => 500, 'module' => 'keyword'],
                        ['fallback_providers' => [], 'force_bypass_circuit' => true]
                    );
                } catch (\Throwable $e) {
                    wp_send_json_error(['message' => __('AI 调用失败: ', 'linked3-ai') . $e->getMessage()], 502);
                }
                $ai_keywords = array_filter(array_map('trim', explode("\n", $result['content'] ?? '')));
                $ai_keywords = array_map(function($k) {
                    return preg_replace('/^\d+[\.\、\)\】\s]+/', '', trim($k));
                }, $ai_keywords);
                $keywords = array_slice($ai_keywords, 0, 30);
                $actual_source = 'ai_fallback';
            } catch (\Throwable $e) {
                // v27.6.21-fix: Log AI failure
                if (function_exists('linked3_log')) linked3_log('keyword', 'warning', 'AI keyword generation failed: ' . $e->getMessage());
                else error_log('[linked3] AI keyword generation failed: ' . $e->getMessage());
            }
        }

        wp_send_json_success([
            'keywords' => $keywords,
            'source' => $actual_source,
            'source_label' => $source_labels[$actual_source] ?? $actual_source,
            'count' => count($keywords),
            'message' => empty($keywords)
                ? '采集失败。服务器可能无法外联热词源,且 AI 备用也未成功。请检查 API Key 配置,或直接在下方文本框手动输入关键词。'
                : ($actual_source === 'ai_fallback' ? '热词源采集失败,AI 备用生成成功' : '采集成功'),
        ]);
    }

    /**
     * AJAX: AI 生成长尾关键词。
     */
    static function keyword_generate_tail(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
        $seed = sanitize_textarea_field(wp_unslash($_POST['seed'] ?? ''));
        $count = max(5, min(100, (int) ($_POST['count'] ?? 20)));
        $keywords = $mgr->generate_tail_keywords($seed, $count);
        wp_send_json_success(['keywords' => $keywords]);
    }

    /**
     * AJAX: 批量生成文章。
     */
    static function keyword_batch_generate(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $mgr = new \Linked3\Classes\SEO\Keyword\KeywordManager();
        $keywords = array_filter(array_map('trim', explode("\n", sanitize_textarea_field(wp_unslash($_POST['keywords'] ?? '')))));
        $opts = [
            'post_status' => sanitize_text_field(wp_unslash($_POST['post_status'] ?? 'draft')),
            'enable_ai_summary' => !empty($_POST['enable_summary']),
            'additional_requirements' => sanitize_text_field(wp_unslash($_POST['additional'] ?? '')),
            'template_id' => (int) ($_POST['template_id'] ?? 0),
            'custom_prompt' => sanitize_textarea_field(wp_unslash($_POST['custom_prompt'] ?? '')),
        ];
        $result = $mgr->batch_generate_from_keywords($keywords, $opts);
        wp_send_json_success($result);
    }

    /**
     * AJAX: 保存关键词库。
     * Implementation: DashboardConfigAjax::ajax_kw_save_library()
     */
    public static function kw_save_library() : mixed     {
        return DashboardConfigAjax::ajax_kw_save_library();
    }

    /**
     * AJAX: 启用关键词定时任务。
     * Implementation: DashboardConfigAjax::ajax_kw_cron_enable()
     */
    public static function kw_cron_enable()
    {
        return DashboardConfigAjax::ajax_kw_cron_enable();
    }

    /**
     * AJAX: 禁用关键词定时任务。
     * Implementation: DashboardConfigAjax::ajax_kw_cron_disable()
     */
    public static function kw_cron_disable()
    {
        return DashboardConfigAjax::ajax_kw_cron_disable();
    }

    /**
     * AJAX: 查询关键词定时任务状态。
     * Implementation: DashboardConfigAjax::ajax_kw_cron_status()
     */
    public static function kw_cron_status()
    {
        return DashboardConfigAjax::ajax_kw_cron_status();
    }
}
