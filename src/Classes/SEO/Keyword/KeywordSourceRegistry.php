<?php

declare(strict_types=1);
/**
 * 热词源配置表 — 数据驱动的 7 源配置。
 *
 * 替代原 KeywordManager 中的:
 *   - get_source_url() URL 数组
 *   - parse_source_response() switch-case 解析
 *   - 7 个 fetch_*_hot() 方法中的硬编码 URL 和解析
 *
 * 新增源只需在此表添加一行配置。
 *
 * 字段: url, host (SafeRemote 白名单), parser (json_path|xml),
 *       path (JSON 点分路径 / XML 路径), field (支持嵌套如 'target.title')
 *
 * 注: baidu/bilibili 采用 JSON API (原串行路径), 而非 HTML 解析 (原并行路径),
 *     消除"双路径分裂症"。
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

if (!defined('ABSPATH')) {
    exit;
}

final class KeywordSourceRegistry
{
    /** @var array<string,array<string,mixed>> 7 源配置表 */
    private static $sources = [
        'baidu'    => ['url' => 'https://top.baidu.com/api/board?platform=wise&tab=realtime',                       'host' => 'top.baidu.com',      'parser' => 'json_path', 'path' => 'data.cards.0.content', 'field' => 'word'],
        'weibo'    => ['url' => 'https://weibo.com/ajax/side/hotSearch',                                            'host' => 'weibo.com',          'parser' => 'json_path', 'path' => 'data.realtime',         'field' => 'note'],
        'bilibili' => ['url' => 'https://api.bilibili.com/x/web-interface/search/square?limit=50',                   'host' => 'api.bilibili.com',   'parser' => 'json_path', 'path' => 'data.trending.list',    'field' => 'keyword'],
        'toutiao'  => ['url' => 'https://www.toutiao.com/hot-event/hot-board/?origin=toutiao_pc',                    'host' => 'www.toutiao.com',    'parser' => 'json_path', 'path' => 'data',                  'field' => 'Title'],
        'zhihu'    => ['url' => 'https://www.zhihu.com/api/v3/feed/topstory/hot-lists/total?limit=50&desktop=true',   'host' => 'www.zhihu.com',      'parser' => 'json_path', 'path' => 'data',                  'field' => 'target.title'],
        'google'   => ['url' => 'https://trends.google.com/trending/rss?geo=',                                      'host' => 'trends.google.com',  'parser' => 'xml',       'path' => 'channel.item',          'field' => 'title'],
        'sogou'    => ['url' => 'https://v2.sohu.com/public-api/yinqing/hot-search-list',                           'host' => 'v2.sohu.com',        'parser' => 'json_path', 'path' => 'data',                  'field' => 'title'],
    ];

    /** 获取指定源的配置,不存在返回空数组。 */
    public static function get(string $source): array
    {
        return self::$sources[$source] ?? [];
    }

    /** 获取所有源名。 */
    public static function all(): array
    {
        return array_keys(self::$sources);
    }
}
