<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

// Ensure the trait is loaded before the class declaration.
if (!trait_exists(__NAMESPACE__ . '\\ScriptFactoryTrait')) {
    require_once __DIR__ . '/ScriptFactoryTrait.php';
}

class ChartsRenderer
{
    use ScriptFactoryTrait;

    public function __construct() {
        $this->script_type = 'charts';
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

    private function split_by_length(string $article, int $target_count): array {
        $total_len = mb_strlen($article);
        $chunk_size = (int)ceil($total_len / $target_count);

        $sections = [];
        for ($i = 0; $i < $total_len; $i += $chunk_size) {
            $chunk = mb_substr($article, $i, $chunk_size);
            if (mb_strlen(trim($chunk)) > 0) {
                $sections[] = trim($chunk);
            }
        }

        return $sections;
    }

    private function merge_sections(array $sections, int $target_count): array {
        if (count($sections) <= $target_count) {
            return $sections;
        }

        $result = [];
        $per_group = (int)ceil(count($sections) / $target_count);
        for ($i = 0; $i < count($sections); $i += $per_group) {
            $group = array_slice($sections, $i, $per_group);
            $result[] = implode("\n\n", $group);
        }

        return $result;
    }

    private function extract_section_title(string $section, int $idx): string {
        $first_line = trim(explode("\n", $section)[0]);
        $first_line = preg_replace('/^(?:[一二三四五六七八九十]+[、．\.]|（[一二三四五六七八九十]+）|[0-9]+[、．\.]|第[一二三四五六七八九十]+[部分章节])\s*/', '', $first_line);
        $title = mb_substr($first_line, 0, 30);
        return $title ?: ('第' . ($idx + 1) . '部分');
    }

    private function extract_section_summary(string $section): string {
        $clean = trim(preg_replace('/\s+/', ' ', $section));
        $snippet = mb_substr($clean, 0, 120);
        if (preg_match('/^(.+?[。！？\.\!\?])/u', $snippet, $m)) {
            return $m[1];
        }
        return $snippet;
    }

    /**
     * v27.6.21-fix: Implement abstract project() from ScriptFactoryTrait.
     *
     * Projects the compiled IR into the final charts script format.
     * The IR contains segments from compile(); this method formats them
     * into the output structure expected by the charts pipeline.
     *
     * @param array $ir Intermediate representation from compile().
     * @return array Final projected script.
     */
    protected function project(array $ir): array {
        $segments = $ir['segments'] ?? [];
        $panels = [];

        foreach ($segments as $idx => $seg) {
            $panels[] = [
                'panel_id'   => 'P' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'title'      => $seg['title'] ?? ('Panel ' . ($idx + 1)),
                'summary'    => $seg['summary'] ?? '',
                'content'    => $seg['content'] ?? '',
                'style_hint' => $ir['style'] ?? 'default',
                'platform'   => $ir['platform'] ?? 'xiaohongshu',
            ];
        }

        return [
            'topic'        => $ir['topic'] ?? '',
            'style'        => $ir['style'] ?? 'default',
            'platform'     => $ir['platform'] ?? 'xiaohongshu',
            'module_count' => $ir['module_count'] ?? count($panels),
            'panels'       => $panels,
            'bands'        => $ir['bands'] ?? [],
            'style_keywords' => $ir['style_keywords'] ?? [],
            'seed_refs'    => $ir['seed_refs'] ?? [],
        ];
    }

}
