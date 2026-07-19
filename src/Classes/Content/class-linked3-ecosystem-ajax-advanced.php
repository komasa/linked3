<?php
namespace Linked3\Classes\Content;
if (!defined('ABSPATH')) exit;
class Linked3_Ecosystem_Ajax_Advanced
{
    private static function call_ai(string $prompt, int $max_tokens = 2000): string {
        return Linked3_Ecosystem_Ajax::call_ai_internal($prompt, $max_tokens);
    }
    public static function ajax_generate_images() : mixed {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $images_json = wp_unslash($_POST['images'] ?? '[]');
        $images = json_decode($images_json, true);
        if (!is_array($images) || empty($images)) wp_send_json_error(['message' => __('图片列表为空', 'linked3-ai')]);

        // 读取图片API配置
        $provider = get_option(LINKED3_OPTION_PREFIX . 'image_provider', 'siliconflow');
        $model = get_option(LINKED3_OPTION_PREFIX . 'image_model', 'Kwai-Kolors/Kolors');
        $api_base = get_option(LINKED3_OPTION_PREFIX . 'image_api_base', '');
        $api_key = get_option(LINKED3_OPTION_PREFIX . 'image_api_key', '');
        $width = get_option(LINKED3_OPTION_PREFIX . 'image_width', 1024);
        $height = get_option(LINKED3_OPTION_PREFIX . 'image_height', 1024);

        // 如果没有单独配置图片API Key, 使用硅基流动文本API Key
        if (empty($api_key)) {
            $text_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            $api_key = $text_keys['siliconflow'] ?? '';
        }
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('未配置API Key, 请先在API设置页配置硅基流动Key', 'linked3-ai')]);
        }

        // 默认API地址
        if (empty($api_base)) {
            $default_bases = [
                'siliconflow' => 'https://api.siliconflow.cn/v1',
                'openai' => 'https://api.openai.com/v1',
                'tongyi' => 'https://dashscope.aliyuncs.com/api/v1',
            ];
            $api_base = $default_bases[$provider] ?? 'https://api.siliconflow.cn/v1';
        }

        @set_time_limit(300);

        $results = [];
        foreach ($images as $idx => $img) {
            $prompt = sanitize_text_field($img['prompt'] ?? '');
            $img_type = sanitize_text_field($img['type'] ?? 'img_' . $idx);

            if (empty($prompt)) {
                $results[] = ['type' => $img_type, 'success' => false, 'error' => 'Prompt为空'];
                continue;
            }

            try {
                // 调用图片生成API (硅基流动/OpenAI兼容接口)
                $endpoint = rtrim($api_base, '/') . '/images/generations';
                $body = [
                    'model' => $model,
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => $width . 'x' . $height,
                ];

                $response = wp_remote_post($endpoint, [
                    'timeout' => 120,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode($body),
                ]);

                if (is_wp_error($response)) {
                    $results[] = ['type' => $img_type, 'success' => false, 'error' => $response->get_error_message()];
                    continue;
                }

                $code = wp_remote_retrieve_response_code($response);
                $body_json = json_decode(wp_remote_retrieve_body($response), true);

                if ($code !== 200 || empty($body_json['data'][0]['url'])) {
                    $err_msg = $body_json['error']['message'] ?? ($body_json['message'] ?? 'HTTP ' . $code);
                    $results[] = ['type' => $img_type, 'success' => false, 'error' => $err_msg];
                    continue;
                }

                $image_url = $body_json['data'][0]['url'];
                $results[] = [
                    'type' => $img_type,
                    'success' => true,
                    'url' => $image_url,
                    'prompt' => $prompt,
                    'model' => $model,
                ];

            } catch (\Throwable $e) {
                $results[] = ['type' => $img_type, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        $success_count = count(array_filter($results, function($r) { return $r['success'] ?? false; }));

        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'success_count' => $success_count,
            'message' => sprintf('图片生成完成: %d/%d 成功', $success_count, count($results)),
        ]);
    }

    public static function ajax_hot_collect() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $source = sanitize_key($_POST['source'] ?? 'all'); // v16.0.15: 默认 all
        $count = max(5, min(100, intval($_POST['count'] ?? 20)));

        @set_time_limit(60);

        // v10.9.0: 真实AI热词生成 (绞杀假大空硬编码seeds)
        $source_names = [
            'baidu'  => '百度热搜',
            'sogou'  => '搜狗热词',
            '360'    => '360热词',
            'zhihu'  => '知乎热榜',
            'weibo'  => '微博热搜',
            'douyin' => '抖音热词',
        ];
        $source_label = $source_names[$source] ?? '百度热搜';

        // v16.0.15 [公理α: H↓ 消除选源不确定性] [公理β: dim↓ 0维默认全部源]
        // source=all → 串行采集6源 → 去重合并 → Top N (串行降级防限流, O部隐性约束)
        $hot_words = [];
        $extra_meta = [];
        if ($source === 'all') {
            $all_sources = array_keys($source_names);
            $per_source = max(3, intval($count / count($all_sources)) + 2);
            $aggregated = [];
            $failed_sources = [];
            foreach ($all_sources as $src) {
                $src_label = $source_names[$src];
                $prompt_all = sprintf(
                    "你是%s的趋势分析专家。请生成%d个当前真实热门的关键词。\n\n严格要求:\n1. 每行一个关键词, 不要编号, 不要标点符号\n2. 关键词长度2-8个字 (短词优先)\n3. 必须是真实存在的热门话题/技术/产品名, 不要编造\n4. 覆盖科技/AI/生活/娱乐/社会等多个领域\n5. 不要输出任何说明文字, 只输出关键词列表\n\n现在请输出%d个%s热门关键词:",
                    $src_label, $per_source, $per_source, $src_label
                );
                $ai_result = self::call_ai($prompt_all, 800);
                if (!empty($ai_result)) {
                    foreach (explode("\n", $ai_result) as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        $line = preg_replace('/^[\d]+[.、\)\）]\s*/', '', $line);
                        $line = preg_replace('/^[\x{201C}\x{201D}\x{2018}\x{2019}\x{300C}\x{300D}\x{3010}\x{3011}"\'\(\)\[\]\x{FF08}\x{FF09}]+/u', '', $line);
                        $line = preg_replace('/[\x{201C}\x{201D}\x{2018}\x{2019}\x{300C}\x{300D}\x{3010}\x{3011}"\'\(\)\[\]\x{FF08}\x{FF09}]+$/u', '', $line);
                        $line = trim($line);
                        $line = preg_replace('/^(关键词|热词|话题)[:：]\s*/', '', $line);
                        $len = mb_strlen($line);
                        if ($len < 2 || $len > 15) continue;
                        if (preg_match('/(请|要求|输出|格式|示例|严格|注意)/', $line)) continue;
                        $aggregated[$line] = 1;
                    }
                } else {
                    $failed_sources[] = $src_label;
                }
            }
            $hot_words = array_slice(array_keys($aggregated), 0, $count);
            if (empty($hot_words)) {
                wp_send_json_error(['message' => __('全部源采集失败, 请检查AI API Key配置 (设置→API设置)', 'linked3-ai')]);
            }
            $source_label = '全部源 (聚合' . count($all_sources) . '源)';
            $extra_meta = ['failed_sources' => $failed_sources];
        } else {
        $prompt = sprintf(
            "你是%s的趋势分析专家。请生成%d个当前真实热门的关键词。\n\n严格要求:\n1. 每行一个关键词, 不要编号, 不要标点符号\n2. 关键词长度2-8个字 (短词优先)\n3. 必须是真实存在的热门话题/技术/产品名, 不要编造\n4. 覆盖科技/AI/生活/娱乐/社会等多个领域\n5. 不要输出任何说明文字, 只输出关键词列表\n\n示例格式:\n人工智能\n量子计算\nChatGPT\n大模型\n\n现在请输出%d个%s热门关键词:",
            $source_label, $count, $count, $source_label
        );
        $ai_result = self::call_ai($prompt, 1000);

        if (!empty($ai_result)) {
            // v11.0.9 #1: 后端清洗 — 去编号/去标点/去空行/长度过滤
            $lines = explode("\n", $ai_result);
            $hot_words = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                // 去除行首编号 (1. / 1、 / 1) 等)
                $line = preg_replace('/^[\d]+[.、\)）]\s*/', '', $line);
                // v11.1.1 P0修复: 去除首尾标点和引号 (用正则替代trim, 避免全角字符导致syntax error)
                // 用hex编码避免全角字符在单引号字符串中的解析问题
                $line = preg_replace('/^[\x{201C}\x{201D}\x{2018}\x{2019}\x{300C}\x{300D}\x{3010}\x{3011}"\'\(\)\[\]\x{FF08}\x{FF09}]+/u', '', $line);
                $line = preg_replace('/[\x{201C}\x{201D}\x{2018}\x{2019}\x{300C}\x{300D}\x{3010}\x{3011}"\'\(\)\[\]\x{FF08}\x{FF09}]+$/u', '', $line);
                $line = trim($line);
                // 去除行内多余说明 (如"关键词：XXX" → "XXX")
                $line = preg_replace('/^(关键词|热词|话题)[:：]\s*/', '', $line);
                // 长度过滤: 2-15字
                $len = mb_strlen($line);
                if ($len < 2 || $len > 15) continue;
                // 跳过包含"请"/"要求"/"输出"等说明性文字的行
                if (preg_match('/(请|要求|输出|格式|示例|严格|注意)/', $line)) continue;
                $hot_words[] = $line;
            }
            $hot_words = array_slice(array_unique($hot_words), 0, $count);
            // 如果清洗后为空, 报错让用户重试
            if (empty($hot_words)) {
                wp_send_json_error(['message' => __('AI返回格式异常, 请重新采集 (已强化提示词, 重试通常可成功)', 'linked3-ai')]);
            }
        } else {
            wp_send_json_error(['message' => __('热词采集需要配置AI API Key (设置→API设置)。当前AI不可用, 拒绝返回假数据。', 'linked3-ai')]);
        }
        } // end else (single source)

        // 持久化到热词库
        $existing = (array) get_option(LINKED3_OPTION_PREFIX . 'hot_keywords', []);
        $merged = array_unique(array_merge($existing, $hot_words));
        update_option(LINKED3_OPTION_PREFIX . 'hot_keywords', array_slice($merged, 0, 500), false);

        wp_send_json_success([
            'source' => $source,
            'source_label' => $source_label,
            'count' => count($hot_words),
            'hot_words' => $hot_words,
            'total_saved' => count($merged),
            'failed_sources' => $extra_meta['failed_sources'] ?? [],
            'message' => __('采集成功: ', 'linked3-ai') . count($hot_words) . '个热词' . (!empty($extra_meta['failed_sources']) ? ' (部分源失败: ' . implode(',', $extra_meta['failed_sources']) . ')' : ''),
        ]);
    }

    public static function ajax_longform_outline() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $section_count = max(3, min(20, intval($_POST['section_count'] ?? 5)));

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3-ai')]);

        // v10.9.1: 真实AI大纲生成 (绞杀假大空模板字符串)
        $prompt = sprintf(
            "你是专业内容策划。请为主题「%s」生成%d个章节的大纲。\n\n要求:\n1. 每行一个章节标题, 不要编号\n2. 章节之间有逻辑递进关系 (引言→核心→实践→总结)\n3. 标题要具体、有信息量, 不要泛泛而谈\n4. 适合长文阅读\n\n直接输出标题列表, 每行一个。",
            $topic, $section_count
        );
        $ai_result = self::call_ai($prompt, 800);

        if (!empty($ai_result)) {
            $titles = array_filter(array_map('trim', explode("\n", $ai_result)));
            $outline = [];
            foreach (array_slice($titles, 0, $section_count) as $i => $title) {
                $outline[] = [
                    'index' => $i,
                    'title' => $title,
                    'status' => 'pending',
                ];
            }
        } else {
            wp_send_json_error(['message' => __('大纲生成需要配置AI API Key。当前AI不可用, 拒绝返回假大纲。', 'linked3-ai')]);
        }

        wp_send_json_success([
            'topic' => $topic,
            'outline' => $outline,
            'section_count' => count($outline),
        ]);
    }

    public static function ajax_longform_section() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $section_title = sanitize_text_field($_POST['section_title'] ?? '');
        $section_index = intval($_POST['section_index'] ?? 0);
        $word_count = max(200, min(2000, intval($_POST['word_count'] ?? 500)));

        if (empty($topic) || empty($section_title)) wp_send_json_error(['message' => __('参数缺失', 'linked3-ai')]);

        @set_time_limit(120);

        // v10.9.2: 真实AI段落生成 (绞杀假大空filler填充)
        // v11.8.0: 贯彻全局require_html格式变量
        $adv_settings = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []), ['require_html' => false]);
        $format_req = !empty($adv_settings['require_html'])
            ? "2. HTML标签格式, 以 <h2> 章节标题开头, 段落用 <p> 标签"
            : "2. Markdown格式, 以 ## 章节标题开头";
        $prompt = sprintf(
            "你是专业写手。请为主题「%s」撰写章节「%s」的正文。\n\n要求:\n1. 字数约%d字\n%s\n3. 内容具体、有信息量, 不要空话套话\n4. 适合长文阅读, 段落分明\n5. 不要使用「赋能/闭环/抓手/底层逻辑」等AI高频词\n\n直接输出正文内容。",
            $topic, $section_title, $word_count, $format_req
        );
        $content = self::call_ai($prompt, $max_tokens = max(800, intval($word_count * 1.5)));

        if (empty($content)) {
            wp_send_json_error(['message' => __('段落生成需要配置AI API Key。当前AI不可用, 拒绝返回假内容。', 'linked3-ai')]);
        }

        wp_send_json_success([
            'section_index' => $section_index,
            'section_title' => $section_title,
            'content' => $content,
            'word_count' => mb_strlen($content),
        ]);
    }

    public static function ajax_csv_batch() {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topics_raw = wp_unslash($_POST['topics'] ?? '');
        $topics = array_filter(array_map('sanitize_text_field', explode("\n", $topics_raw)));
        $topics = array_slice($topics, 0, 50);

        if (empty($topics)) wp_send_json_error(['message' => __('请输入至少一个主题', 'linked3-ai')]);

        // v18复审修复E3 [公理α: H↓ 消除"选了发布却没发布"不确定性]
        // 消费 target_status 参数, 据此 wp_insert_post 落地为草稿/已发布文章
        $target_status = sanitize_key($_POST['target_status'] ?? 'draft');
        if (!in_array($target_status, ['draft', 'publish'], true)) {
            $target_status = 'draft';
        }
        // 关键词列表 (可选, 用于文章标签)
        $keywords_raw = wp_unslash($_POST['keywords_list'] ?? '');
        $keywords_list = array_filter(array_map('sanitize_text_field', explode("\n", $keywords_raw)));

        @set_time_limit(300);

        $results = [];
        $ai_available = class_exists('\\Linked3\\Classes\\Core\\AIDispatcher');
        foreach ($topics as $i => $topic) {
            // v10.9.3: 真实AI批量生成 (绞杀假大空"关于X的文章内容...")
            if (!$ai_available) {
                $results[] = [
                    'topic' => $topic,
                    'success' => false,
                    'word_count' => 0,
                    'content' => '',
                    'error' => 'AI API未配置',
                ];
                continue;
            }
            // v11.8.0: 贯彻全局require_html格式变量
            $adv_csv = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []), ['require_html' => false]);
            $csv_format = !empty($adv_csv['require_html'])
                ? "1. HTML标签格式, 含<h1>标题, 段落用<p>标签"
                : "1. Markdown格式, 含H1标题";
            $prompt = sprintf(
                "请为主题「%s」撰写一篇约800字的文章。要求:\n%s\n2. 内容具体有信息量, 不要空话\n3. 适合博客/公众号发布\n4. 不要使用「赋能/闭环/抓手」等AI高频词\n\n直接输出正文。",
                $topic, $csv_format
            );
            $content = self::call_ai($prompt, 1500);

            $row = [
                'topic' => $topic,
                'success' => !empty($content),
                'word_count' => mb_strlen($content),
                'content' => $content,
                'error' => empty($content) ? 'AI生成失败' : '',
                'post_id' => 0,
                'post_status' => '',
            ];

            // v18复审修复E3: 生成成功则 wp_insert_post 落地
            if (!empty($content)) {
                $post_data = [
                    'post_title'   => $topic,
                    'post_content' => $content,
                    'post_status'  => $target_status,
                    'post_type'    => 'post',
                    'post_author'  => get_current_user_id(),
                ];
                // 关键词作为标签 (若有对应行)
                if (isset($keywords_list[$i]) && !empty($keywords_list[$i])) {
                    $tags = array_filter(array_map('trim', explode('|', $keywords_list[$i])));
                    if (!empty($tags)) {
                        $post_data['tags_input'] = $tags;
                    }
                }
                $post_id = wp_insert_post($post_data, true);
                if (!is_wp_error($post_id) && $post_id > 0) {
                    $row['post_id'] = $post_id;
                    $row['post_status'] = $target_status;
                } else {
                    $row['error'] = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post失败';
                }
            }

            $results[] = $row;
        }

        $saved_count = count(array_filter($results, function($r) { return !empty($r['post_id']); }));
        wp_send_json_success([
            'results' => $results,
            'total' => count($results),
            'saved_count' => $saved_count,
            'target_status' => $target_status,
            'message' => __('批量生成完成: ', 'linked3-ai') . count($results) . '篇, 落地' . $saved_count . '篇' . ($target_status === 'publish' ? '(已发布)' : '(草稿)'),
        ]);
    }

}
