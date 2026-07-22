<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
class ChartsRenderer
{
    use ScriptFactoryTrait;

    /** @var string 脚本类型 */
    protected $script_type = 'charts';

    public function __construct() {
    }

    protected function compile(array $context): array {
        $topic = $context['topic'] ?? '';
        $style = $context['style'] ?? 'default';
        $platform = $context['platform'] ?? 'xiaohongshu';
        $module_count = max(1, (int)($context['module_count'] ?? $context['panel_count'] ?? 1));

        // v19.3.3: 智能分镜 — 长文按语义边界切分为 N 段，每段对应一镜
        $segments = $this->split_long_article($topic, $module_count);

        return [
            'topic' => $topic,
            'style' => $style,
            'platform' => $platform,
            'bands' => [],
            'style_keywords' => $this->style_config['keywords'] ?? [],
            'seed_refs' => $context['seed_refs'] ?? [],
            'module_count' => count($segments), // 实际分镜数（可能因语义切分微调）
            'segments' => $segments, // v19.3.3: 每镜的内容片段 [{title, summary, content}]
        ];
    }

    public function split_long_article(string $article, int $target_count): array {
        $article = trim($article);
        if (empty($article)) {
            return [['title' => '默认', 'summary' => '', 'content' => '']];
        }

        // 策略1: 按中文序号标题切分（一、二、三 / （一）（二） / 1. 2. 3.）
        $sections = $this->split_by_chinese_headers($article);

        // 策略2: 如果序号切分不足，按段落切分
        if (count($sections) < $target_count) {
            $sections = $this->split_by_paragraphs($article, $target_count);
        }

        // 策略3: 如果段落数仍不足，按长度均分
        if (count($sections) < $target_count) {
            $sections = $this->split_by_length($article, $target_count);
        }

        // 策略4: 如果切分段数 > 目标数，合并相邻段
        if (count($sections) > $target_count && $target_count > 1) {
            $sections = $this->merge_sections($sections, $target_count);
        }

        // 为每段提取标题和摘要
        $result = [];
        foreach ($sections as $idx => $section) {
            $title = $this->extract_section_title($section, $idx);
            $summary = $this->extract_section_summary($section);
            $result[] = [
                'title' => $title,
                'summary' => $summary,
                'content' => $section,
            ];
        }

        // 确保至少返回 1 段
        if (empty($result)) {
            $result = [['title' => mb_substr($article, 0, 20), 'summary' => '', 'content' => $article]];
        }

        return $result;
    }

    public function split_by_chinese_headers(string $article): array {
        // 匹配 "一、" "二、" 或 "（一）" "（二）" 或 "1." "2." 或 "第一部分" 等
        $pattern = '/(?=^(?:[一二三四五六七八九十]+[、．\.]|（[一二三四五六七八九十]+）|[0-9]+[、．\.]|第[一二三四五六七八九十]+[部分章节]))/m';
        $parts = preg_split($pattern, $article, -1, PREG_SPLIT_NO_EMPTY);

        // 过滤空段
        $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => mb_strlen($p) > 10));

        return count($parts) >= 2 ? $parts : [];
    }

    public function split_by_paragraphs(string $article, int $target_count): array {
        $paragraphs = preg_split('/\n\s*\n/', $article, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), fn($p) => mb_strlen($p) > 20));

        if (count($paragraphs) < 2) {
            return [];
        }

        // 如果段落数远多于目标数，合并相邻段落
        if (count($paragraphs) > $target_count * 2) {
            return $this->merge_sections($paragraphs, $target_count);
        }

        return $paragraphs;
    }

}
