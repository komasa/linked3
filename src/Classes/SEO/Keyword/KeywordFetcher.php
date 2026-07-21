<?php

declare(strict_types=1);
/**
 * 热词采集器 — 统一单路径采集。
 *
 * 合并原 KeywordManager 双路径分裂症:
 *   - 并行路径 (v5.1.5): get_source_url + wp_remote_get + parse_source_response (switch)
 *   - 串行路径 (v3.2.0): fetch_hotwords_from + 7 个 fetch_*_hot (硬编码 URL)
 * 两条路径对同一源用不同 URL 和解析逻辑,现统一为:
 *   KeywordSourceRegistry 配置表 + fetch_one + parse。
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class KeywordFetcher
{
    /** @var Logger */
    private $log;

    public function __construct(Logger $log)
    {
        $this->log = $log;
    }

    /** 热词采集统一入口。 */
    public function fetch(string $seed = '', int $limit = 20, string $source = 'auto'): array
    {
        if ($source === 'auto') {
            if ($seed) {
                return $this->fetch_baidu_suggest($seed, $limit);
            }
            return $this->fetch_all($seed, $limit);
        }
        if ($source === 'baidu' && $seed) {
            return $this->fetch_baidu_suggest($seed, $limit);
        }
        return $this->fetch_one($source, $limit);
    }

    /** 7 源采集 + 合并去重,每源缓存 6 小时。 */
    public function fetch_all(string $seed = '', int $limit = 30): array
    {
        $sources = KeywordSourceRegistry::all();
        $cached_results = [];
        $uncached = [];

        // Phase 1: 检查缓存
        foreach ($sources as $src) {
            $cache_key = 'linked3_kw_' . $src . '_' . md5($seed);
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                $cached_results = array_merge($cached_results, $cached);
            } else {
                $uncached[] = $src;
            }
        }

        // Phase 2: 逐源请求 (原"并行"实为顺序 wp_remote_get,现统一为 fetch_one)
        foreach ($uncached as $src) {
            try {
                $words = $this->fetch_one($src, $limit);
                if (!empty($words)) {
                    $cache_key = 'linked3_kw_' . $src . '_' . md5($seed);
                    set_transient($cache_key, $words, 6 * HOUR_IN_SECONDS);
                    $cached_results = array_merge($cached_results, $words);
                }
            } catch (\Throwable $e) {
                $this->log->error('keyword', "采集 {$src} 热词失败: " . $e->getMessage());
            }
        }

        $unique   = array_values(array_unique($cached_results));
        $filtered = array_filter($unique, function ($word) {
            $word = trim($word);
            if (empty($word) || mb_strlen($word) < 2 || mb_strlen($word) > 50) return false;
            if (preg_match('/^https?:\/\//', $word)) return false;
            if (preg_match('/<[^>]+>/', $word)) return false;
            if (preg_match('/^\d+$/', $word)) return false;
            if (preg_match('/[a-zA-Z]{20,}/', $word)) return false;
            return true;
        });
        return array_slice(array_values($filtered), 0, $limit);
    }

    /** 采集单个源 (统一路径,替代原 fetch_sources_parallel + 7 个 fetch_*_hot)。 */
    private function fetch_one(string $source, int $limit): array
    {
        $config = KeywordSourceRegistry::get($source);
        if (empty($config)) {
            return [];
        }

        $resp = SafeRemote::get($config['url'], [
            'timeout'       => 3,
            'allowed_hosts' => [$config['host']],
            'skip_ssrf'     => true,
            'headers'       => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        ]);

        if (is_wp_error($resp)) {
            return [];
        }
        $body = wp_remote_retrieve_body($resp);
        if (empty($body)) {
            return [];
        }
        return $this->parse($config, $body, $limit);
    }

    /** 通用解析器 (替代原 parse_source_response switch-case + 7 个 fetch_*_hot 解析)。 */
    private function parse(array $config, string $body, int $limit): array
    {
        $words = [];

        if ($config['parser'] === 'json_path') {
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return [];
            }
            // 导航点分路径 (如 'data.cards.0.content')
            $list = $json;
            foreach (explode('.', $config['path']) as $key) {
                if (!is_array($list) || !isset($list[$key])) {
                    return [];
                }
                $list = $list[$key];
            }
            if (!is_array($list)) {
                return [];
            }
            // 提取字段 (支持嵌套如 'target.title')
            foreach ($list as $item) {
                $val = $item;
                foreach (explode('.', $config['field']) as $fld) {
                    $val = is_array($val) && isset($val[$fld]) ? $val[$fld] : '';
                    if ($val === '') break;
                }
                $word = is_string($val) ? trim($val) : '';
                if ($word !== '') {
                    $words[] = $word;
                }
                if (count($words) >= $limit) {
                    break;
                }
            }
        } elseif ($config['parser'] === 'xml') {
            $xml = @simplexml_load_string($body);
            if (!$xml) {
                return [];
            }
            $items = $xml;
            foreach (explode('.', $config['path']) as $part) {
                $items = $items->$part;
            }
            if (!$items) {
                return [];
            }
            $field = $config['field'];
            foreach ($items as $item) {
                $word = (string)($item->$field ?? '');
                if ($word !== '') {
                    $words[] = $word;
                }
                if (count($words) >= $limit) {
                    break;
                }
            }
        }

        return $words;
    }

    /** 百度搜索建议 (有种子词时使用)。 */
    private function fetch_baidu_suggest(string $seed, int $limit): array
    {
        $url = 'https://sp0.baidu.com/5a1Fazu8AA54nxGko9WTAnF6hhy/su?wd=' . urlencode($seed) . '&json=1&p=3';
        $resp = SafeRemote::get($url, [
            'timeout'       => 3,
            'allowed_hosts' => ['sp0.baidu.com'],
        ]);
        if (is_wp_error($resp)) {
            return [];
        }
        $body = wp_remote_retrieve_body($resp);
        if (preg_match('/\{.*\}/s', $body, $m)) {
            $json = json_decode($m[0], true);
            if (!empty($json['s'])) {
                return array_slice($json['s'], 0, $limit);
            }
        }
        return [];
    }
}
