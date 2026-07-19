<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class DashboardConfigAjax
{
    public static function ajax_sync_models()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        if (!$provider) wp_send_json_error(['message' => __('需要 Provider', 'linked3')], 400);

        $keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $api_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);

        // 多 Key 取第一个
        $key = '';
        if (!empty($keys[$provider])) {
            $key_lines = array_filter(array_map('trim', explode("\n", $keys[$provider])));
            $key = $key_lines[0] ?? '';
        }
        if (!$key) wp_send_json_error(['message' => __('请先填写该 Provider 的 API Key', 'linked3')], 400);

        // 获取 API base
        $defaults = [
            'openai' => 'https://api.openai.com/v1',
            'deepseek' => 'https://api.deepseek.com/v1',
            'kimi' => 'https://api.moonshot.cn/v1',
            'qwen' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
            'doubao' => 'https://ark.cn-beijing.volces.com/api/v3',
        ];
        $base = $api_bases[$provider] ?? ($defaults[$provider] ?? '');
        if (!$base) wp_send_json_error(['message' => __('无 API 地址', 'linked3')], 400);

        // 调用 /models
        $url = rtrim($base, '/') . '/models';
        $resp = \Linked3\Includes\Http\SafeRemote::get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Accept' => 'application/json',
            ],
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => __('请求失败: ', 'linked3') . $resp->get_error_message()], 502);
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($resp);
            wp_send_json_error(['message' => sprintf('HTTP %d: %s', $code, substr($body, 0, 200))], 502);
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $models = [];
        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $m) {
                if (isset($m['id'])) {
                    $models[] = (string) $m['id'];
                }
            }
        }
        // 按字母排序
        sort($models);

        // 保存到 option
        $synced = (array) get_option(LINKED3_OPTION_PREFIX . 'synced_models', []);
        $synced[$provider] = $models;
        update_option(LINKED3_OPTION_PREFIX . 'synced_models', $synced);

        wp_send_json_success(['models' => $models, 'count' => count($models)]);
    }

    public static function ajax_save_image_settings()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $mgr = new \Linked3\Classes\Media\ImageManager();
        $input = [
            'auto_generate' => !empty($_POST['auto_generate']),
            'provider' => sanitize_text_field($_POST['provider'] ?? 'openai'),
            'model' => sanitize_text_field($_POST['model'] ?? 'dall-e-3'),
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'api_url' => esc_url_raw($_POST['api_url'] ?? ''),
            'img_width' => (int) ($_POST['img_width'] ?? 800),
            'img_height' => (int) ($_POST['img_height'] ?? 600),
            'insert_position' => sanitize_text_field($_POST['insert_position'] ?? 'after_first_h2'),
            'image_count' => (int) ($_POST['image_count'] ?? 1),
            'image_alignment' => sanitize_text_field($_POST['image_alignment'] ?? 'center'),
            'prompt_source' => sanitize_text_field($_POST['prompt_source'] ?? 'title'),
            'custom_prompt' => sanitize_textarea_field($_POST['custom_prompt'] ?? ''),
            'save_to_media' => !empty($_POST['save_to_media']),
            'station_url' => esc_url_raw($_POST['station_url'] ?? ''),
            'station_count' => (int) ($_POST['station_count'] ?? 1),
            // v5.3.3: 图库独立 API Key
            'pexels_api_key' => sanitize_text_field($_POST['pexels_api_key'] ?? ''),
            'pixabay_api_key' => sanitize_text_field($_POST['pixabay_api_key'] ?? ''),
            'unsplash_api_key' => sanitize_text_field($_POST['unsplash_api_key'] ?? ''),
            'gallery_keyword' => sanitize_text_field($_POST['gallery_keyword'] ?? ''),
        ];
        $mgr->save_settings($input);
        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_test_image_station()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $url = esc_url_raw($_POST['station_url'] ?? '');
        $count = max(1, min(20, (int) ($_POST['count'] ?? 5)));

        if (empty($url)) {
            wp_send_json_error(['message' => __('请填写图片站 URL', 'linked3-ai')]);
        }

        if (!class_exists('\\Linked3\\Classes\\Media\\ImageManager')) {
            wp_send_json_error(['message' => __('图片模块未加载', 'linked3-ai')]);
        }

        $mgr = new \Linked3\Classes\Media\ImageManager();
        $images = $mgr->fetch_from_station($url, $count);

        wp_send_json_success([
            'images' => $images,
            'count' => count($images),
            'station_url' => $url,
        ]);
    }

    public static function ajax_sync_image_models() : mixed {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $provider = sanitize_key($_POST['provider'] ?? 'openai');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        // 读全局 keys
        $keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        if (empty($api_key) && !empty($keys[$provider])) {
            $raw = explode("\n", $keys[$provider]);
            $api_key = trim($raw[0] ?? '');
        }
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('未配置 API Key', 'linked3')]);
        }

        // 读 API base
        $api_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
        $defaults = [
            'openai' => 'https://api.openai.com/v1',
            'siliconflow' => 'https://api.siliconflow.cn/v1',
        ];
        $base = $api_bases[$provider] ?? ($defaults[$provider] ?? 'https://api.openai.com/v1');

        // GET /v1/models 拉取模型列表,过滤出图片模型
        $url = rtrim($base, '/') . '/models';
        $resp = \Linked3\Includes\Http\SafeRemote::get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $api_key],
            'allowed_hosts' => [wp_parse_url($base, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_error(['message' => $resp->get_error_message()]);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $all_models = $body['data'] ?? [];
        // v3.6.0: 如果 API 返回错误,直接报错
        if (isset($body['error'])) {
            wp_send_json_error(['message' => __('API 返回错误: ', 'linked3') . ($body['error']['message'] ?? $body['error'] ?? 'unknown')]);
        }
        if (empty($all_models)) {
            wp_send_json_error(['message' => __('API 返回空模型列表 (可能 Key 无效或无权限)', 'linked3')]);
        }
        // 过滤图片模型 (含 image/dall-e/flux/sd/stable 关键词)
        $img_models = [];
        foreach ($all_models as $m) {
            $id = strtolower($m['id'] ?? '');
            if (preg_match('/(image|dall-e|flux|sd|stable|wanx|tongyi|kolors|seedream)/i', $id)) {
                $img_models[] = $m['id'];
            }
        }
        // 若没匹配到,返回全部模型 (让用户自己选)
        if (empty($img_models)) {
            $img_models = array_map(function($m) { return $m['id'] ?? ''; }, $all_models);
        }
        $img_models = array_filter($img_models);
        sort($img_models);

        wp_send_json_success([
            'models' => array_slice($img_models, 0, 50),
            'count' => count($img_models),
            'total_available' => count($all_models),
            'message' => sprintf('已同步 %d 个模型 (共 %d 个可用)', count($img_models), count($all_models)),
        ]);
    }

    public static function ajax_save_provider_config()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        // default_provider
        $default_provider = sanitize_text_field($_POST['default_provider'] ?? 'openai');
        update_option('linked3_default_provider', $default_provider);

        // key_rotation
        $rotation = sanitize_text_field($_POST['key_rotation'] ?? 'disabled');
        update_option('linked3_key_rotation', $rotation);

        // provider_api_bases (数组)
        $api_bases = $_POST['provider_api_bases'] ?? [];
        if (is_array($api_bases)) {
            $clean_bases = [];
            foreach ($api_bases as $slug => $base) {
                $clean_bases[sanitize_key($slug)] = esc_url_raw($base);
            }
            update_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', $clean_bases);
        }

        // provider_models (数组)
        $models = $_POST['provider_models'] ?? [];
        if (is_array($models)) {
            $clean_models = [];
            foreach ($models as $slug => $model) {
                $clean_models[sanitize_key($slug)] = sanitize_text_field($model);
            }
            update_option(LINKED3_OPTION_PREFIX . 'provider_models', $clean_models);
        }

        // provider_keys (数组,textarea 多行)
        $keys = $_POST['provider_keys'] ?? [];
        $saved_keys_count = 0;
        if (is_array($keys)) {
            $clean_keys = [];
            foreach ($keys as $slug => $raw_keys) {
                $slug_clean = sanitize_key($slug);
                // 保留换行,只做 textarea sanitize
                $clean_keys[$slug_clean] = sanitize_textarea_field($raw_keys);
                if (!empty(trim($clean_keys[$slug_clean]))) {
                    $saved_keys_count++;
                }
            }
            update_option(LINKED3_OPTION_PREFIX . 'provider_keys', $clean_keys);
        }

        // v3.1.1: 返回保存的 key 数量,方便用户验证
        wp_send_json_success([
            'saved' => true,
            'message' => sprintf('Provider 配置已保存 (%d 个 provider 有 key)', $saved_keys_count),
            'saved_keys_count' => $saved_keys_count,
            'default_provider' => $default_provider,
        ]);
    }

    public static function ajax_save_custom_apis()
    : void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $apis_json = wp_unslash($_POST['apis'] ?? '{}');
        $apis = json_decode($apis_json, true);
        if (!is_array($apis)) {
            wp_send_json_error(['message' => __('无效数据', 'linked3')], 400);
        }
        $clean = [];
        foreach ($apis as $id => $api) {
            $id = sanitize_key($id);
            $clean[$id] = [
                'name' => sanitize_text_field($api['name'] ?? ''),
                'url' => esc_url_raw(trim($api['url'] ?? '')),
                'model' => sanitize_text_field($api['model'] ?? ''),
                'key' => sanitize_textarea_field($api['key'] ?? ''),
            ];
        }
        update_option(LINKED3_OPTION_PREFIX . 'custom_apis', $clean);
        wp_send_json_success(['saved' => count($clean)]);
    }

    public static function ajax_save_advanced()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $enhancer = new \Linked3\Classes\Core\AIEnhancer();
        $input = [
            'require_html' => !empty($_POST['require_html']),
            'require_tag' => !empty($_POST['require_tag']),
            'enable_ai_summary' => !empty($_POST['enable_ai_summary']),
            'time_window_enabled' => !empty($_POST['time_window_enabled']),
            'time_window_start' => sanitize_text_field($_POST['time_window_start'] ?? '09:00'),
            'time_window_end' => sanitize_text_field($_POST['time_window_end'] ?? '18:00'),
        ];
        $enhancer->save_settings($input);
        // v3.5.0: 联网搜索开关 (独立 option)
        update_option(LINKED3_OPTION_PREFIX . 'web_search_enabled', !empty($_POST['web_search_enabled']) ? 1 : 0);
        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_save_seo_enhance()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $clean = [
            'interlink_enabled' => !empty($_POST['interlink_enabled']),
            'interlink_strategy' => in_array(sanitize_key($_POST['interlink_strategy'] ?? 'popular'), ['popular', 'recent', 'frequent'], true) ? sanitize_key($_POST['interlink_strategy']) : 'popular',
            'interlink_max_per_post' => max(1, min(20, (int) ($_POST['interlink_max_per_post'] ?? 5))),
            'schema_article' => !empty($_POST['schema_article']),
            'schema_faq' => !empty($_POST['schema_faq']),
            'schema_howto' => !empty($_POST['schema_howto']),
            'schema_product' => !empty($_POST['schema_product']),
            'external_link_nofollow' => !empty($_POST['external_link_nofollow']),
            'external_link_target_blank' => !empty($_POST['external_link_target_blank']),
            'external_link_whitelist' => sanitize_textarea_field($_POST['external_link_whitelist'] ?? ''),
        ];
        update_option(LINKED3_OPTION_PREFIX . 'seo_enhance', $clean);
        wp_send_json_success(['saved' => true, 'message' => __('SEO 增强配置已保存', 'linked3')]);
    }

    public static function ajax_regen_llms_txt()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        if (!class_exists('\\Linked3\\Classes\\SEO\\GEOEnhancer')) {
            wp_send_json_error(['message' => __('GEO 模块未加载', 'linked3')]);
        }

        $content = \Linked3\Classes\SEO\GEOEnhancer::generate_llms_txt();
        update_option(LINKED3_OPTION_PREFIX . 'llms_txt_content', $content);
        update_option(LINKED3_OPTION_PREFIX . 'llms_txt_last_gen', time());

        wp_send_json_success(['message' => __('llms.txt 已重新生成', 'linked3'), 'url' => home_url('/llms.txt')]);
    }

    public static function ajax_save_ai_search_keys()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        update_option(LINKED3_OPTION_PREFIX . 'perplexity_api_key', sanitize_text_field($_POST['perplexity_api_key'] ?? ''));
        update_option(LINKED3_OPTION_PREFIX . 'binggpt_api_key', sanitize_text_field($_POST['binggpt_api_key'] ?? ''));
        wp_send_json_success(['saved' => true, 'message' => __('AI 搜索引擎 API 已保存', 'linked3')]);
    }

    public static function ajax_save_ai_suffix()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $suffix = wp_unslash($_POST['suffix'] ?? '');
        $enabled = !empty($_POST['enabled']);
        update_option(LINKED3_OPTION_PREFIX . 'ai_suffix_enabled', $enabled ? 1 : 0);
        update_option(LINKED3_OPTION_PREFIX . 'ai_suffix_text', sanitize_textarea_field($suffix));
        wp_send_json_success(['saved' => true]);
    }

    public static function ajax_kw_save_library()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $hot = wp_unslash($_POST['hot_list'] ?? '');
        $tail = wp_unslash($_POST['tail_list'] ?? '');
        // 保存为数组 (每行一个词)
        $hot_arr = array_filter(array_map('trim', explode("\n", $hot)));
        $tail_arr = array_filter(array_map('trim', explode("\n", $tail)));
        update_option(LINKED3_OPTION_PREFIX . 'kw_hot_library', $hot_arr);
        update_option(LINKED3_OPTION_PREFIX . 'kw_tail_library', $tail_arr);
        wp_send_json_success(['saved' => true, 'hot_count' => count($hot_arr), 'tail_count' => count($tail_arr)]);
    }

    public static function ajax_kw_cron_enable()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $frequency = sanitize_key($_POST['frequency'] ?? 'twicedaily');
        $count = (int) ($_POST['count'] ?? 30);
        update_option(LINKED3_OPTION_PREFIX . 'kw_cron_frequency', $frequency);
        update_option(LINKED3_OPTION_PREFIX . 'kw_cron_count', $count);
        update_option(LINKED3_OPTION_PREFIX . 'kw_cron_enabled', 1);

        // 注册 WP cron
        if (!wp_next_scheduled('linked3_kw_cron_run')) {
            wp_schedule_event(time(), $frequency, 'linked3_kw_cron_run');
        }
        wp_send_json_success(['enabled' => true]);
    }

    public static function ajax_kw_cron_disable()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        update_option(LINKED3_OPTION_PREFIX . 'kw_cron_enabled', 0);
        wp_clear_scheduled_hook('linked3_kw_cron_run');
        wp_send_json_success(['enabled' => false]);
    }

    public static function ajax_kw_cron_status()
    : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $enabled = (bool) get_option(LINKED3_OPTION_PREFIX . 'kw_cron_enabled', 0);
        $frequency = get_option(LINKED3_OPTION_PREFIX . 'kw_cron_frequency', 'twicedaily');
        $count = (int) get_option(LINKED3_OPTION_PREFIX . 'kw_cron_count', 30);
        $next_run = wp_next_scheduled('linked3_kw_cron_run');
        wp_send_json_success([
            'enabled' => $enabled,
            'frequency' => $frequency,
            'count' => $count,
            'next_run' => $next_run ? gmdate('Y-m-d H:i:s', $next_run) : '—',
        ]);
    }

}
