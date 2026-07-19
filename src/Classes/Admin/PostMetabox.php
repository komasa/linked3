<?php

declare(strict_types=1);
/**
 * 文章编辑页 Metabox — v2.8.0 深度对标 aipower Post Enhancer
 *
 * 功能:
 *   - 文本操作 (7 个): 改写/扩写/纠正语法/总结/生成大纲/生成FAQ/简化语气
 *   - AI 生成标题/摘要/标签/Meta
 *   - AI 生成特色图片
 *   - SEO 评分
 *   - 翻译 (中→英 / 英→中)
 *
 * @package Linked3
 * @subpackage Classes\Admin
 */

namespace Linked3\Classes\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class PostMetabox
{
    public static function register()
    : void {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('wp_ajax_linked3_metabox_ai', [__CLASS__, 'ajax_ai']);
        add_action('wp_ajax_linked3_metabox_process_text', [__CLASS__, 'ajax_process_text']);
    }

    public static function add_metabox()
    : void {
        $types = ['post', 'page', 'product'];
        foreach ($types as $type) {
            add_meta_box(
                'linked3_ai_metabox',
                'Linked3 AI 辅助',
                [__CLASS__, 'render_metabox'],
                $type,
                'side',
                'high'
            );
        }
    }

    public static function render_metabox($post) : mixed {
        wp_nonce_field('linked3_metabox', 'linked3_metabox_nonce');
        $score = get_post_meta($post->ID, '_linked3_seo_score', true);
        ?>
        <div id="linked3-metabox">
            <!-- 文本操作 (aipower Post Enhancer 对标 v2.8.0) -->
            <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
                <p style="font-weight:600;margin:0 0 6px;">📝 文本操作</p>
                <p style="margin:0 0 4px;">
                    <button type="button" class="button button-small linked3-mb-text" data-action="rewrite">改写</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="expand">扩写</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="fix_grammar">纠错</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="summarize">总结</button>
                </p>
                <p style="margin:0 0 4px;">
                    <button type="button" class="button button-small linked3-mb-text" data-action="outline">大纲</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="faqs">FAQ</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="simplify">简化</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="translate_en">中→英</button>
                </p>
                <p style="margin:0 0 4px;">
                    <button type="button" class="button button-small linked3-mb-text" data-action="translate_zh">英→中</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="make_longer">加长</button>
                    <button type="button" class="button button-small linked3-mb-text" data-action="make_shorter">缩短</button>
                </p>
            </div>

            <!-- 文章级 AI 操作 -->
            <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
                <p style="font-weight:600;margin:0 0 6px;">🎯 文章 AI</p>
                <p>
                    <button type="button" class="button linked3-mb-btn" data-action="title">生成标题</button>
                    <button type="button" class="button linked3-mb-btn" data-action="excerpt">生成摘要</button>
                </p>
                <p>
                    <button type="button" class="button linked3-mb-btn" data-action="tags">生成标签</button>
                    <button type="button" class="button linked3-mb-btn" data-action="meta">生成 SEO Meta</button>
                </p>
                <p>
                    <button type="button" class="button button-primary linked3-mb-btn" data-action="image">生成特色图片</button>
                </p>
            </div>

            <?php if ($score) : ?>
            <p><strong>SEO 评分:</strong> <span id="linked3-seo-score"><?php echo esc_html($score); ?>/100</span></p>
            <?php endif; ?>
            <div id="linked3-mb-result" style="margin-top:10px;font-size:12px;max-height:300px;overflow-y:auto;"></div>
        </div>
        <script>
        (function(){
            var nonce = document.getElementById('linked3_metabox_nonce').value;
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var postId = <?php echo (int) $post->ID; ?>;

            function getCurrentContent() {
                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                    return tinymce.get('content').getContent();
                }
                var ta = document.getElementById('content');
                return ta ? ta.value : '';
            }
            function getCurrentTitle() {
                var t = document.getElementById('title');
                return t ? t.value : '';
            }
            function setResult(html) {
                var r = document.getElementById('linked3-mb-result');
                r.innerHTML = html;
            }

            // 文章级 AI 操作
            document.querySelectorAll('.linked3-mb-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var action = btn.dataset.action;
                    var fd = new FormData();
                    fd.append('action', 'linked3_metabox_ai');
                    fd.append('nonce', nonce);
                    fd.append('sub_action', action);
                    fd.append('post_id', postId);
                    fd.append('title', getCurrentTitle());
                    fd.append('content', getCurrentContent());
                    setResult('生成中...');
                    fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                        .then(function(r){return r.json();})
                        .then(function(res){
                            if (res.success) {
                                var html = '';
                                var title = document.getElementById('title');
                                if (res.data.title && title) title.value = res.data.title;
                                if (res.data.excerpt) {
                                    var ex = document.getElementById('excerpt');
                                    if (ex) ex.value = res.data.excerpt;
                                }
                                if (res.data.tags) {
                                    var tg = document.getElementById('new-tag-post_tag');
                                    if (tg) tg.value = res.data.tags;
                                }
                                if (res.data.image_url) {
                                    html += '<p>已设置特色图片</p><img src="' + res.data.image_url + '" style="max-width:100%;" />';
                                }
                                if (res.data.message) html += '<p>' + res.data.message + '</p>';
                                setResult(html || '完成');
                            } else {
                                setResult(res.data && res.data.message ? res.data.message : '错误');
                            }
                        }).catch(function(e){ setResult('网络错误: ' + e.message); });
                });
            });

            // 文本操作 (aipower 风格 v2.8.0)
            document.querySelectorAll('.linked3-mb-text').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var action = btn.dataset.action;
                    var content = getCurrentContent();
                    if (!content || content.length < 10) {
                        alert('请先在编辑器里输入内容');
                        return;
                    }
                    // 取选中文本优先,否则用全文前 2000 字
                    var selected = '';
                    if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        var ed = tinymce.get('content');
                        selected = ed.selection.getContent({format: 'text'});
                    }
                    var textToProcess = selected || content;
                    var fd = new FormData();
                    fd.append('action', 'linked3_metabox_process_text');
                    fd.append('nonce', nonce);
                    fd.append('process_action', action);
                    fd.append('text', textToProcess);
                    setResult('AI 处理中...');
                    fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                        .then(function(r){return r.json();})
                        .then(function(res){
                            if (res.success) {
                                var html = '<p style="color:#080;font-weight:600;">✓ 处理完成</p>';
                                html += '<div style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px;margin-top:6px;border-radius:4px;max-height:200px;overflow-y:auto;font-size:11px;">' +
                                    String(res.data.result).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</div>';
                                // 一键插入按钮
                                html += '<p style="margin-top:6px;"><button type="button" class="button button-small linked3-mb-insert">插入到编辑器</button></p>';
                                setResult(html);
                                // 绑定插入事件
                                var insBtn = document.querySelector('.linked3-mb-insert');
                                if (insBtn) {
                                    insBtn.addEventListener('click', function(){
                                        var insertText = res.data.result;
                                        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                                            var ed = tinymce.get('content');
                                            ed.execCommand('mceInsertContent', false, '<p>' + insertText.replace(/\n/g, '</p><p>') + '</p>');
                                        } else {
                                            var ta = document.getElementById('content');
                                            if (ta) ta.value += '\n\n' + insertText;
                                        }
                                        setResult('已插入到编辑器');
                                    });
                                }
                            } else {
                                setResult(res.data && res.data.message ? res.data.message : '错误');
                            }
                        }).catch(function(e){ setResult('网络错误: ' + e.message); });
                });
            });
        })();
        </script>
        <?php
    }

    public static function save_metabox($post_id, $post)
    : void {
        if (!isset($_POST['linked3_metabox_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_metabox_nonce'])), 'linked3_metabox')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    }

    /**
     * AJAX: 文章级 AI 操作 (标题/摘要/标签/Meta/图片)
     */
    public static function ajax_ai()
    : void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
        $sub = sanitize_text_field($_POST['sub_action'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $post_id = (int) ($_POST['post_id'] ?? 0);

        try {
            $dispatcher = \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance();
            // v2.8.0: 用用户配置的默认 Provider,而非硬编码 openai
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'gpt-4o-mini';
            $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            $api_key = $keys[$provider] ?? '';

            switch ($sub) {
                case 'title':
                    $prompt = "为以下文章内容生成 5 个吸引人的标题(每行一个,不要编号):\n\n" . mb_substr($content, 0, 2000);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.7, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    $titles = array_filter(array_map('trim', explode("\n", $r['content'])));
                    wp_send_json_success(['title' => $titles[0] ?? '', 'message' => __('已生成标题(可选其他): ', 'linked3-ai') . implode(' / ', array_slice($titles, 1, 3))]);
                    break;

                case 'excerpt':
                    $prompt = "为以下文章生成一段 100-150 字的摘要:\n\n标题: {$title}\n\n" . mb_substr($content, 0, 2000);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    wp_send_json_success(['excerpt' => trim($r['content'])]);
                    break;

                case 'tags':
                    $prompt = "为以下文章生成 5-8 个标签(逗号分隔,不要编号):\n\n标题: {$title}\n\n" . mb_substr($content, 0, 1500);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    wp_send_json_success(['tags' => trim($r['content'])]);
                    break;

                case 'meta':
                    $prompt = "为以下文章生成 SEO meta description (150 字以内):\n\n标题: {$title}\n\n" . mb_substr($content, 0, 1500);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    $meta = trim($r['content']);
                    if ($post_id) {
                        update_post_meta($post_id, '_linked3_meta_description', $meta);
                        update_post_meta($post_id, '_aioseo_description', $meta);
                        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta);
                    }
                    wp_send_json_success(['message' => __('SEO Meta 已保存: ', 'linked3-ai') . $meta]);
                    break;

                case 'image':
                    if (empty($keys['openai'])) {
                        wp_send_json_error(['message' => __('需要 OpenAI API Key 生成图片 (在 API 设置里配置)', 'linked3-ai')]);
                    }
                    $prompt = "为文章《{$title}》生成一张配图,风格: 现代简约,横版";
                    $url = 'https://api.openai.com/v1/images/generations';
                    $resp = \Linked3\Includes\Http\Linked3_Safe_Remote::post($url, [
                        'timeout' => 60,
                        'headers' => ['Authorization' => 'Bearer ' . $keys['openai'], 'Content-Type' => 'application/json'],
                        'body' => wp_json_encode(['model' => 'dall-e-3', 'prompt' => $prompt, 'n' => 1, 'size' => '1792x1024']),
                        'allowed_hosts' => ['api.openai.com'],
                    ]);
                    if (is_wp_error($resp)) {
                        wp_send_json_error(['message' => $resp->get_error_message()]);
                    }
                    $body = json_decode(wp_remote_retrieve_body($resp), true);
                    $image_url = $body['data'][0]['url'] ?? '';
                    if (!$image_url) {
                        wp_send_json_error(['message' => __('图片生成失败', 'linked3-ai')]);
                    }
                    if (!function_exists('media_handle_sideload')) {
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                    }
                    $tmp = download_url($image_url);
                    if (is_wp_error($tmp)) wp_send_json_error(['message' => __('下载失败', 'linked3-ai')]);
                    $file = ['name' => 'linked3-' . time() . '.png', 'tmp_name' => $tmp];
                    $attach_id = media_handle_sideload($file, $post_id);
                    if (is_wp_error($attach_id)) wp_send_json_error(['message' => __('媒体库插入失败', 'linked3-ai')]);
                    set_post_thumbnail($post_id, $attach_id);
                    wp_send_json_success(['image_url' => wp_get_attachment_url($attach_id), 'message' => __('特色图片已设置', 'linked3-ai')]);
                    break;
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: 文本操作 (aipower Post Enhancer 对标 v2.8.0)
     *
     * 11 个操作: rewrite/expand/fix_grammar/summarize/outline/faqs/simplify/
     *            translate_en/translate_zh/make_longer/make_shorter
     */
    public static function ajax_process_text()
    : void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
        $action_key = sanitize_key($_POST['process_action'] ?? '');
        $text = wp_strip_all_tags(wp_unslash($_POST['text'] ?? ''));
        if (empty($text) || mb_strlen($text) < 5) {
            wp_send_json_error(['message' => __('文本太短', 'linked3-ai')]);
        }

        // 11 个操作的 prompt 模板 (aipower 风格)
        $prompts = [
            'rewrite'       => '改写以下文本以提升清晰度和吸引力,保持原意不变。只返回改写后的文本,不要解释:\n\n"%s"',
            'expand'         => '扩写以下内容,补充更多细节和例子。只返回扩写后的文本,不要解释:\n\n"%s"',
            'fix_grammar'   => '纠正以下文本中的拼写和语法错误。只返回纠正后的文本,不要解释:\n\n"%s"',
            'summarize'     => '用 3-5 句简洁的话总结以下文本,保留关键事实和语气。只返回总结,不要解释:\n\n"%s"',
            'outline'       => '为以下文本生成清晰的大纲,使用 Markdown 格式 (## H2, ### H3),必要时加项目符号。只返回大纲:\n\n"%s"',
            'faqs'          => '基于以下文本生成 5-7 个相关的 FAQ 问题和简短答案,用 Markdown Q/A 格式。只返回 FAQ:\n\n"%s"',
            'simplify'      => '用友好、简单的语气 (7-8 年级可读性) 重写以下文本,保持原意和结构。只返回重写后的文本:\n\n"%s"',
            'translate_en'  => '将以下中文文本翻译成地道、流畅的英文。只返回译文,不要解释:\n\n"%s"',
            'translate_zh'  => '将以下英文文本翻译成地道、流畅的中文。只返回译文,不要解释:\n\n"%s"',
            'make_longer'   => '在不改变原意的前提下,扩展以下文本使其更长更详细。只返回扩展后的文本:\n\n"%s"',
            'make_shorter'  => '在不改变原意的前提下,精简以下文本使其更短。只返回精简后的文本:\n\n"%s"',
        ];
        if (!isset($prompts[$action_key])) {
            wp_send_json_error(['message' => __('未知操作: ', 'linked3-ai') . $action_key]);
        }

        // 截断文本避免超长
        $truncated = mb_substr($text, 0, 3000);
        $prompt = sprintf($prompts[$action_key], $truncated);

        try {
            $dispatcher = \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance();
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'gpt-4o-mini';

            $r = $dispatcher->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => 0.6, 'max_tokens' => 2000, 'module' => 'metabox',
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
            wp_send_json_success(['result' => trim($r['content']), 'usage' => $r['usage']]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
