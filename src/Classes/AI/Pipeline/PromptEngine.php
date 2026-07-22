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

use RuntimeException;

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

}


