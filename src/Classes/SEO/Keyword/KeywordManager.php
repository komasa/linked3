<?php

declare(strict_types=1);
/**
 * 关键词管理器 (门面) — 委托到 3 个子服务。
 *
 * v5.3.0: 原 677 行 God Class 拆为:
 *   KeywordFetcher + TailKeywordGenerator + KeywordBatchGenerator
 *   + KeywordSourceRegistry (7 源数据驱动配置表)
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class KeywordManager
{
    /** @var KeywordFetcher */
    private $fetcher;
    /** @var TailKeywordGenerator */
    private $generator;
    /** @var KeywordBatchGenerator */
    private $batch;

    public function __construct()
    {
        $log             = Logger::instance();
        $this->fetcher   = new KeywordFetcher($log);
        $this->generator = new TailKeywordGenerator($log);
        $this->batch     = new KeywordBatchGenerator($log);
    }

    /** 采集百度热词 / 多源热词 (v3.2.0 多源, v5.1.3 auto 合一去重)。 */
    public function fetch_baidu_hotwords($seed = '', $limit = 20, $source = 'auto'): mixed
    {
        return $this->fetcher->fetch($seed, $limit, $source);
    }

    /** v5.1.5: 7 源采集 + 合并去重,每源缓存 6 小时。 */
    public function fetch_all_sources($seed = '', $limit = 30): mixed
    {
        return $this->fetcher->fetch_all($seed, $limit);
    }

    /** AI 生成长尾关键词 (支持换行分隔多种子词)。 */
    public function generate_tail_keywords($seed, $count = 20, $additional_requirements = '')
    {
        return $this->generator->generate($seed, $count, $additional_requirements);
    }

    /** 批量生成文章 (从关键词列表)。 */
    public function batch_generate_from_keywords(array $keywords, array $opts = []): array
    {
        return $this->batch->generate($keywords, $opts);
    }
}
