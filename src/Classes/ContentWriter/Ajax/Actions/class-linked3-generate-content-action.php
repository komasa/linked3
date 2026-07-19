<?php
namespace Linked3\Classes\ContentWriter\Ajax\Actions;
use Linked3\Classes\ContentWriter\Ajax\Linked3_Content_Writer_Base_Ajax_Action;
use Linked3\Classes\ContentWriter\Prompt\Linked3_System_Instruction_Builder;
use Linked3\Classes\ContentWriter\Prompt\Linked3_User_Prompt_Builder;
use Linked3\Classes\ContentWriter\Prompt\Linked3_Markdown_Html_Converter;
use Linked3\Classes\Core\Linked3_AI_Enhancer;


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
final class Linked3_Generate_Content_Action extends Linked3_Content_Writer_Base_Ajax_Action
{
    public function handle()
    : void {
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $template_id = (int) ($_POST['template_id'] ?? 0);
        $inject_images = !empty($_POST['inject_images']);
        $post_status = sanitize_text_field($_POST['post_status'] ?? '');

        // v2.7.0 高级参数
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $author_id = (int) ($_POST['author_id'] ?? get_current_user_id());
        $content_length = sanitize_key($_POST['content_length'] ?? 'medium');
        $temperature = max(0.0, min(2.0, (float) ($_POST['temperature'] ?? 0.7)));
        $gen_title = !empty($_POST['gen_title']);
        $gen_meta = !empty($_POST['gen_meta']);
        $gen_tags = !empty($_POST['gen_tags']);
        $gen_excerpt = !empty($_POST['gen_excerpt']);
        $gen_keyword = !empty($_POST['gen_keyword']);

        // v5.2.1: V15 品牌配置参数
        $brand_profile_id = (int) ($_POST['brand_profile_id'] ?? 0);
        $v15_mood = sanitize_text_field($_POST['v15_mood'] ?? '');
        $v15_culture = sanitize_text_field($_POST['v15_culture'] ?? '');
        $v15_platform = sanitize_text_field($_POST['v15_platform'] ?? '');
        $v15_brand = sanitize_text_field($_POST['v15_brand'] ?? ''); // v5.2.6

        // 解析 V15 占位符上下文
        $v15_context = [
            'mood' => $v15_mood,
            'culture' => $v15_culture,
            'platform' => $v15_platform,
            'brand' => $v15_brand,
        ];

        // 如果选了品牌配置,从 DB 读取并合并 (手动输入优先)
        if ($brand_profile_id > 0 && class_exists('\\Linked3\\Classes\\V15\\Linked3_V15_Brand_Profile_Manager')) {
            $bp_mgr = \Linked3\Classes\V15\Linked3_V15_Brand_Profile_Manager::instance();
            global $wpdb;
            $table = $wpdb->prefix . 'linked3_v15_brand_profiles';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $brand_profile_id), ARRAY_A);
            if ($row) {
                if (empty($v15_mood)) $v15_context['mood'] = $row['mood_primary'];
                if (empty($v15_culture)) $v15_context['culture'] = $row['culture_region'] . '/' . $row['culture_age'];
                if (empty($v15_platform)) $v15_context['platform'] = $row['platform_name'];
                $v15_context['brand'] = $row['brand_name'];
                $v15_context['color'] = $row['color_primary'] . '/' . $row['color_secondary'];
            }
        }

        if (empty($keyword) && empty($title)) {
            $this->send_error('关键词或标题至少填一个', 400);
        }

        // content_length → word_count + max_tokens 映射 (aipower 对标)
        $length_map = [
            'short'  => ['word_count' => 700,  'max_tokens' => 2000],
            'medium' => ['word_count' => 1400, 'max_tokens' => 4000],
            'long'   => ['word_count' => 2250, 'max_tokens' => 6000],
        ];
        $length_cfg = $length_map[$content_length] ?? $length_map['medium'];

        // 加载模板配置 (v2.6.0: TemplateManager — 内置 5 个 + 用户自定义)
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

        // 字数: 模板优先,其次用 content_length 映射
        $word_count = (int) ($config['word_count'] ?? $length_cfg['word_count']);
        // v5.2.5: 确保 max_tokens 足够 (最低 2000),防止截断导致乱码
        $max_tokens = max(2000, (int) $length_cfg['max_tokens']);
        // v5.2.5: temperature 上限 1.5 防止过高导致重复乱码
        $temperature = min(1.5, $temperature);
        $tone = $config['tone'] ?? 'professional';
        $complexity = $config['complexity'] ?? 'intermediate';

        // 读默认 Provider + Model
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

        // 构建 Prompt — 支持模板自定义 prompt 模式 (v2.1.0)
        $prompt_mode = $config['prompt_mode'] ?? 'default';
        $topic = $title ?: $keyword;
        $keywords = $keyword;

        if ($prompt_mode === 'custom' && !empty($config['custom_content_prompt'])) {
            $sys = '你是一位专业内容写作器。请严格按照用户的指令生成内容。';
            // v5.2.1: 用 Placeholder_Resolver 替换所有占位符 (含 V15)
            $raw_prompt = $config['custom_content_prompt'];
            if (class_exists('\\Linked3\\Classes\\Pipeline\\Linked3_Pipeline_Placeholder_Resolver')) {
                $user = \Linked3\Classes\Pipeline\Linked3_Pipeline_Placeholder_Resolver::resolve($raw_prompt, array_merge([
                    'topic' => $topic,
                    'keywords' => $keywords,
                    'keyword' => $keyword,
                    'word_count' => (string)$word_count,
                    'title' => $title,
                ], $v15_context));
            } else {
                $user = str_replace(
                    ['{topic}', '{keywords}', '{word_count}', '{mood}', '{culture}', '{platform}', '{brand}', '{color}'],
                    [$topic, $keywords, (string)$word_count, $v15_context['mood'], $v15_context['culture'], $v15_context['platform'], $v15_context['brand'] ?? '', $v15_context['color'] ?? ''],
                    $raw_prompt
                );
            }
        } else {
            $user = (new \Linked3\Classes\ContentWriter\Prompt\Linked3_User_Prompt_Builder())->build([
                'keyword' => $keyword, 'title' => $title, 'word_count' => $word_count,
            ]);
        }

        // 高级设置: HTML格式/AI摘要/标签
        $adv_settings = [];
        $enhancer = null;
        if (class_exists('\\Linked3\\Classes\\Core\\Linked3_AI_Enhancer')) {
            $enhancer = new \Linked3\Classes\Core\Linked3_AI_Enhancer();
            $adv = $enhancer->get_settings();
            $adv_settings = $adv;
            $user = $enhancer->apply_format_requirements($user, $adv);
        }

        // 直接检查 option (双重保险)
        $raw_adv = (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []);
        $require_html = !empty($adv_settings['require_html']) || !empty($raw_adv['require_html']);
        $require_tag = !empty($adv_settings['require_tag']) || !empty($raw_adv['require_tag']);
        $enable_summary = !empty($adv_settings['enable_ai_summary']) || !empty($raw_adv['enable_ai_summary']);

        // System prompt 根据 HTML 设置调整 — 无论 default 还是 custom 模式都生效
        if ($prompt_mode !== 'custom') {
            $sys = (new \Linked3\Classes\ContentWriter\Prompt\Linked3_System_Instruction_Builder())->build([
                'tone' => $tone, 'language' => 'zh-CN', 'complexity' => $complexity, 'seo_focus' => true,
                'require_html' => $require_html,
            ]);
        } else {
            // custom 模式: HTML 指令前置以提高优先级
            $html_prefix = '';
            if ($require_html) {
                $html_prefix = '【强制格式要求】你必须输出 HTML 标签格式(使用 H2/H3/p/ul/li/strong 等标签),严禁输出 Markdown 语法(如 #、##、**、- 等)。不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。正文不要包含 H1 标题。 ';
            }
            $sys = $html_prefix . $sys;
            if ($enable_summary) {
                $sys .= ' 请在文章尾部嵌入一段适配搜索引擎精选摘要,格式为:摘要:xxx。';
            }
            if ($require_tag) {
                $sys .= ' 请在文章尾部加入适当的文章 tag 标签,标签格式必须为:{1、标签1}{2、标签2}。';
            }
        }

        // v5.2.5: 系统 prompt 增加防重复/防乱码指令
        $sys .= ' 重要:不要重复相同的内容或标题,不要输出无意义字符,确保文章结构完整、逻辑通顺。';

        // user prompt 末尾再强调 HTML (双重保险)
        if ($require_html) {
            $user .= "\n\n重要:请必须输出 HTML 标签格式(H2/H3/p/ul/li),不要输出 Markdown 格式。";
        }

        try {
            // 1. 生成正文 — v5.2.9: 传入 timeout 基于 content_length 动态调整
            $ai_timeout = $content_length === 'long' ? 180 : ($content_length === 'medium' ? 150 : 120);
            $result = $this->dispatcher()->chat(
                [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => $temperature, 'max_tokens' => $max_tokens,
                    'module' => 'content_writer',
                    'timeout' => $ai_timeout, // v5.2.9: 动态超时
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
            if ($require_html && class_exists('\\Linked3\\Classes\\ContentWriter\\Prompt\\Linked3_Markdown_Html_Converter')) {
                $content = Linked3_Markdown_Html_Converter::convert($content, true);
            }

            // AI 标识符后缀
            if (class_exists('\\Linked3\\Classes\\Core\\Linked3_AI_Enhancer')) {
                $content = $enhancer->append_identifier_suffix($content);
            }

            // 2. 自动生成标题 (若用户没填且勾选了 gen_title)
            if (empty($title) && $gen_title) {
                try {
                    $title_prompt = !empty($config['custom_title_prompt'])
                        ? \Linked3\Classes\Pipeline\Linked3_Pipeline_Placeholder_Resolver::resolve($config['custom_title_prompt'], array_merge(['topic'=>$topic,'keywords'=>$keywords,'keyword'=>$keyword,'title'=>$title?:$topic], $v15_context))
                        : '为以下主题生成一个 SEO 友好的中文标题(8-15字),只返回标题文本,不要其他内容。' . "\n\n主题:{$topic}\n关键词:{$keywords}";
                    $title_result = $this->dispatcher()->chat(
                        [['role' => 'user', 'content' => $title_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.5, 'max_tokens' => 80, 'module' => 'content_writer'],
                        ['fallback_providers' => []]
                    );
                    $generated_title = trim(wp_strip_all_tags($title_result['content']));
                    // 去掉引号 (中英文引号)
                    $generated_title = trim($generated_title, "\"'“”‘’");
                } catch (\Throwable $e) { /* 静默失败 */ }
            }

            // 3. 生成 SEO Meta Description
            if ($gen_meta) {
                try {
                    $meta_prompt = !empty($config['custom_meta_prompt'])
                        ? \Linked3\Classes\Pipeline\Linked3_Pipeline_Placeholder_Resolver::resolve($config['custom_meta_prompt'], array_merge(['title'=>$title?:$topic,'keywords'=>$keywords,'keyword'=>$keyword,'topic'=>$topic], $v15_context))
                        : '为以下文章生成 150-160 字的 SEO meta description,包含主关键词,只返回描述文本。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}\n\n正文摘要:" . mb_substr(wp_strip_all_tags($content), 0, 500);
                    $meta_result = $this->dispatcher()->chat(
                        [['role' => 'user', 'content' => $meta_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 200, 'module' => 'content_writer'],
                        ['fallback_providers' => []]
                    );
                    $seo_meta = trim(wp_strip_all_tags($meta_result['content']));
                } catch (\Throwable $e) { /* 静默失败 */ }
            }

            // 4. 提取焦点关键词
            if ($gen_keyword) {
                try {
                    $kw_prompt = !empty($config['custom_keyword_prompt'])
                        ? str_replace(['{title}', '{topic}'], [$title ?: $topic, $topic], $config['custom_keyword_prompt'])
                        : '从以下文章中提取 1 个焦点关键词和 5 个长尾关键词,用逗号分隔,只返回关键词。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
                    $kw_result = $this->dispatcher()->chat(
                        [['role' => 'user', 'content' => $kw_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer'],
                        ['fallback_providers' => []]
                    );
                    $focus_keyword = trim(wp_strip_all_tags($kw_result['content']));
                } catch (\Throwable $e) { /* 静默失败 */ }
            }

            // 5. 生成摘要
            if ($gen_excerpt) {
                try {
                    $excerpt_prompt = !empty($config['custom_excerpt_prompt'])
                        ? \Linked3\Classes\Pipeline\Linked3_Pipeline_Placeholder_Resolver::resolve($config['custom_excerpt_prompt'], array_merge(['title'=>$title?:$topic,'topic'=>$topic], $v15_context))
                        : '为以下文章生成 100 字以内的摘要,只返回摘要文本。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
                    $excerpt_result = $this->dispatcher()->chat(
                        [['role' => 'user', 'content' => $excerpt_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer'],
                        ['fallback_providers' => []]
                    );
                    $excerpt = trim(wp_strip_all_tags($excerpt_result['content']));
                } catch (\Throwable $e) { /* 静默失败 */ }
            }

            // 6. 生成标签
            if ($gen_tags) {
                try {
                    $tags_prompt = !empty($config['custom_tags_prompt'])
                        ? \Linked3\Classes\Pipeline\Linked3_Pipeline_Placeholder_Resolver::resolve($config['custom_tags_prompt'], array_merge(['title'=>$title?:$topic,'keywords'=>$keywords,'keyword'=>$keyword,'topic'=>$topic], $v15_context))
                        : '为以下文章生成 5-8 个标签,用逗号分隔,只返回标签。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}";
                    $tags_result = $this->dispatcher()->chat(
                        [['role' => 'user', 'content' => $tags_prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 100, 'module' => 'content_writer'],
                        ['fallback_providers' => []]
                    );
                    $tags_str = trim(wp_strip_all_tags($tags_result['content']));
                } catch (\Throwable $e) { /* 静默失败 */ }
            }

            // 7. 图片注入
            if ($inject_images && class_exists('\\Linked3\\Classes\\Media\\Linked3_Image_Manager')) {
                $img_mgr = new \Linked3\Classes\Media\Linked3_Image_Manager();
                $img_settings = $img_mgr->get_settings();
                if ($img_settings['auto_generate']) {
                    $prompt = $img_mgr->build_prompt($title ?: $keyword, $content, $img_settings);
                    $img_result = $img_mgr->generate_image($prompt);
                    if ($img_result['ok'] && $img_result['url']) {
                        if ($img_settings['save_to_media']) {
                            $attach_id = $img_mgr->sideload($img_result['url'], $title ?: $keyword);
                            if (!is_wp_error($attach_id)) {
                                $img_url = wp_get_attachment_url($attach_id);
                                $content = $img_mgr->insert_into_content($content, $img_url, $img_settings);
                            }
                        } else {
                            $content = $img_mgr->insert_into_content($content, $img_result['url'], $img_settings);
                        }
                    }
                }
            }

            // 8. 保存草稿/发布 (含分类/作者)
            $saved_id = 0;
            $saved_url = '';
            $final_title = $title ?: $generated_title ?: $keyword;

            if ($post_status === 'draft' || $post_status === 'publish') {
                $post_data = [
                    'post_title'   => $final_title,
                    'post_content' => $content,
                    'post_status'  => $post_status,
                    'post_type'    => 'post',
                    'post_author'  => $author_id > 0 ? $author_id : get_current_user_id(),
                ];
                if (!empty($excerpt)) $post_data['post_excerpt'] = $excerpt;
                $saved_id = wp_insert_post(wp_slash($post_data), true);
                if (!is_wp_error($saved_id)) {
                    $saved_url = get_permalink($saved_id);
                    // 设置分类
                    if ($category_id > 0) {
                        wp_set_post_categories($saved_id, [$category_id], false);
                    }
                    // 设置标签
                    if (!empty($tags_str)) {
                        $tag_arr = array_filter(array_map('trim', explode(',', $tags_str)));
                        if (!empty($tag_arr)) {
                            wp_set_post_tags($saved_id, $tag_arr, false);
                        }
                    }
                    // 保存 SEO meta (用 _aioseo_description / _yoast_wpseo_metadesc 兼容主流 SEO 插件)
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
                } else {
                    $saved_id = 0;
                }
            }

            $this->send_success([
                'content'        => $content,
                'title'          => $final_title,
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
}
