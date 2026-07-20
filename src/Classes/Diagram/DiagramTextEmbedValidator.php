<?php

declare(strict_types=1);
/**
 * DiagramTextEmbedValidator — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramTextEmbedValidator {
    public function validate(array $diagram): array {
        $issues = [];
        $totalTexts = 0;
        $embeddedTexts = 0;
        $lengthIssues = 0;

        foreach ($diagram['bands'] ?? [] as $band) {
            foreach ($band['modules'] ?? [] as $module) {
                // 校验嵌入文字
                foreach ($module['text_embedded'] ?? [] as $text) {
                    $totalTexts++;
                    $embeddedTexts++;
                    $len = mb_strlen($text);
                    if ($len < 2 || $len > 6) {
                        $lengthIssues++;
                        $issues[] = "文字\"{$text}\"长度{$len}不在2-6字范围";
                    }
                }
                // 校验未嵌入的文字 (漂浮文字)
                foreach ($module['floating_text'] ?? [] as $text) {
                    $totalTexts++;
                    $issues[] = "文字\"{$text}\"未嵌入图示";
                }
            }
        }

        $embedRate = $totalTexts > 0 ? $embeddedTexts / $totalTexts : 1;
        return [
            'total_texts' => $totalTexts,
            'embedded' => $embeddedTexts,
            'embed_rate' => round($embedRate * 100, 1) . '%',
            'length_issues' => $lengthIssues,
            'passed' => $embedRate >= 0.95 && $lengthIssues === 0,
            'issues' => $issues,
        ];
    }
}

// =================================================================
// v6.2.0.8: Loop迭代法7步闭环
// =================================================================
