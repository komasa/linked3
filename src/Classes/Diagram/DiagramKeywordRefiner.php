<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Keyword_Refiner — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Keyword_Refiner {
    /**
     * 5种提炼法: 概括/提取/压缩/转化/锚定
     */
    public function refine(string $text): array {
        $keywords = [];

        // 法1: 概括法 — 提取核心名词
        $keywords = array_merge($keywords, $this->extractNouns($text));

        // 法2: 提取法 — 数字+单位
        preg_match_all('/\d+[万千百亿%]*/u', $text, $nums);
        $keywords = array_merge($keywords, $nums[0]);

        // 法3: 压缩法 — 4字黄金长度
        $keywords = array_map(fn($k) => $this->compressTo4($k), $keywords);

        // 法4: 转化法 — 动词转名词
        $keywords = array_map(fn($k) => $this->verbToNoun($k), $keywords);

        // 法5: 锚定法 — 确保独特性
        $keywords = array_unique(array_filter($keywords, fn($k) => mb_strlen($k) >= 2 && mb_strlen($k) <= 6));

        return array_values($keywords);
    }

    private function extractNouns(string $text): array {
        // 简化: 按标点分割取关键短语
        $parts = preg_split('/[，。、；：！？\s]/u', $text);
        return array_filter($parts, fn($p) => mb_strlen($p) >= 2 && mb_strlen($p) <= 8);
    }

    /**
     * 四字黄金长度: 压缩到4字。
     */
    public function compressTo4(string $keyword): string {
        $len = mb_strlen($keyword);
        if ($len <= 4) return $keyword;
        if ($len <= 6) return mb_substr($keyword, 0, 4);
        // 取前2+后2
        return mb_substr($keyword, 0, 2) . mb_substr($keyword, -2);
    }

    private function verbToNoun(string $keyword): string {
        $verbMap = ['实现' => '实现法', '优化' => '优化法', '提升' => '提升法', '管理' => '管理法'];
        return $verbMap[$keyword] ?? $keyword;
    }

    /**
     * 校验四字黄金占比。
     */
    public function checkGoldenRatio(array $keywords): array {
        $total = count($keywords);
        $fourChar = count(array_filter($keywords, fn($k) => mb_strlen($k) === 4));
        $ratio = $total > 0 ? $fourChar / $total : 0;
        return [
            'total' => $total,
            'four_char_count' => $fourChar,
            'golden_ratio' => round($ratio * 100, 1) . '%',
            'passed' => $ratio >= 0.60,
        ];
    }
}

// =================================================================
// v6.2.0.7: 图文咬合量化校验
// =================================================================
