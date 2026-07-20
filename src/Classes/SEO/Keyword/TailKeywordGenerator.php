<?php

declare(strict_types=1);
/**
 * 长尾关键词生成器 — AI 驱动。
 *
 * 从原 KeywordManager::generate_tail_keywords + generate_tail_keywords_single 提取。
 *
 * @package Linked3
 * @subpackage Classes\SEO\Keyword
 */

namespace Linked3\Classes\SEO\Keyword;

use Linked3\Classes\Core\AIDispatcher;
use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class TailKeywordGenerator
{
    /** @var Logger */
    private $log;

    public function __construct(Logger $log)
    {
        $this->log = $log;
    }

    /** 多种子词分发 + AI 生成长尾关键词 (支持换行分隔多种子词)。 */
    public function generate(string $seed, int $count = 20, string $additional_requirements = ''): array
    {
        if (empty($seed)) {
            return [];
        }

        $seeds = array_filter(array_map('trim', explode("\n", $seed)));
        if (count($seeds) > 1) {
            $all = [];
            $per_seed = max(3, (int)($count / count($seeds)));
            foreach ($seeds as $s) {
                $all = array_merge($all, $this->generate_single($s, $per_seed, $additional_requirements));
            }
            return array_slice(array_unique($all), 0, $count);
        }

        return $this->generate_single($seeds[0] ?? $seed, $count, $additional_requirements);
    }

    /** 单个种子词 AI 生成长尾关键词。 */
    private function generate_single(string $seed, int $count, string $additional_requirements = ''): array
    {
        $prompt = sprintf(
            "为种子关键词「%s」生成 %d 个长尾关键词变体。\n" .
            "要求:每个关键词独立一行,不要编号,不要重复。\n" .
            "关键词应覆盖:疑问型(怎么/为什么/多少钱)、对比型(vs/区别)、地域型(北京/上海)、长尾意图型。\n" .
            "%s",
            $seed,
            $count,
            $additional_requirements ? "额外要求:{$additional_requirements}\n" : ''
        );

        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider'    => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
                    'temperature' => 0.9,
                    'max_tokens'  => 1000,
                    'module'      => 'keyword',
                ],
                ['fallback_providers' => []]
            );
            $keywords = array_filter(array_map('trim', explode("\n", $result['content'] ?? '')));
            $keywords = array_map(function ($k) {
                return preg_replace('/^\d+[\.\、\)】\s]+/', '', trim($k));
            }, $keywords);
            return array_slice($keywords, 0, $count);
        } catch (\Throwable $e) {
            $this->log->error('keyword', '长尾关键词生成失败: ' . $e->getMessage());
            return [];
        }
    }
}
