<?php

declare(strict_types=1);
/**
 * Linked3 Prompt Engine — v5.6.0.5
 *
 * 功能:
 *   - 模板化 Prompt 管理 (变量替换/条件/循环)
 *   - 支持占位符: {topic} {keyword} {word_count} {brand} {mood} ...
 *   - A/B 测试: 同一意图多版本 Prompt 随机/轮转
 *   - Prompt 版本追踪: 记录哪个 Prompt 效果最好
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.5
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class PromptEngine {
    private static ?PromptEngine $instance = null;
    private array $templates = [];
    private array $abVariants = [];
    private array $performance = []; // ['template_id' => ['calls'=>0, 'avg_score'=>0]]

    public static function instance(): PromptEngine {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 注册 Prompt 模板。
     */
    public function register(string $id, string $template, array $defaults = []): void {
        $this->templates[$id] = [
            'template' => $template,
            'defaults' => $defaults,
        ];
    }

    /**
     * 渲染 Prompt (替换占位符)。
     */
    public function render(string $id, array $vars = []): string {
        if (!isset($this->templates[$id])) {
            throw new RuntimeException("Prompt template not found: {$id}");
        }
        $tpl = $this->templates[$id];
        $vars = array_merge($tpl['defaults'], $vars);
        $prompt = $tpl['template'];

        // 替换占位符 {key}
        foreach ($vars as $key => $value) {
            $prompt = str_replace('{' . $key . '}', (string) $value, $prompt);
        }
        // 清理未匹配的占位符
        $prompt = preg_replace('/\{[a-z_]+\}/i', '', $prompt);

        return $prompt;
    }

    /**
     * A/B 测试: 注册同一模板的多个变体。
     */
    public function registerAB(string $experimentId, array $variants): void {
        $this->abVariants[$experimentId] = $variants;
    }

    /**
     * A/B 测试: 获取一个变体 (随机)。
     */
    public function getABVariant(string $experimentId): string {
        if (!isset($this->abVariants[$experimentId])) {
            throw new RuntimeException("A/B experiment not found: {$experimentId}");
        }
        $variants = $this->abVariants[$experimentId];
        return $variants[array_rand($variants)];
    }

    /**
     * 记录 Prompt 性能 (用于优化)。
     */
    public function recordPerformance(string $templateId, float $score): void {
        if (!isset($this->performance[$templateId])) {
            $this->performance[$templateId] = ['calls' => 0, 'total_score' => 0, 'avg_score' => 0];
        }
        $this->performance[$templateId]['calls']++;
        $this->performance[$templateId]['total_score'] += $score;
        $this->performance[$templateId]['avg_score'] =
            $this->performance[$templateId]['total_score'] / $this->performance[$templateId]['calls'];
    }

    /**
     * 获取最佳模板 (按平均分排序)。
     */
    public function getBestTemplate(array $candidateIds): ?string {
        $best = null;
        $bestScore = 0;
        foreach ($candidateIds as $id) {
            $score = $this->performance[$id]['avg_score'] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $id;
            }
        }
        return $best;
    }

    /**
     * 获取性能报告。
     */
    public function getPerformanceReport(): array {
        return $this->performance;
    }
}


