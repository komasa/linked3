<?php

declare(strict_types=1);
/**
 * 图片管理器 — AI 图片生成 + 图片站采集 + 媒体库管理。
 *
 * 迁移原版 v2.9.6 的:
 *   - generate_image(): AI 生图(多 Provider: 默认/硅基流动/阿里/自定义)
 *   - fetch_image_urls_from_homepage(): 图片站采集
 *   - image_station_settings: 图片站配置(URL/数量/插入位置/媒体库保存)
 *   - auto_generate_image: 自动生成配图
 *   - img_width/img_height: 图片尺寸
 *   - custom_image_prompt: 自定义图片提示词
 *   - insert_position: 插入位置(开头/中间/结尾)
 *
 * @package Linked3
 * @subpackage Classes\Media
 */

namespace Linked3\Classes\Media;

use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class ImageManager
{
    private $log;

    public function __construct() {
        $this->log = Logger::instance();
    }

    /**
     * 获取图片设置 (原版 image_settings)。
     */
    public function get_settings() : mixed {
        return wp_parse_args(
            (array) get_option(LINKED3_OPTION_PREFIX . 'image_settings', []),
            [
                'auto_generate' => false,
                'provider' => 'openai',       // openai / siliconflow / aliyun / custom
                'api_key' => '',
                'api_url' => '',
                'model' => 'dall-e-3',
                'img_width' => 800,
                'img_height' => 600,
                'insert_position' => 'after_first_h2',  // before_content / after_first_h2 / after_h2 / after_content / random
                'insert_into_content' => true,
                'save_to_media' => true,
                'custom_prompt' => '',         // 自定义提示词模板
                'prompt_source' => 'title',    // title / content
                'image_count' => 1,            // 每篇文章生成几张图
                'image_size' => 'large',       // thumbnail / medium / large / full
                'image_alignment' => 'center', // none / left / center / right
                'featured_image_prompt' => '',  // 特色图片独立提示词
                // 硅基流动专用
                'siliconflow_negative_prompt' => '',
                'siliconflow_image_size' => '640x480',
                'siliconflow_steps' => 20,
                'siliconflow_guidance' => 7.5,
                // 图片站
                'station_url' => '',
                'station_count' => 1,
                // v5.3.3: 图库独立 API Key
                'pexels_api_key' => '',
                'pixabay_api_key' => '',
                'unsplash_api_key' => '',
                'gallery_keyword' => '',
            ]
        );
    }

    /**
     * 保存图片设置。
     */
    public function save_settings(array $input) : mixed     {
        $clean = [
            'auto_generate' => !empty($input['auto_generate']),
            'provider' => sanitize_text_field($input['provider'] ?? 'openai'),
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'api_url' => esc_url_raw($input['api_url'] ?? ''),
            'model' => sanitize_text_field($input['model'] ?? 'dall-e-3'),
            'img_width' => (int) ($input['img_width'] ?? 800),
            'img_height' => (int) ($input['img_height'] ?? 600),
            'insert_position' => sanitize_text_field($input['insert_position'] ?? 'after_first_h2'),
            'insert_into_content' => !empty($input['insert_into_content']),
            'save_to_media' => !empty($input['save_to_media']),
            'custom_prompt' => sanitize_textarea_field($input['custom_prompt'] ?? ''),
            'prompt_source' => sanitize_text_field($input['prompt_source'] ?? 'title'),
            'image_count' => max(1, min(10, (int) ($input['image_count'] ?? 1))),
            'image_alignment' => sanitize_text_field($input['image_alignment'] ?? 'center'),
            'siliconflow_negative_prompt' => sanitize_text_field($input['siliconflow_negative_prompt'] ?? ''),
            'siliconflow_image_size' => sanitize_text_field($input['siliconflow_image_size'] ?? '640x480'),
            'siliconflow_steps' => (int) ($input['siliconflow_steps'] ?? 20),
            'siliconflow_guidance' => (float) ($input['siliconflow_guidance'] ?? 7.5),
            'station_url' => esc_url_raw($input['station_url'] ?? ''),
            'station_count' => (int) ($input['station_count'] ?? 1),
            // v5.3.3: 图库独立 API Key
            'pexels_api_key' => sanitize_text_field($input['pexels_api_key'] ?? ''),
            'pixabay_api_key' => sanitize_text_field($input['pixabay_api_key'] ?? ''),
            'unsplash_api_key' => sanitize_text_field($input['unsplash_api_key'] ?? ''),
            'gallery_keyword' => sanitize_text_field($input['gallery_keyword'] ?? ''),
        ];
        update_option(LINKED3_OPTION_PREFIX . 'image_settings', $clean);
        return $clean;
    }

    /**
     * AI 生成图片 (原版 generate_image)。
     *
     * @param string $prompt 图片提示词
     * @return array{ok:bool, url:string, message:string}
     */
    public function generate_image($prompt) : mixed {
        $settings = $this->get_settings();
        if (!$settings['auto_generate']) {
            return ['ok' => false, 'url' => '', 'message' => __('图片生成未启用', 'linked3-ai')];
        }

        $provider = $settings['provider'];
        $key = $settings['api_key'];
        if (!$key) {
            // 尝试从 provider_keys 读
            $keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            $key_lines = !empty($keys[$provider]) ? array_filter(array_map('trim', explode("\n", $keys[$provider]))) : [];
            $key = $key_lines[0] ?? '';
        }
        if (!$key) {
            return ['ok' => false, 'url' => '', 'message' => __('缺少 API Key', 'linked3-ai')];
        }

        switch ($provider) {
            case 'siliconflow':
                return $this->generate_siliconflow($prompt, $key, $settings);
            case 'aliyun':
                return $this->generate_aliyun($prompt, $key, $settings);
            case 'openai':
            default:
                return $this->generate_openai($prompt, $key, $settings);
        }
    }

    /**
     * OpenAI DALL-E 3 生成图片。
     */
    private function generate_openai($prompt, $key, $settings)
    : array {
        $size = $settings['img_width'] . 'x' . $settings['img_height'];
        // DALL-E 3 只支持 1024x1024, 1792x1024, 1024x1792
        $valid_sizes = ['1024x1024', '1792x1024', '1024x1792'];
        if (!in_array($size, $valid_sizes)) $size = '1024x1024';

        $body = [
            'model' => $settings['model'] ?: 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => 'standard',
        ];
        $resp = SafeRemote::post('https://api.openai.com/v1/images/generations', [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['api.openai.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'url' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) return ['ok' => false, 'url' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['error']['message'] ?? '')];
        $url = $json['data'][0]['url'] ?? '';
        return ['ok' => true, 'url' => $url, 'message' => 'ok'];
    }

    /**
     * 硅基流动生成图片。
     */
    private function generate_siliconflow($prompt, $key, $settings)
    : array {
        $body = [
            'model' => $settings['model'] ?: 'black-forest-labs/FLUX.1-schnell',
            'prompt' => $prompt,
            'image_size' => $settings['siliconflow_image_size'] ?: '1024x1024',
            'batch_size' => 1,
            'num_inference_steps' => (int) ($settings['siliconflow_steps'] ?: 20),
            'guidance_scale' => (float) ($settings['siliconflow_guidance'] ?: 7.5),
        ];
        if (!empty($settings['siliconflow_negative_prompt'])) {
            $body['negative_prompt'] = $settings['siliconflow_negative_prompt'];
        }
        $resp = SafeRemote::post('https://api.siliconflow.cn/v1/images/generations', [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['api.siliconflow.cn'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'url' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) return ['ok' => false, 'url' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['message'] ?? '')];
        $url = $json['images'][0]['url'] ?? '';
        return ['ok' => true, 'url' => $url, 'message' => 'ok'];
    }

    /**
     * 阿里云通义万相生成图片。
     */
    private function generate_aliyun($prompt, $key, $settings)
    : array {
        $body = [
            'model' => $settings['model'] ?: 'flux-schnell',
            'input' => ['prompt' => $prompt],
            'parameters' => [
                'size' => $settings['img_width'] . '*' . $settings['img_height'],
                'n' => 1,
            ],
        ];
        $resp = SafeRemote::post('https://dashscope.aliyuncs.com/api/v1/services/aigc/text2image/image-synthesis', [
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'X-DashScope-Async' => 'enable'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => ['dashscope.aliyuncs.com'],
        ]);
        if (is_wp_error($resp)) return ['ok' => false, 'url' => '', 'message' => $resp->get_error_message()];
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 400) return ['ok' => false, 'url' => '', 'message' => sprintf('HTTP %d: %s', $code, $json['message'] ?? '')];
        // 阿里云是异步的,返回 task_id
        $task_id = $json['output']['task_id'] ?? '';
        if (!$task_id) return ['ok' => false, 'url' => '', 'message' => __('未返回 task_id', 'linked3-ai')];
        // 轮询结果 (简化:等待 10 秒后查询)
        sleep(10);
        $poll = SafeRemote::get("https://dashscope.aliyuncs.com/api/v1/tasks/{$task_id}", [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $key],
            'allowed_hosts' => ['dashscope.aliyuncs.com'],
        ]);
        if (is_wp_error($poll)) return ['ok' => false, 'url' => '', 'message' => __('轮询失败', 'linked3-ai')];
        $poll_json = json_decode(wp_remote_retrieve_body($poll), true);
        $url = $poll_json['output']['results'][0]['url'] ?? '';
        if (!$url) return ['ok' => false, 'url' => '', 'message' => __('图片生成中,请稍后', 'linked3-ai')];
        return ['ok' => true, 'url' => $url, 'message' => 'ok'];
    }

    /**
     * 从图片站首页采集图片 URL (原版 fetch_image_urls_from_homepage)。
     *
     * @param string $station_url 图片站首页 URL
     * @param int $count 采集数量
     * @return string[] 图片 URL 列表
     */
    public function fetch_from_station($station_url, $count = 5) : mixed     {
        if (empty($station_url)) return [];
        $resp = SafeRemote::get($station_url, [
            'timeout' => 15,
            'allowed_hosts' => [wp_parse_url($station_url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) return [];
        $html = wp_remote_retrieve_body($resp);
        // 提取所有图片 URL
        preg_match_all('/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\']/i', $html, $matches);
        $urls = $matches[1] ?? [];
        // 过滤太小的图片 (图标等), 同时将相对 URL 转为绝对 URL
        $base = parse_url($station_url, PHP_URL_SCHEME) . '://' . parse_url($station_url, PHP_URL_HOST);
        $filtered = [];
        foreach ($urls as $url) {
            // 转相对 URL 为绝对 URL
            if (strpos($url, 'http') !== 0) {
                $url = $base . (strpos($url, '/') === 0 ? $url : '/' . $url);
            }
            $filtered[] = $url;
        }
        $urls = $filtered;
        return array_slice($urls, 0, $count);
    }

    /**
     * 下载图片到媒体库 (原版 download_image_to_media_library)。
     *
     * @param string $url 图片 URL
     * @param string $alt alt 文本
     * @param int $post_id 关联文章 ID
     * @return int|\WP_Error 附件 ID
     */
    public function sideload($url, $alt = '', $post_id = 0)
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return $tmp;
        $file_array = [
            'name' => 'linked3-' . wp_generate_password(8, false) . '.jpg',
            'tmp_name' => $tmp,
        ];
        $attach_id = media_handle_sideload($file_array, $post_id, $alt);
        if (is_wp_error($attach_id)) {
            @unlink($tmp);
        } else {
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);
        }
        return $attach_id;
    }

    /**
     * 构建图片提示词 (原版 build_prompt_data)。
     *
     * @param string $title 文章标题
     * @param string $content 文章内容
     * @param array $settings 图片设置
     * @return string
     */
    public function build_prompt($title, $content, $settings = null)
    {
        if (!$settings) $settings = $this->get_settings();
        $source = $settings['prompt_source'] === 'content'
            ? mb_substr(wp_strip_all_tags($content), 0, 200)
            : $title;
        $template = $settings['custom_prompt'] ?: '根据文章内容: {source}，生成高度相关的专业级图片，风格现代简约，高质量。';
        return str_replace('{source}', $source, $template);
    }

    /**
     * 将图片插入文章内容 (原版 insert_image_into_content)。
     *
     * @param string $content 文章内容
     * @param string $image_url 图片 URL
     * @param array $settings {insert_position, img_width, img_height}
     * @return string
     */
    public function insert_into_content($content, $image_url, $settings = null)
    {
        if (!$settings) $settings = $this->get_settings();
        $w = $settings['img_width'] ?: 800;
        $h = $settings['img_height'] ?: 600;
        $alignment = $settings['image_alignment'] ?? 'center';
        $align_class = $alignment === 'none' ? '' : 'align' . $alignment;
        $img_html = '<img src="' . esc_url($image_url) . '" alt="" width="' . $w . '" height="' . $h . '" class="' . $align_class . '" style="max-width:100%;height:auto;" />';

        $position = $settings['insert_position'] ?? 'after_first_h2';

        switch ($position) {
            case 'before_content':
                return $img_html . "\n" . $content;

            case 'after_first_h2':
                // 在第一个 H2 标签后插入
                if (preg_match('/(<h2[^>]*>.*?<\/h2>)/is', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1] + strlen($m[0][0]);
                    return substr($content, 0, $pos) . "\n" . $img_html . substr($content, $pos);
                }
                // 没有 H2 则用 Markdown ## 
                if (preg_match('/(^##\s+.+$)/m', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1] + strlen($m[0][0]);
                    return substr($content, 0, $pos) . "\n\n" . $img_html . substr($content, $pos);
                }
                return $img_html . "\n" . $content;

            case 'after_h2':
                // 在每个 H2 后都插入 (分散图片)
                if (preg_match_all('/(<h2[^>]*>.*?<\/h2>)/is', $content, $m, PREG_OFFSET_CAPTURE)) {
                    $offset = 0;
                    foreach ($m[0] as $match) {
                        $pos = $match[1] + strlen($match[0]) + $offset;
                        $content = substr($content, 0, $pos) . "\n" . $img_html . substr($content, $pos);
                        $offset += strlen($img_html) + 1;
                    }
                    return $content;
                }
                return $img_html . "\n" . $content;

            case 'random':
                // 随机位置插入
                $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (count($paragraphs) > 3) {
                    $insert_idx = rand(1, count($paragraphs) - 2);
                    $paragraphs[$insert_idx] .= "\n" . $img_html . "\n";
                    return implode('', $paragraphs);
                }
                return $img_html . "\n" . $content;

            case 'after_content':
            case 'end':
                return $content . "\n" . $img_html;

            case 'first':
            case 'middle':
            default:
                // 兼容旧值: middle = 第一个 </p> 后
                if ($position === 'middle') {
                    $pos = strpos($content, '</p>');
                    if ($pos !== false) {
                        return substr($content, 0, $pos + 4) . "\n" . $img_html . substr($content, $pos + 4);
                    }
                }
                return $img_html . "\n" . $content;
        }
    }

    /**
     * 记录图片生成日志 (v2.2.0)。
     *
     * @param array $data {provider, model, prompt, url, status, cost}
     * @return void
     */
    public function log_generation(array $data)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_image_logs';
        // 检查表是否存在
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)); // phpcs:ignore
        if (!$exists) return;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (provider, model, prompt, url, status, cost_usd) VALUES (%s, %s, %s, %s, %s, %f)",
            sanitize_text_field($data['provider'] ?? ''), sanitize_text_field($data['model'] ?? ''), substr(sanitize_textarea_field($data['prompt'] ?? ''), 0, 500), esc_url_raw($data['url'] ?? ''), sanitize_text_field($data['status'] ?? 'ok'), (float) ($data['cost'] ?? 0)
        ));
    }

    /**
     * 生成多张图片 (v2.2.0)。
     *
     * @param string $prompt
     * @param int $count
     * @return array{ok:bool, images:array, message:string}
     */
    public function generate_multiple($prompt, $count = 1)
    : array {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $result = $this->generate_image($prompt);
            if ($result['ok'] && !empty($result['url'])) {
                $images[] = $result['url'];
                $this->log_generation([
                    'provider' => $this->get_settings()['provider'],
                    'model' => $this->get_settings()['model'],
                    'prompt' => $prompt,
                    'url' => $result['url'],
                    'status' => 'ok',
                ]);
            }
        }
        if (empty($images)) {
            return ['ok' => false, 'images' => [], 'message' => __('图片生成失败', 'linked3-ai')];
        }
        return ['ok' => true, 'images' => $images, 'message' => sprintf('生成 %d 张图片', count($images))];
    }
}
