<?php

declare(strict_types=1);
/**
 * Linked3_Batch_Generate_Handler — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class BatchGenerateHandler {
    public function execute(array $payload): array {
        $topic = $payload['topic'];
        if (class_exists('\Linked3\Classes\Scale\AIDispatcher')) {
            try { // v19.3.0: AI 调用容错
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => "请为以下主题生成一篇文章:\n\n" . $topic]],
                ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'batch'],
                ['fallback_providers' => []]
            );
            } catch (\Throwable $e) {
                return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
            }
            return ['topic' => $topic, 'content' => $result['content'] ?? '', 'tokens' => $result['usage']['total_tokens'] ?? 0];
        }
        return ['topic' => $topic, 'content' => '', 'error' => 'AI Dispatcher not available'];
    }
}

// =================================================================
// v5.9.0.5: 性能缓存
// =================================================================
