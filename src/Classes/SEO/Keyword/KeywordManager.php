<?php

declare(strict_types=1);
/**
 * 关键词管理器 — 热词采集 + 长尾关键词生成 + 批量文章生成。
 *
 * 迁移原版 v2.9.6 的:
 *   - baidu_hotwords (百度/Google/Bing 热词采集)
 *   - generate_tail_keywords (AI 生成长尾关键词)
 *   - tail_keywords 表 (关键词库管理)
 *   - cron post_method=random_keyword (随机关键词生成文章)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Classes\Core\AIDispatcher;
use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class KeywordManager
{
    private $log;

    public function __construct() {
        $this->log = Logger::instance();
    }

    /**
     * 1. 采集百度热词。
     *
     * @param string $seed 种子词(可选,为空则取百度热搜榜)
     * @param int $limit
     * @return string[]
     */
    /**
     * v3.2.0: 多源热词采集 (百度/微博/知乎/B站/头条)
     *
     * @param string $seed 种子词 (空=热搜榜)
     * @param int    $limit 数量
     * @param string $source 采集源 (baidu/weibo/zhihu/bilibili/toutiao/auto)
     * @return array 词数组
     */
    public function fetch_baidu_hotwords($seed = '', $limit = 20, $source = 'auto') : mixed {
        // v5.1.3: auto 模式改为 7 源合一去重 (原 v3.2.0 是 4 源按优先级)
        if ($source === 'auto') {
            if ($seed) {
                // 有种子词:百度搜索建议 + AI 备用
                return $this->fetch_baidu_suggest($seed, $limit);
            }
            // 无种子词:7 源合一去重
            return $this->fetch_all_sources($seed, $limit);
        }
        // 指定源
        if ($source === 'baidu' && $seed) {
            return $this->fetch_baidu_suggest($seed, $limit);
        }
        return $this->fetch_hotwords_from($source, $limit);
    }

    /**
     * v5.1.5: 7 源并发采集 + 合并去重。
     *
     * 用 curl_multi 并发请求 7 个源,总耗时 ≈ 最慢的单源 (3s),
     * 而非串行的 7×3=21s。每源结果缓存 6 小时。
     *
     * @param string $seed  种子词 (空时采集热搜榜)
     * @param int    $limit 最大返回数
     * @return array
     */
    public function fetch_all_sources($seed = '', $limit = 30) : mixed     {
        $sources = ['baidu', 'weibo', 'bilibili', 'toutiao', 'zhihu', 'google', 'sogou'];
        $all = [];

        // Phase 1: 检查缓存,收集需要实际请求的源
        $cached_results = [];
        $uncached_sources = [];
        foreach ($sources as $src) {
            $cache_key = 'linked3_kw_' . $src . '_' . md5($seed);
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                $cached_results = array_merge($cached_results, $cached);
            } else {
                $uncached_sources[] = $src;
            }
        }

        // Phase 2: 并发请求未缓存的源 (curl_multi)
        if (!empty($uncached_sources) && function_exists('curl_multi_init')) {
            $fresh_results = $this->fetch_sources_parallel($uncached_sources, $limit);
            foreach ($fresh_results as $src => $words) {
                if (!empty($words)) {
                    $cache_key = 'linked3_kw_' . $src . '_' . md5($seed);
                    set_transient($cache_key, $words, 6 * HOUR_IN_SECONDS);
                    $cached_results = array_merge($cached_results, $words);
                }
            }
        } elseif (!empty($uncached_sources)) {
            // Fallback: 串行 (curl_multi 不可用时)
            foreach ($uncached_sources as $src) {
                try {
                    $words = $this->fetch_hotwords_from($src, $limit);
                    if (!empty($words)) {
                        $cache_key = 'linked3_kw_' . $src . '_' . md5($seed);
                        set_transient($cache_key, $words, 6 * HOUR_IN_SECONDS);
                        $cached_results = array_merge($cached_results, $words);
                    }
                } catch (\Throwable $e) {
                    // 单源失败不阻塞
                }
            }
        }

        $unique = array_values(array_unique($cached_results));
        // v5.2.3: 过滤掉非热词内容 (URL/HTML标签/过长文本/纯数字)
        $filtered = array_filter($unique, function($word) {
            $word = trim($word);
            if (empty($word)) return false;
            if (mb_strlen($word) < 2) return false;  // 太短
            if (mb_strlen($word) > 50) return false; // 太长 (不是热词)
            if (preg_match('/^https?:\/\//', $word)) return false; // URL
            if (preg_match('/<[^>]+>/', $word)) return false;      // HTML标签
            if (preg_match('/^\d+$/', $word)) return false;        // 纯数字
            if (preg_match('/[a-zA-Z]{20,}/', $word)) return false; // 长英文串(可能是代码)
            return true;
        });
        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * v5.1.5: 用 curl_multi 并发请求多个热词源。
     *
     * @param array $sources  源名数组 ['baidu','weibo',...]
     * @param int   $limit    每源最大条数
     * @return array  ['baidu' => [...], 'weibo' => [...], ...]
     */
    private function fetch_sources_parallel(array $sources, int $limit): array
    {
        // ── FIX v27.1.1: Replace raw cURL with wp_remote_* via Safe_Remote ──
        // Previous code used CURLOPT_SSL_VERIFYPEER=false + FOLLOWLOCATION=true,
        // creating SSRF + MitM vulnerabilities. Now routes through wp_remote_get
        // which respects WP's SSL defaults. Sources are read-only public APIs.
        $results = [];
        foreach ($sources as $src) {
            $url = $this->get_source_url($src);
            if (!$url) continue;

            $resp = wp_remote_get($url, [
                'timeout'             => 3,
                'redirection'         => 2,
                'sslverify'           => true,
                'reject_unsafe_urls'  => true,
                'user-agent'          => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ]);

            if (is_wp_error($resp)) {
                $results[$src] = [];
                continue;
            }
            $body = wp_remote_retrieve_body($resp);
            if (empty($body)) {
                $results[$src] = [];
                continue;
            }
            $results[$src] = $this->parse_source_response($src, $body, $limit);
        }
        return $results;
    }

    /**
     * v5.1.5: 获取各源的 URL。
     */
    private function get_source_url(string $src): string
    {
        $urls = [
            'baidu' => 'https://top.baidu.com/board?tab=realtime',
            'weibo' => 'https://weibo.com/ajax/side/hotSearch',
            'bilibili' => 'https://api.bilibili.com/x/web-interface/ranking/v2?rid=0&type=all',
            'toutiao' => 'https://www.toutiao.com/hot-event/hot-board/?origin=toutiao_pc',
            'zhihu' => 'https://www.zhihu.com/api/v3/feed/topstory/hot-lists/total?limit=50&desktop=true',
            'google' => 'https://trends.google.com/trending/rss?geo=',
            'sogou' => 'https://v2.sohu.com/public-api/yinqing/hot-search-list',
        ];
        return $urls[$src] ?? '';
    }

    /**
     * v5.1.5: 解析各源的 HTTP 响应。
     */
    private function parse_source_response(string $src, string $body, int $limit): array
    {
        $words = [];
        switch ($src) {
            case 'baidu':
                // 百度热搜: HTML 页面,提取标题
                if (preg_match_all('/<div class="c-single-text-ellipsis"[^>]*>([^<]+)<\/div>/', $body, $m)) {
                    $words = array_slice(array_map('trim', $m[1]), 0, $limit);
                }
                break;
            case 'weibo':
                $json = json_decode($body, true);
                if (!empty($json['data']['realtime'])) {
                    foreach ($json['data']['realtime'] as $item) {
                        if (!empty($item['note'])) $words[] = $item['note'];
                        if (count($words) >= $limit) break;
                    }
                }
                break;
            case 'bilibili':
                $json = json_decode($body, true);
                if (!empty($json['data']['list'])) {
                    foreach ($json['data']['list'] as $item) {
                        if (!empty($item['title'])) $words[] = $item['title'];
                        if (count($words) >= $limit) break;
                    }
                }
                break;
            case 'toutiao':
                $json = json_decode($body, true);
                if (!empty($json['data'])) {
                    foreach ($json['data'] as $item) {
                        if (!empty($item['Title'])) $words[] = $item['Title'];
                        if (count($words) >= $limit) break;
                    }
                }
                break;
            case 'zhihu':
                $json = json_decode($body, true);
                if (!empty($json['data'])) {
                    foreach ($json['data'] as $item) {
                        if (!empty($item['target']['title'])) $words[] = $item['target']['title'];
                        if (count($words) >= $limit) break;
                    }
                }
                break;
            case 'google':
                $xml = @simplexml_load_string($body);
                if ($xml) {
                    foreach ($xml->channel->item as $item) {
                        $words[] = (string)$item->title;
                        if (count($words) >= $limit) break;
                    }
                }
                break;
            case 'sogou':
                $json = json_decode($body, true);
                if (!empty($json['data']) && is_array($json['data'])) {
                    foreach ($json['data'] as $item) {
                        if (!empty($item['title'])) $words[] = $item['title'];
                        if (count($words) >= $limit) break;
                    }
                }
                break;
        }
        return $words;
    }

    /**
     * 百度搜索建议 (有种子词)
     */
    private function fetch_baidu_suggest($seed, $limit) : mixed {
        $url = 'https://sp0.baidu.com/5a1Fazu8AA54nxGko9WTAnF6hhy/su?wd=' . urlencode($seed) . '&json=1&p=3';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,  // v3.2.0: 降 timeout
            'allowed_hosts' => ['sp0.baidu.com'],
        ]);
        if (is_wp_error($resp)) return [];
        $body = wp_remote_retrieve_body($resp);
        if (preg_match('/\{.*\}/s', $body, $m)) {
            $json = json_decode($m[0], true);
            if (!empty($json['s'])) {
                return array_slice($json['s'], 0, $limit);
            }
        }
        return [];
    }

    /**
     * v3.2.0: 从指定源采集热搜榜
     */
    private function fetch_hotwords_from($source, $limit) : mixed     {
        $method = 'fetch_' . $source . '_hot';
        if (method_exists($this, $method)) {
            try {
                $words = $this->$method($limit);
                return array_slice($words, 0, $limit);
            } catch (\Throwable $e) {
                $this->log->error('keyword', "采集 {$source} 热词失败: " . $e->getMessage());
                return [];
            }
        }
        return [];
    }

    /**
     * 百度热搜榜
     */
    private function fetch_baidu_hot($limit) : mixed {
        $url = 'https://top.baidu.com/api/board?platform=wise&tab=realtime';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['top.baidu.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        $words = [];
        if (!empty($json['data']['cards'][0]['content'])) {
            foreach ($json['data']['cards'][0]['content'] as $item) {
                if (!empty($item['word'])) $words[] = $item['word'];
            }
        }
        return $words;
    }

    /**
     * v3.2.0: 微博热搜
     */
    private function fetch_weibo_hot($limit) : mixed     {
        $url = 'https://weibo.com/ajax/side/hotSearch';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['weibo.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $words = [];
        if (!empty($json['data']['realtime'])) {
            foreach ($json['data']['realtime'] as $item) {
                if (!empty($item['note'])) $words[] = $item['note'];
            }
        }
        return $words;
    }

    /**
     * v3.2.0: B 站热搜
     */
    private function fetch_bilibili_hot($limit)
    {
        $url = 'https://api.bilibili.com/x/web-interface/search/square?limit=' . $limit;
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['api.bilibili.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $words = [];
        if (!empty($json['data']['trending']['list'])) {
            foreach ($json['data']['trending']['list'] as $item) {
                if (!empty($item['keyword'])) $words[] = $item['keyword'];
            }
        }
        return $words;
    }

    /**
     * v3.2.0: 头条热榜
     */
    private function fetch_toutiao_hot($limit)
    {
        $url = 'https://www.toutiao.com/hot-event/hot-board/?origin=toutiao_pc';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['www.toutiao.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $words = [];
        if (!empty($json['data'])) {
            foreach ($json['data'] as $item) {
                if (!empty($item['Title'])) $words[] = $item['Title'];
            }
        }
        return $words;
    }

    /**
     * v5.1.3: 知乎热榜采集。
     */
    private function fetch_zhihu_hot($limit)
    {
        $url = 'https://www.zhihu.com/api/v3/feed/topstory/hot-lists/total?limit=50&desktop=true';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['www.zhihu.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $words = [];
        if (!empty($json['data'])) {
            foreach ($json['data'] as $item) {
                if (!empty($item['target']['title'])) {
                    $words[] = $item['target']['title'];
                }
            }
        }
        return $words;
    }

    /**
     * v5.1.3: Google Trends 采集 (RSS feed)。
     */
    private function fetch_google_hot($limit)
    {
        // Google Trends RSS for daily trends (geo = worldwide)
        $url = 'https://trends.google.com/trending/rss?geo=';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['trends.google.com'],
            'skip_ssrf' => true,
        ]);
        if (is_wp_error($resp)) return [];
        $body = wp_remote_retrieve_body($resp);
        // Parse RSS XML
        $xml = @simplexml_load_string($body);
        if (!$xml) return [];
        $words = [];
        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            if ($title) $words[] = $title;
            if (count($words) >= $limit) break;
        }
        return $words;
    }

    /**
     * v5.1.3: 搜狗热搜采集。
     */
    private function fetch_sogou_hot($limit)
    {
        $url = 'https://v2.sohu.com/public-api/yinqing/hot-search-list';
        $resp = SafeRemote::get($url, [
            'timeout' => 3,
            'allowed_hosts' => ['v2.sohu.com'],
            'skip_ssrf' => true,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);
        if (is_wp_error($resp)) return [];
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $words = [];
        if (!empty($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $item) {
                if (!empty($item['title'])) {
                    $words[] = $item['title'];
                }
                if (count($words) >= $limit) break;
            }
        }
        return $words;
    }

    /**
     * 2. AI 生成长尾关键词。
     *
     * @param string $seed 种子关键词
     * @param int $count 生成数量
     * @param string $additional_requirements 额外要求
     * @return string[]
     */
    public function generate_tail_keywords($seed, $count = 20, $additional_requirements = '')
    {
        if (empty($seed)) return [];

        // 支持多个种子词(换行分隔),每个都生成
        $seeds = array_filter(array_map('trim', explode("\n", $seed)));
        if (count($seeds) > 1) {
            $all_keywords = [];
            $per_seed = max(3, (int)($count / count($seeds)));
            foreach ($seeds as $s) {
                $kws = $this->generate_tail_keywords_single($s, $per_seed, $additional_requirements);
                $all_keywords = array_merge($all_keywords, $kws);
            }
            return array_slice(array_unique($all_keywords), 0, $count);
        }

        return $this->generate_tail_keywords_single($seeds[0] ?? $seed, $count, $additional_requirements);
    }

    /**
     * 单个种子词生成长尾关键词。
     */
    private function generate_tail_keywords_single($seed, $count, $additional_requirements = '')
    {
        $prompt = sprintf(
            "为种子关键词「%s」生成 %d 个长尾关键词变体。\n" .
            "要求:每个关键词独立一行,不要编号,不要重复。\n" .
            "关键词应覆盖:疑问型(怎么/为什么/多少钱)、对比型(vs/区别)、地域型(北京/上海)、长尾意图型。\n" .
            "%s",
            $seed, $count,
            $additional_requirements ? "额外要求:{$additional_requirements}\n" : ''
        );

        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
                    'temperature' => 0.9,
                    'max_tokens' => 1000,
                    'module' => 'keyword',
                ],
                ['fallback_providers' => []]
            );
            $keywords = array_filter(array_map('trim', explode("\n", $result['content'] ?? '')));
            // 过滤:去掉编号前缀
            $keywords = array_map(function($k) {
                return preg_replace('/^\d+[\.\、\)】\s]+/', '', trim($k));
            }, $keywords);
            return array_slice($keywords, 0, $count);
        } catch (\Throwable $e) {
            $this->log->error('keyword', '长尾关键词生成失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 3. 批量生成文章 (从关键词列表)。
     *
     * @param array $keywords 关键词列表
     * @param array $opts {template_id, post_status, publish_target_id, additional_requirements}
     * @return array{generated:int, errors:array}
     */
    public function batch_generate_from_keywords(array $keywords, array $opts = [])
    : array {
        $post_status = $opts['post_status'] ?? 'draft';
        $additional = $opts['additional_requirements'] ?? '';
        $template_id = (int) ($opts['template_id'] ?? 0);
        $custom_prompt = $opts['custom_prompt'] ?? '';
        $generated = 0;
        $errors = [];

        // 加载模板配置 (v2.6.0: 统一索引规则 — template_id 是 get_all() 的 1-based 索引)
        $tpl_config = [];
        if ($template_id > 0 && class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            $tpl_mgr = new \Linked3\Classes\Templates\TemplateManager();
            $all = $tpl_mgr->get_all();
            $idx = $template_id - 1; // 1-based → 0-based
            if (isset($all[$idx])) {
                $tpl_config = $all[$idx]['config'] ?? [];
            }
        }

        // 读高级设置
        $require_html = false;
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $adv = $enhancer->get_settings();
            $require_html = !empty($adv['require_html']);
        }

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) continue;

            try {
                // 如果有自定义提示词,直接用
                if (!empty($custom_prompt)) {
                    $prompt = str_replace(['{keyword}', '{word_count}'], [$keyword, $tpl_config['word_count'] ?? 1200], $custom_prompt);
                    if (strpos($custom_prompt, '{keyword}') === false) {
                        $prompt = $custom_prompt . "\n\n关键词: " . $keyword;
                    }
                    $result = AIDispatcher::instance()->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'keyword_batch'],
                        ['fallback_providers' => []]
                    );
                } else {
                    // 用 ContentWriter 的 prompt 构造器 + 模板
                    $sys = (new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder())->build([
                        'tone' => $tpl_config['tone'] ?? 'professional',
                        'language' => 'zh-CN',
                        'complexity' => $tpl_config['complexity'] ?? 'intermediate',
                        'seo_focus' => true,
                        'require_html' => $require_html,
                    ]);
                    $user = (new \Linked3\Classes\ContentWriter\Prompt\UserPromptBuilder())->build([
                        'keyword' => $keyword,
                        'word_count' => $tpl_config['word_count'] ?? 1200,
                    ]);
                    // 模板自定义提示词
                    if (!empty($tpl_config['prompt'])) {
                        $user = str_replace(['{keyword}', '{title}', '{word_count}'], [$keyword, $keyword, $tpl_config['word_count'] ?? 1200], $tpl_config['prompt']);
                    }
                    if ($additional) {
                        $user .= "\n\n额外要求:{$additional}";
                    }

                    try { // v19.3.0: AI 调用容错
                        $result = AIDispatcher::instance()->chat(
                            [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                            ['temperature' => $tpl_config['temperature'] ?? 0.7, 'max_tokens' => $tpl_config['max_tokens'] ?? 2000, 'module' => 'keyword_batch'],
                            ['fallback_providers' => []]
                        );
                    } catch (\Throwable $e) {
                        $this->log("关键词批量生成失败 [{$keyword}]: " . $e->getMessage());
                        continue; // 跳过此关键词，继续下一个
                    }
                }

                $content = $result['content'] ?? '';

                // AI 附加备注
                $content = $this->append_ai_suffix($content);

                // AI 摘要
                if (!empty($opts['enable_ai_summary'])) {
                    $content = $this->append_ai_summary($content);
                }

                // 发布
                $post_data = [
                    'post_title' => $keyword,
                    'post_content' => $content,
                    'post_status' => $post_status,
                    'post_type' => 'post',
                    'post_author' => get_current_user_id(),
                ];

                wp_insert_post(wp_slash($post_data), true);
                $generated++;
            } catch (\Throwable $e) {
                $errors[] = $keyword . ': ' . $e->getMessage();
            }
        }

        return ['generated' => $generated, 'errors' => $errors];
    }

    /**
     * AI 附加备注 (原版 enable_random_identifier + AI 标识符后缀)。
     */
    private function append_ai_suffix($content)
    {
        $enabled = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_enabled', 0);
        if (!$enabled) return $content;
        $suffix = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_text', '');
        if (empty($suffix)) {
            $suffix = '本文基于公开技术资料和厂商官方信息整合撰写,以确保信息的时效性与客观性。我们建议您将所有信息作为决策参考,并最终以各云厂商官方页面的最新公告为准。';
        }
        return $content . "\n\n---\n" . $suffix;
    }

    /**
     * AI 摘要 (原版 enable_ai_summary)。
     */
    private function append_ai_summary($content)
    {
        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => '为以下文章生成一段100字以内的摘要:\n\n' . mb_substr($content, 0, 2000)]],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'gpt-4o-mini', 'temperature' => 0.3, 'max_tokens' => 200, 'module' => 'keyword_batch'],
                ['fallback_providers' => []]
            );
            return $content . "\n\n**摘要:** " . trim($result['content'] ?? '');
        } catch (\Throwable $e) {
            return $content;
        }
    }
}
