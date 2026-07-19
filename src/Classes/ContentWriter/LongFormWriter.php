<?php

declare(strict_types=1);
/**
 * 长文分段写作器 — v3.3.0 真正的分段分步撰写
 *
 * 与 aipower 的"单次大 max_tokens"不同,本类实现真正的分段生成:
 *   1. 生成大纲 (JSON 结构:H2 标题 + 目标字数 + 关键点)
 *   2. 用户可编辑大纲 (前端展示)
 *   3. 逐段流式生成,每段独立 AI 调用
 *   4. 段落衔接: 前段摘要 + 完整大纲作为上下文
 *   5. 段落级 regenerate
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class LongFormWriter
{
    /**
     * 阶段 A: 生成大纲
     *
     * @param array $args {topic, keywords, target_word_count, tone, complexity}
     * @return array {outline: [{h2, target_words, key_points, keywords}], total_words}
     */
    public static function generate_outline(array $args) : mixed {
        $topic = $args['topic'] ?? '';
        $keywords = $args['keywords'] ?? '';
        $target_words = (int) ($args['target_word_count'] ?? 2000);
        $tone = $args['tone'] ?? 'professional';
        $complexity = $args['complexity'] ?? 'intermediate';

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        $section_count = $target_words >= 2000 ? 5 : ($target_words >= 1200 ? 4 : 3);
        $words_per_section = (int) ($target_words / $section_count);

        $prompt = sprintf(
            "你是一位专业的内容策略师。为一篇约 %d 字的文章生成结构化大纲。\n\n" .
            "主题: %s\n" .
            "关键词: %s\n" .
            "语气: %s\n" .
            "复杂度: %s\n\n" .
            "要求:\n" .
            "- 生成 %d 个段落(含引言和结论)\n" .
            "- 每段约 %d 字\n" .
            "- 每段包含 2-4 个关键点\n" .
            "- 自然融入关键词\n\n" .
            "只返回 JSON 数组,格式:\n" .
            '[{"h2":"段落标题","target_words":%d,"key_points":["要点1","要点2"],"keywords":["关键词1"]}]' . "\n\n" .
            "不要返回其他内容,不要 markdown 代码块标记。",
            $target_words, $topic, $keywords, $tone, $complexity,
            $section_count, $words_per_section, $words_per_section
        );

        try { // v19.3.0: AI 调用容错
            // v19.50: 绞杀模式 — system_prompt 可被元提示词杠杆增强
            $cw_system = apply_filters('linked3_content_writer_system_prompt', '你是专业的内容写作助手。', ['task' => 'content_writer']);
            $result = AIDispatcher::instance()->chat(
                [['role' => 'system', 'content' => $cw_system], ['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => 0.4, 'max_tokens' => 1500,
                    'module' => 'content_writer',
                    'user_id' => $args['user_id'] ?? get_current_user_id(),
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', '大纲生成失败: ' . $e->getMessage());
        }

        $content = $result['content'] ?? '';
        // 解析 JSON (容忍 ```json 包裹)
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $outline = json_decode($content, true);
        if (!is_array($outline)) {
            // 解析失败,生成默认大纲
            $outline = self::build_default_outline($topic, $target_words);
        }

        return [
            'outline' => $outline,
            'total_words' => array_sum(array_column($outline, 'target_words')),
            'usage' => $result['usage'] ?? [],
        ];
    }

    /**
     * 阶段 C: 生成单个段落
     *
     * @param array $args {section_index, section, outline, topic, keywords, previous_summary, tone}
     * @return array {content, usage}
     */
    public static function generate_section(array $args) : mixed     {
        $section = $args['section'] ?? [];
        $outline = $args['outline'] ?? [];
        $topic = $args['topic'] ?? '';
        $keywords = $args['keywords'] ?? '';
        $previous_summary = $args['previous_summary'] ?? '';
        $tone = $args['tone'] ?? 'professional';
        $section_index = $args['section_index'] ?? 0;
        $style_dna = $args['style_dna'] ?? '';
        $is_last = $section_index === count($outline) - 1;

        $h2 = $section['h2'] ?? '';
        $target_words = (int) ($section['target_words'] ?? 400);
        $key_points = $section['key_points'] ?? [];
        $section_keywords = $section['keywords'] ?? [];

        $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        // 构造完整大纲概览 (让 AI 看到全篇结构)
        $outline_overview = '';
        foreach ($outline as $i => $s) {
            $marker = ($i === $section_index) ? ' ← 当前段落' : '';
            $outline_overview .= sprintf("%d. %s (%d字)%s\n", $i + 1, $s['h2'] ?? '', $s['target_words'] ?? 0, $marker);
        }

        // v17.0: 加载风格DNA
        $style_prompt = '';
        if ($style_dna && class_exists('\Linked3\Classes\ContentWriter\SystemInstructionBuilder')) {
            $style = \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder::get_style_dna($style_dna);
            if (!empty($style['prompt_dna'])) {
                $style_prompt = "\n## 写作风格DNA\n" . $style['prompt_dna'] . "\n";
            }
            if (!empty($style['anti_ai_rules'])) {
                $style_prompt .= "\n## 反AI规则\n";
                foreach ($style['anti_ai_rules'] as $i => $rule) {
                    $style_prompt .= ($i + 1) . '. ' . $rule . "\n";
                }
            }
        }

        // v17.0: 通用反AI规则
        if (!$style_prompt) {
            $style_prompt = "\n## 反AI规则\n1. 禁止'总之/综上所述/首先其次最后'\n2. 段落长度不均匀\n3. 至少一处第一人称'我'\n4. 用具体数字代替模糊词\n";
        }

        // 构造 prompt (v17.0: 风格DNA + 完整性指令)
        $prompt = sprintf(
            "你正在撰写一篇长文,标题: %s\n\n" .
            "完整大纲:\n%s" .
            "关键词: %s\n" .
            "语气: %s\n\n" .
            "%s" .
            "前文摘要(保持连贯性):\n%s\n\n" .
            "现在请撰写第 %d 段: %s\n\n" .
            "要求:\n" .
            "- 约 %d 字\n" .
            "- 覆盖关键点: %s\n" .
            "- 自然融入关键词: %s\n" .
            "- 使用 Markdown 格式,以 ## H2 标题开头\n" .
            "- 与前文保持叙事连贯\n" .
            "- 必须写完本段所有内容,不能中途停止或断句\n" .
            "- 每个句子必须完整,不能出现未写完的句子\n" .
            "%s" .
            "重要:直接输出文章内容,不要输出任何指令、提示词、或'请根据要求'之类的文字。只返回纯文章内容。必须写完整段。",
            $topic,
            $outline_overview,
            $keywords . ($section_keywords ? ',' . implode(',', $section_keywords) : ''),
            $tone,
            $style_prompt,
            $previous_summary ?: '(本文第一段,无前文)',
            $section_index + 1,
            $h2,
            $target_words,
            implode(' / ', $key_points),
            implode(',', $section_keywords),
            $is_last ? "- 作为最后一段,需有总结性结论,必须完整收束\n" : ""
        );

        try { // v19.3.0: AI 调用容错
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => 0.7,
                    // v17.0: 修复输出不完整 — 增大max_tokens到目标字数的3倍 (原为2倍)
                    'max_tokens' => min(4096, max(2000, $target_words * 3)),
                    'module' => 'content_writer',
                    'user_id' => $args['user_id'] ?? get_current_user_id(),
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', '章节生成失败: ' . $e->getMessage());
        }

        // v17.0: 检测输出是否完整 (末尾是否有句号/问号/感叹号)
        $content = $result['content'] ?? '';
        $content = trim($content);
        if ($content && !preg_match('/[。！？\.\!\?]$/', $content)) {
            // 输出不完整,追加省略号提示 (后续可考虑自动续写)
            $content .= "\n\n[本段内容可能因长度限制未完整输出,建议重新生成]";
        }

        return [
            'content' => $content,
            'usage' => $result['usage'] ?? [],
        ];
    }

    /**
     * 段落摘要 (用于下一段的 previous_summary)
     */
    public static function summarize_section($content, $word_count = 80) : mixed {
        $text = wp_strip_all_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        return wp_trim_words($text, $word_count, '...');
    }

    /**
     * 默认大纲 (JSON 解析失败时的兜底)
     */
    private static function build_default_outline($topic, $target_words)
    : array {
        $section_words = (int) ($target_words / 4);
        return [
            ['h2' => '引言: ' . $topic, 'target_words' => $section_words, 'key_points' => ['背景介绍', '问题阐述'], 'keywords' => []],
            ['h2' => '核心内容', 'target_words' => $section_words * 2, 'key_points' => ['主要观点', '实例分析'], 'keywords' => []],
            ['h2' => '实践应用', 'target_words' => $section_words, 'key_points' => ['操作步骤', '注意事项'], 'keywords' => []],
            ['h2' => '总结', 'target_words' => $section_words, 'key_points' => ['要点回顾', '未来展望'], 'keywords' => []],
        ];
    }
}
