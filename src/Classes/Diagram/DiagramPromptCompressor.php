<?php

declare(strict_types=1);
/**
 * DiagramPromptCompressor — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramPromptCompressor {
    private int $maxChars = 4500;

    public function compress(string $prompt): string {
        // 策略1: 压缩空白
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        // 策略2: 精简Validation层
        $prompt = preg_replace('/# Validation.*$/s', '# Validation: 13维校验通过', $prompt);
        // 策略3: 压缩旁注
        $prompt = preg_replace('/Side-cards:.*?\.\n/', '', $prompt);
        // 策略4: 如果仍超限, 截断
        if (strlen($prompt) > $this->maxChars) {
            $prompt = substr($prompt, 0, $this->maxChars - 3) . '...';
        }
        return $prompt;
    }

}

// =================================================================
// v6.2.0.6: 关键词提炼5法
// =================================================================
