<?php

declare(strict_types=1);
/**
 * Trait: 平衡括号法提取 JSON — 通用基础设施。
 *
 * v5.4.0 从 VideoGenerator 提取为公共 trait, 与视频领域无关,
 * 可被任何需要从 AI 返回文本中提取 JSON 的类 use。
 *
 * 注意: 原 VideoGenerator 中的 extract_first_json_array() 已被
 * 确认为死代码 (零调用) 并在拆分时删除。本 trait 仅保留
 * extract_first_json_object()。
 *
 * @package Linked3
 * @subpackage Includes\Traits
 * @since 27.4.1
 */

namespace Linked3\Includes\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait JsonExtractor
{
    /**
     * v5.3.5: 用平衡括号法提取第一个完整 JSON 对象。
     *
     * 解决 AI 返回 markdown 包裹 + 前后说明文字导致
     * preg_match 贪婪匹配失败的问题。
     *
     * @param string $text 原始文本
     * @return string 完整的 JSON 对象字符串 (含大括号), 失败返回空字符串
     */
    private function extract_first_json_object(string $text): ?string
    {
        if (empty($text)) return '';
        $start = strpos($text, '{');
        if ($start === false) return '';

        $depth = 0;
        $in_string = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $in_string) {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $in_string = !$in_string;
                continue;
            }
            if ($in_string) continue;

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
        return '';
    }
}
