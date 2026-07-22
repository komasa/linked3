<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Ajax\Actions;
use Linked3\Classes\ContentWriter\Ajax\ContentWriterBaseAjaxAction;
use Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder;
use Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder;
use Linked3\Classes\ContentWriter\Prompt\MarkdownHtmlConverter;
use Linked3\Classes\Core\AIEnhancer;


if (!defined('ABSPATH')) exit;
/**
 * 内容生成 Action — v2.7.0 深度对标 aipower
 *
 * 新增高级参数:
 *   - category_id: 分类 ID
 *   - author_id: 作者 ID
 *   - content_length: short/medium/long → 自动映射 word_count + max_tokens
 *   - temperature: 0-2 创造力
 *   - gen_title/gen_meta/gen_tags/gen_excerpt/gen_keyword: SEO 元数据自动生成开关
 *
 * 工作流:
 *   1. 读模板配置 (TemplateManager::get_all())
 *   2. 应用高级设置 (HTML 格式 / AI 摘要 / 标签 / 时间段)
 *   3. 调用 AI 生成正文
 *   4. Markdown→HTML 兜底转换
 *   5. 并行生成 SEO 元数据 (meta/keyword/excerpt/tags)
 *   6. 图片注入
 *   7. 保存草稿/发布 (含分类/作者)
 */
final class GenerateContentAction extends ContentWriterBaseAjaxAction
{
    public function handle(): void {
        $inputs = $this->parseContentInputs();
        if (is_array($inputs) && isset($inputs['error'])) {
            $this->send_error($inputs['error'], 400);
            return;
        }
        [
            'keyword' => $keyword, 'title' => $title, 'template_id' => $template_id,
            'inject_images' => $inject_images, 'post_status' => $post_status,
            'category_id' => $category_id, 'author_id' => $author_id,
            'content_length' => $content_length, 'temperature' => $temperature,
            'gen_title' => $gen_title, 'gen_meta' => $gen_meta, 'gen_tags' => $gen_tags,
            'gen_excerpt' => $gen_excerpt, 'gen_keyword' => $gen_keyword,
        ] = $inputs;

        $v15_context = $this->buildV15Context($inputs);

        if (empty($keyword) && empty($title)) {
            $this->send_error('关键词或标题至少填一个', 400);
        }

        $cfg = $this->loadTemplateAndLengthConfig($template_id, $content_length);
        [
            'config' => $config, 'word_count' => $word_count, 'max_tokens' => $max_tokens,
            'temperature' => $final_temp, 'tone' => $tone, 'complexity' => $complexity,
            'provider' => $provider, 'model' => $model,
        ] = $cfg;
        $temperature = $final_temp;

        $prompt = $this->buildPrompt($config, $keyword, $title, $word_count, $v15_context);
        [$sys, $user] = $prompt;

        $adv_cfg = $this->applyAdvancedSettings($config, $sys, $user, $prompt_mode = $config['prompt_mode'] ?? 'default');
        $sys = $adv_cfg['sys'];
        $user = $adv_cfg['user'];

        try {
            $ai_timeout = $content_length === 'long' ? 180 : ($content_length === 'medium' ? 150 : 120);
            $result = $this->dispatcher()->chat(
                [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => $temperature, 'max_tokens' => $max_tokens,
                    'module' => 'content_writer',
                    'timeout' => $ai_timeout,
                ],
                ['fallback_providers' => []]
            );
            $content = $result['content'];
            $generated_title = '';
            $seo_meta = '';
            $focus_keyword = '';
            $excerpt = '';
            $tags_str = '';

            // HTML 格式兜底
            if ($adv_cfg['require_html'] && class_exists('\\Linked3\\Classes\\ContentWriter\\Prompt\\MarkdownHtmlConverter')) {
                $content = MarkdownHtmlConverter::convert($content, true);
            }
            if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer') && isset($adv_cfg['enhancer']) && $adv_cfg['enhancer']) {
                $content = $adv_cfg['enhancer']->append_identifier_suffix($content);
            }

            $topic = $title ?: $keyword;
            $keywords = $keyword;

            // 2-6: SEO metadata generation
            if (empty($title) && $gen_title) {
                $generated_title = $this->generateTitle($config, $topic, $keywords, $keyword, $title, $v15_context, $provider, $model);
            }
            if ($gen_meta) {
                $seo_meta = $this->generateSeoMeta($config, $title, $topic, $keywords, $keyword, $content, $v15_context, $provider, $model);
            }
            if ($gen_keyword) {
                $focus_keyword = $this->generateFocusKeyword($config, $title, $topic, $content, $provider, $model);
            }
            if ($gen_excerpt) {
                $excerpt = $this->generateExcerpt($config, $title, $topic, $content, $v15_context, $provider, $model);
            }
            if ($gen_tags) {
                $tags_str = $this->generateTags($config, $title, $topic, $keywords, $keyword, $v15_context, $provider, $model);
            }

            // 7. 图片注入
            if ($inject_images && class_exists('\\Linked3\\Classes\\Media\\ImageManager')) {
                $content = $this->injectImages($content, $title ?: $keyword);
            }

            // 8. 保存草稿/发布
            [$saved_id, $saved_url] = $this->savePost(
                $post_status, $title ?: $generated_title ?: $keyword,
                $content, $author_id, $excerpt, $category_id, $tags_str, $seo_meta, $focus_keyword
            );

            $this->send_success([
                'content'        => $content,
                'title'          => $title ?: $generated_title ?: $keyword,
                'seo_meta'       => $seo_meta,
                'focus_keyword'  => $focus_keyword,
                'excerpt'        => $excerpt,
                'tags'           => $tags_str,
                'usage'          => $result['usage'],
                'provider'       => $result['provider'],
                'model'          => $result['model'],
                'saved_id'       => $saved_id,
                'saved_url'      => $saved_url,
                'message'        => $saved_id ? sprintf('已保存为%s', $post_status === 'publish' ? '已发布' : '草稿') : '',
            ]);
        } catch (\Exception $e) {
            $this->send_error($e->getMessage(), 502);
        }
    }

    /**
     * 解析 handle() 的 $_POST 输入.
     */
    private function parseContentInputs(): array
    {
        return [
            'keyword' => sanitize_text_field($_POST['keyword'] ?? ''),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'template_id' => (int) ($_POST['template_id'] ?? 0),
            'inject_images' => !empty($_POST['inject_images']),
            'post_status' => sanitize_text_field($_POST['post_status'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'author_id' => (int) ($_POST['author_id'] ?? get_current_user_id()),
            'content_length' => sanitize_key($_POST['content_length'] ?? 'medium'),
            'temperature' => max(0.0, min(2.0, (float) ($_POST['temperature'] ?? 0.7))),
            'gen_title' => !empty($_POST['gen_title']),
            'gen_meta' => !empty($_POST['gen_meta']),
            'gen_tags' => !empty($_POST['gen_tags']),
            'gen_excerpt' => !empty($_POST['gen_excerpt']),
            'gen_keyword' => !empty($_POST['gen_keyword']),
            'brand_profile_id' => (int) ($_POST['brand_profile_id'] ?? 0),
            'v15_mood' => sanitize_text_field($_POST['v15_mood'] ?? ''),
            'v15_culture' => sanitize_text_field($_POST['v15_culture'] ?? ''),
            'v15_platform' => sanitize_text_field($_POST['v15_platform'] ?? ''),
            'v15_brand' => sanitize_text_field($_POST['v15_brand'] ?? ''),
        ];
    }

    /**
     * 构建 V15 品牌上下文.
     */
    private function buildV15Context(array $inputs): array
    {
        $v15_context = [
            'mood' => $inputs['v15_mood'] ?? '',
            'culture' => $inputs['v15_culture'] ?? '',
            'platform' => $inputs['v15_platform'] ?? '',
            'brand' => $inputs['v15_brand'] ?? '',
        ];
        $brand_profile_id = $inputs['brand_profile_id'] ?? 0;
        if ($brand_profile_id > 0 && class_exists('\\Linked3\\Classes\\V15\\V15BrandProfileManager')) {
            global $wpdb;
            $table = $wpdb->prefix . 'linked3_v15_brand_profiles';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $brand_profile_id), ARRAY_A);
            if ($row) {
                if (empty($v15_context['mood'])) $v15_context['mood'] = $row['mood_primary'];
                if (empty($v15_context['culture'])) $v15_context['culture'] = $row['culture_region'] . '/' . $row['culture_age'];
                if (empty($v15_context['platform'])) $v15_context['platform'] = $row['platform_name'];
                $v15_context['brand'] = $row['brand_name'];
                $v15_context['color'] = $row['color_primary'] . '/' . $row['color_secondary'];
            }
        }
        return $v15_context;
    }

    /**
     * 加载模板配置 + 长度映射 + provider/model 解析.
     */
    private function loadTemplateAndLengthConfig(int $template_id, string $content_length): array
    {
        $length_map = [
            'short'  => ['word_count' => 700,  'max_tokens' => 2000],
            'medium' => ['word_count' => 1400, 'max_tokens' => 4000],
            'long'   => ['word_count' => 2250, 'max_tokens' => 6000],
        ];
        $length_cfg = $length_map[$content_length] ?? $length_map['medium'];

        $config = [];
        if ($template_id > 0 && class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
            $all_templates = $tpl_mgr->get_all();
            $idx = $template_id - 1;
            if (isset($all_templates[$idx])) {
                $tpl = $all_templates[$idx];
                $config = is_array($tpl['config']) ? $tpl['config'] : [];
            }
        }

        $word_count = (int) ($config['word_count'] ?? $length_cfg['word_count']);
        $max_tokens = max(2000, (int) $length_cfg['max_tokens']);
        $temperature = min(1.5, max(0.0, min(2.0, (float) ($_POST['temperature'] ?? 0.7))));
        $tone = $config['tone'] ?? 'professional';
        $complexity = $config['complexity'] ?? 'intermediate';

        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $provider = $config['provider'] ?? $default_provider;
        if (empty($provider)) $provider = $default_provider;
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $config['model'] ?? ($saved_models[$provider] ?? '');
        if (empty($model)) {
            $defaults = [
                'openai' => 'Qwen/Qwen2.5-7B-Instruct', 'deepseek' => 'deepseek-chat',
                'kimi' => 'moonshot-v1-8k', 'qwen' => 'qwen-plus',
                'doubao' => 'doubao-pro-4k', 'zhipu' => 'glm-4-flash',
                'zai' => 'glm-4-flash', 'siliconflow' => 'Qwen/Qwen2.5-7B-Instruct',
            ];
            $model = $defaults[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        }

        return compact('config', 'word_count', 'max_tokens', 'temperature', 'tone', 'complexity', 'provider', 'model');
    }

    /**
     * 构建 prompt (default 模式用 UserPromptBuilder, custom 模式用 Placeholder_Resolver).
     *
     * @return array{0:string,1:string} [sys, user]
     */
    private function buildPrompt(array $config, string $keyword, string $title, int $word_count, array $v15_context): array
    {
        $prompt_mode = $config['prompt_mode'] ?? 'default';
        $topic = $title ?: $keyword;
        $keywords = $keyword;

        if ($prompt_mode === 'custom' && !empty($config['custom_content_prompt'])) {
            $sys = '你是一位专业内容写作器。请严格按照用户的指令生成内容。';
            $raw_prompt = $config['custom_content_prompt'];
            if (class_exists('\\Linked3\\Classes\\Pipeline\\PipelinePlaceholderResolver')) {
                $user = \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($raw_prompt, array_merge([
                    'topic' => $topic, 'keywords' => $keywords, 'keyword' => $keyword,
                    'word_count' => (string)$word_count, 'title' => $title,
                ], $v15_context));
            } else {
                $user = str_replace(
                    ['{topic}', '{keywords}', '{word_count}', '{mood}', '{culture}', '{platform}', '{brand}', '{color}'],
                    [$topic, $keywords, (string)$word_count, $v15_context['mood'], $v15_context['culture'], $v15_context['platform'], $v15_context['brand'] ?? '', $v15_context['color'] ?? ''],
                    $raw_prompt
                );
            }
        } else {
            $user = (new \Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder())->build([
                'keyword' => $keyword, 'title' => $title, 'word_count' => $word_count,
            ]);
        }
        return [$sys ?? '', $user];
    }

    /**
     * 应用高级设置 (HTML 格式 / AI 摘要 / 标签) 到 sys+user.
     */
    private function applyAdvancedSettings(array $config, string $sys, string $user, string $prompt_mode): array
    {
        $adv_settings = [];
        $enhancer = null;
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $adv = $enhancer->get_settings();
            $adv_settings = $adv;
            $user = $enhancer->apply_format_requirements($user, $adv);
        }
        $raw_adv = (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []);
        $require_html = !empty($adv_settings['require_html']) || !empty($raw_adv['require_html']);
        $require_tag = !empty($adv_settings['require_tag']) || !empty($raw_adv['require_tag']);
        $enable_summary = !empty($adv_settings['enable_ai_summary']) || !empty($raw_adv['enable_ai_summary']);

        if ($prompt_mode !== 'custom') {
            $sys = (new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder())->build([
                'tone' => $config['tone'] ?? 'professional', 'language' => 'zh-CN',
                'complexity' => $config['complexity'] ?? 'intermediate', 'seo_focus' => true,
                'require_html' => $require_html,
            ]);
        } else {
            $html_prefix = '';
            if ($require_html) {
                $html_prefix = '【强制格式要求】你必须输出 HTML 标签格式(使用 H2/H3/p/ul/li/strong 等标签),严禁输出 Markdown 语法(如 #、##、**、- 等)。不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。正文不要包含 H1 标题。 ';
            }
            $sys = $html_prefix . $sys;
            if ($enable_summary) $sys .= ' 请在文章尾部嵌入一段适配搜索引擎精选摘要,格式为:摘要:xxx。';
            if ($require_tag)    $sys .= ' 请在文章尾部加入适当的文章 tag 标签,标签格式必须为:{1、标签1}{2、标签2}。';
        }

        $sys .= ' 重要:不要重复相同的内容或标题,不要输出无意义字符,确保文章结构完整、逻辑通顺。';
        if ($require_html) {
            $user .= "\n\n重要:请必须输出 HTML 标签格式(H2/H3/p/ul/li),不要输出 Markdown 格式。";
        }

        return compact('sys', 'user', 'require_html', 'require_tag', 'enable_summary', 'enhancer');
    }

    /**
     * 自动生成标题.
     */
    private function generateTitle(array $config, string $topic, string $keywords, string $keyword, string $title, array $v15_context, string $provider, string $model): string
    {
        try {
            $title_prompt = !empty($config['custom_title_prompt'])
                ? \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($config['custom_title_prompt'], array_merge(['topic'=>$topic,'keywords'=>$keywords,'keyword'=>$keyword,'title'=>$title?:$topic], $v15_context))
                : '为以下主题生成一个 SEO 友好的中文标题(8-15字),只返回标题文本,不要其他内容。' . "\n\n主题:{$topic}\n关键词:{$keywords}";
            $title_result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $title_prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.5, 'max_tokens' => 80, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($title_result['content']), "\"'“”‘’");
        } catch (\Throwable $e) { return ''; }
    }

    /**
     * 生成 SEO Meta Description.
     */
    private function generateSeoMeta(array $config, string $title, string $topic, string $keywords, string $keyword, string $content, array $v15_context, string $provider, string $model): string
    {
        try {
            $meta_prompt = !empty($config['custom_meta_prompt'])
                ? \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($config['custom_meta_prompt'], array_merge(['title'=>$title?:$topic,'keywords'=>$keywords,'keyword'=>$keyword,'topic'=>$topic], $v15_context))
                : '为以下文章生成 150-160 字的 SEO meta description,包含主关键词,只返回描述文本。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}\n\n正文摘要:" . mb_substr(wp_strip_all_tags($content), 0, 500);
            $meta_result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $meta_prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 200, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($meta_result['content']));
        } catch (\Throwable $e) { return ''; }
    }

    /**
     * 提取焦点关键词.
     */
    private function generateFocusKeyword(array $config, string $title, string $topic, string $content, string $provider, string $model): string
    {
        try {
            $kw_prompt = !empty($config['custom_keyword_prompt'])
                ? str_replace(['{title}', '{topic}'], [$title ?: $topic, $topic], $config['custom_keyword_prompt'])
                : '从以下文章中提取 1 个焦点关键词和 5 个长尾关键词,用逗号分隔,只返回关键词。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
            $kw_result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $kw_prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($kw_result['content']));
        } catch (\Throwable $e) { return ''; }
    }

    /**
     * 生成摘要.
     */
    private function generateExcerpt(array $config, string $title, string $topic, string $content, array $v15_context, string $provider, string $model): string
    {
        try {
            $excerpt_prompt = !empty($config['custom_excerpt_prompt'])
                ? \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($config['custom_excerpt_prompt'], array_merge(['title'=>$title?:$topic,'topic'=>$topic], $v15_context))
                : '为以下文章生成 100 字以内的摘要,只返回摘要文本。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
            $excerpt_result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $excerpt_prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($excerpt_result['content']));
        } catch (\Throwable $e) { return ''; }
    }

    /**
     * 生成标签.
     */
    private function generateTags(array $config, string $title, string $topic, string $keywords, string $keyword, array $v15_context, string $provider, string $model): string
    {
        try {
            $tags_prompt = !empty($config['custom_tags_prompt'])
                ? \Linked3\Classes\Pipeline\PipelinePlaceholderResolver::resolve($config['custom_tags_prompt'], array_merge(['title'=>$title?:$topic,'keywords'=>$keywords,'keyword'=>$keyword,'topic'=>$topic], $v15_context))
                : '为以下文章生成 5-8 个标签,用逗号分隔,只返回标签。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}";
            $tags_result = $this->dispatcher()->chat(
                [['role' => 'user', 'content' => $tags_prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 100, 'module' => 'content_writer'],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($tags_result['content']));
        } catch (\Throwable $e) { return ''; }
    }

    /**
     * 图片注入.
     */
    private function injectImages(string $content, string $title): string
    {
        $img_mgr = new \Linked3\Classes\Media\ImageManager();
        $img_settings = $img_mgr->get_settings();
        if (!$img_settings['auto_generate']) {
            return $content;
        }
        $prompt = $img_mgr->build_prompt($title, $content, $img_settings);
        $img_result = $img_mgr->generate_image($prompt);
        if (!$img_result['ok'] || !$img_result['url']) {
            return $content;
        }
        if ($img_settings['save_to_media']) {
            $attach_id = $img_mgr->sideload($img_result['url'], $title);
            if (is_wp_error($attach_id)) return $content;
            $img_url = wp_get_attachment_url($attach_id);
        } else {
            $img_url = $img_result['url'];
        }
        return $img_mgr->insert_into_content($content, $img_url, $img_settings);
    }

    /**
     * 保存草稿/发布 (含分类/作者/标签/SEO meta).
     *
     * @return array{0:int,1:string} [saved_id, saved_url]
     */
    private function savePost(string $post_status, string $final_title, string $content, int $author_id, string $excerpt, int $category_id, string $tags_str, string $seo_meta, string $focus_keyword): array
    {
        if ($post_status !== 'draft' && $post_status !== 'publish') {
            return [0, ''];
        }
        $post_data = [
            'post_title'   => $final_title,
            'post_content' => $content,
            'post_status'  => $post_status,
            'post_type'    => 'post',
            'post_author'  => $author_id > 0 ? $author_id : get_current_user_id(),
        ];
        if (!empty($excerpt)) $post_data['post_excerpt'] = $excerpt;
        $saved_id = wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($saved_id)) {
            return [0, ''];
        }
        $saved_url = get_permalink($saved_id);
        if ($category_id > 0) wp_set_post_categories($saved_id, [$category_id], false);
        if (!empty($tags_str)) {
            $tag_arr = array_filter(array_map('trim', explode(',', $tags_str)));
            if (!empty($tag_arr)) wp_set_post_tags($saved_id, $tag_arr, false);
        }
        if (!empty($seo_meta)) {
            update_post_meta($saved_id, '_aioseo_description', $seo_meta);
            update_post_meta($saved_id, '_yoast_wpseo_metadesc', $seo_meta);
            update_post_meta($saved_id, 'linked3_seo_meta', $seo_meta);
        }
        if (!empty($focus_keyword)) {
            update_post_meta($saved_id, '_yoast_wpseo_focuskw', $focus_keyword);
            update_post_meta($saved_id, 'rank_math_focus_keyword', $focus_keyword);
            update_post_meta($saved_id, 'linked3_focus_keyword', $focus_keyword);
        }
        return [$saved_id, $saved_url];
    }
}
