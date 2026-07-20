<?php

declare(strict_types=1);
/**
 * Story Pipeline v8.2.1 — M3 长文本→分镜三层管线 (v10.0.1 优化版)
 *
 * v10.0.1 优化 (基于 /genesis 链路深度公理分析):
 *   公理1: 信息熵减 — parse()结果transient缓存, 相同剧本不重复切分
 *   公理2: 系统降维 — AI辅助切分降级路径, AI失败时回退正则切分
 *
 * 优化项:
 *   1. parse() 加 transient 缓存 (10分钟, 按剧本hash)
 *   2. 新增 ai_assisted_split() AI辅助切分 (可选, 默认关闭)
 *   3. 结构化日志 (切分耗时/场景数/缓存命中)
 *   4. 统一错误码 (E_PARSE_SCRIPT, E_PARSE_STRUCTURE)
 *
 * 兼容性: 保留所有原方法签名, 内部增强
 *
 * @package Linked3\Genesis
 * @since 8.2.0
 * @version 8.2.1 (v10.0.1 优化)
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class StoryPipeline
{
    /** 3.3 EmotionMap 24情绪谱系 (与 Prompt_Assembler::map_emotion_en 对齐) */
    const EMOTION_SPECTRUM = [
        '振奋', '期待', '专注', '温情', '希望', '决心',
        '宁静', '愉悦', '紧张', '焦虑', '悲伤', '愤怒',
        '恐惧', '困惑', '释然', '自豪', '孤独', '怀念',
        '惊讶', '尴尬', '不习惯', '接纳', '勇敢', '平和',
    ];

    /** 3.3.1 情绪→色调联动 (S14) — 5色系, 其它情绪归入"中灰"兜底 */
    const EMOTION_COLOR_MAP = [
        '振奋' => '#FFD700',
        '专注' => '#4682B4',
        '温情' => '#FF8C42',
        '紧张' => '#2F4F4F',
        '希望' => '#98FB98',
    ];

    const COLOR_NEUTRAL = '#808080';

    /** v10.0.1: 缓存过期时间(秒) — 10分钟 */
    const CACHE_TTL = 600;
    const CACHE_PREFIX = 'lk3_story_parse_';

    /**
     * 3.1 长文本导入 — 保留原方法
     */
    public static function import_text(string $content, string $filename = ''): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $text = '';
        $warning = '';

        if ($ext === 'pdf') {
            if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
                return [
                    'text'       => '',
                    'source'     => $filename,
                    'word_count' => 0,
                    'warning'    => __('PDF 文本提取需要 smalot/pdfparser。当前未安装, 暂不支持 PDF 导入。', 'linked3'),
                ];
            }
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $tmp = is_file($content) ? $content : '';
                if ($tmp === '') {
                    return [
                        'text'       => '',
                        'source'     => $filename,
                        'word_count' => 0,
                        'warning'    => __('PDF 导入需要文件路径而非内联内容。', 'linked3'),
                    ];
                }
                $pdf = $parser->parseFile($tmp);
                $text = $pdf->getText();
                if (trim($text) === '') {
                    $warning = __('PDF 提取文本为空, 可能是扫描件。', 'linked3');
                }
            } catch (\Throwable $e) {
                return [
                    'text'       => '',
                    'source'     => $filename,
                    'word_count' => 0,
                    'warning'    => sprintf(__('PDF 解析失败: %s', 'linked3'), $e->getMessage()),
                ];
            }
        } else {
            $text = $content;
            if ($ext === 'md' || $ext === 'markdown') {
                $text = self::strip_markdown($text);
            }
            $text = self::clean_text($text);
        }

        return [
            'text'       => $text,
            'source'     => $filename,
            'word_count' => mb_strlen($text),
            'warning'    => $warning,
        ];
    }

    /**
     * 3.2 剧本拆解 — v10.0.1 加缓存
     *
     * @param string $script      原始剧本
     * @param array  $opts {chapter_marker, split_mode, panel_count, use_cache, use_ai}
     * @return array {scenes, total_shots, total_scenes, source, cached, elapsed_ms}
     */
            public static function parse(string $script, array $opts = []) : mixed { return StoryParser::parse($script, $opts); }

    /**
     * v10.0.1 新增: AI辅助切分 (可选, 默认关闭)
     *
     * @param string $script
     * @param int $target_panels
     * @return array
     */
    private static function ai_assisted_split(string $script, int $target_panels): array
    {
        $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance(); // v19.2.1: 单例
        $prompt = sprintf(
            "你是漫画分镜师。将以下剧本拆分为%d个分镜场景。\n" .
            "返回JSON数组, 每个元素: {location, mood, shots:[{shot,angle,comp,action,dialogue}]}\n" .
            "剧本:\n%s",
            $target_panels,
            mb_substr($script, 0, 4000)
        );

        // v19.50: 绞杀模式 — system_prompt 可被元提示词杠杆增强
        $comic_system = apply_filters('linked3_comic_system_prompt', '你是专业的漫画分镜师。', ['task' => 'comic_split']);
        $messages = [['role' => 'system', 'content' => $comic_system], ['role' => 'user', 'content' => $prompt]];
        $options  = ['temperature' => 0.3, 'max_tokens' => 4000, 'module' => 'comic_split', 'user_id' => get_current_user_id()];
        $config   = ['fallback_providers' => ['deepseek', 'zhipu']];
        try {
            $response = $dispatcher->chat($messages, $options, $config);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('漫画分镜 AI 调用失败: ' . $e->getMessage(), 0, $e);
        }

        $raw = $response['content'] ?? '';
        $json = self::extract_json($raw);
        if ($json === null) {
            throw new \RuntimeException('AI返回非JSON');
        }

        $scenes = [];
        foreach ($json as $s) {
            $shots = [];
            foreach (($s['shots'] ?? []) as $sh) {
                $shots[] = [
                    'shot'     => $sh['shot'] ?? 'medium',
                    'angle'    => $sh['angle'] ?? 'eye_level',
                    'comp'     => $sh['comp'] ?? 'center',
                    'action'   => $sh['action'] ?? '',
                    'dialogue' => $sh['dialogue'] ?? '',
                ];
            }
            $scenes[] = [
                'location' => $s['location'] ?? '未命名场景',
                'mood'     => $s['mood'] ?? 'neutral',
                'shots'    => $shots,
            ];
        }

        return $scenes;
    }

    /**
     * 正则切分 (原parse逻辑提取)
     */
    private static function regex_split(string $script, string $chapter_marker, string $split_mode, int $panel_count): array
    {
        $scenes = [];

        // 按章节切分
        $chapters = self::split_chapters($script, $chapter_marker);

        foreach ($chapters as $chap_idx => $chapter) {
            // 按场景切分
            $scene_blocks = self::split_scenes($chapter, $split_mode);

            foreach ($scene_blocks as $block) {
                $location = self::extract_location($block);
                $mood = self::extract_mood($block);
                $shots = self::extract_shots($block, $panel_count);

                if (!empty($shots)) {
                    $scenes[] = [
                        'location'    => $location,
                        'mood'        => $mood,
                        'shots'       => $shots,
                        'chapter_idx' => $chap_idx,
                        'raw'         => mb_substr($block, 0, 500),
                    ];
                }
            }
        }

        // 兜底: 如果切分结果为空, 整体作为一个场景
        if (empty($scenes) && trim($script) !== '') {
            $scenes[] = [
                'location'    => '默认场景',
                'mood'        => 'neutral',
                'shots'       => self::auto_split_shots($script, $panel_count),
                'chapter_idx' => 0,
                'raw'         => mb_substr($script, 0, 500),
            ];
        }

        return $scenes;
    }

    /**
     * 按章节切分
     */
    private static function split_chapters(string $script, string $marker): array
    {
        if ($marker === 'auto') {
            // 自动检测: 第X章 / Chapter X / 【第X章】
            if (preg_match_all('/第[一二三四五六七八九十百\d]+章|Chapter\s+\d+/i', $script)) {
                $marker = 'chapter';
            } else {
                return [$script]; // 无章节标记, 整体作为一个章节
            }
        }

        if ($marker === 'chapter') {
            $parts = preg_split('/(?=第[一二三四五六七八九十百\d]+章|Chapter\s+\d+)/i', $script, -1, PREG_SPLIT_NO_EMPTY);
            return array_map('trim', $parts);
        }

        return [$script];
    }

    /**
     * 按场景切分
     */
    private static function split_scenes(string $text, string $mode): array
    {
        if ($mode === 'auto' || $mode === 'scene') {
            // 场景标记: 场景/Scene/【场景】/---
            $parts = preg_split('/\n\s*(?:场景|Scene|【场景】|---+)\s*[:：\s]*\n/i', $text);
            $parts = array_filter(array_map('trim', $parts));
            if (count($parts) > 1) return $parts;
        }

        // 按段落切分 (双换行)
        $parts = preg_split('/\n\s*\n/', $text);
        $parts = array_filter(array_map('trim', $parts));
        if (count($parts) > 0) return $parts;

        return [$text];
    }

    /**
     * 提取场景地点
     */
    private static function extract_location(string $block): string
    {
        if (preg_match('/(?:地点|Location|场景)\s*[:：]\s*(.+)/', $block, $m)) {
            return trim($m[1]);
        }
        // 取第一行作为地点
        $lines = explode("\n", trim($block));
        return trim($lines[0]) ?: '未命名场景';
    }

    /**
     * 提取场景情绪
     */
    private static function extract_mood(string $block): string
    {
        if (preg_match('/(?:情绪|氛围|Mood)\s*[:：]\s*(.+)/', $block, $m)) {
            $mood = trim($m[1]);
            // 匹配情绪谱系
            foreach (self::EMOTION_SPECTRUM as $e) {
                if (mb_strpos($mood, $e) !== false) return $e;
            }
            return $mood;
        }
        return 'neutral';
    }

    /**
     * 提取分镜
     */
    private static function extract_shots(string $block, int $target_count): array
    {
        $shots = [];

        // 显式分镜标记: 分镜1/Shot1/1.
        if (preg_match_all('/(?:分镜|Shot)\s*(\d+)\s*[:：]\s*([^\n]+)/i', $block, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $shots[] = [
                    'shot'     => 'medium',
                    'angle'    => 'eye_level',
                    'comp'     => 'center',
                    'action'   => trim($match[2]),
                    'dialogue' => '',
                ];
            }
        }

        // 无显式标记, 按句号切分
        if (empty($shots)) {
            $shots = self::auto_split_shots($block, $target_count);
        }

        return $shots;
    }

    /**
     * 自动按句号切分分镜
     */
    private static function auto_split_shots(string $text, int $target_count): array
    {
        $sentences = preg_split('/[。！？\.\!\?]+/', $text);
        $sentences = array_filter(array_map('trim', $sentences));
        $sentences = array_values($sentences);

        if (empty($sentences)) return [];

        // 如果句子数 > 目标分镜数, 合并相邻句子
        if (count($sentences) > $target_count) {
            $per_shot = ceil(count($sentences) / $target_count);
            $shots = [];
            for ($i = 0; $i < count($sentences); $i += $per_shot) {
                $chunk = array_slice($sentences, $i, $per_shot);
                $shots[] = [
                    'shot'     => 'medium',
                    'angle'    => 'eye_level',
                    'comp'     => 'center',
                    'action'   => implode('。', $chunk) . '。',
                    'dialogue' => '',
                ];
            }
            return $shots;
        }

        // 句子数 <= 目标, 每句一个分镜
        $shots = [];
        foreach ($sentences as $s) {
            $shots[] = [
                'shot'     => 'medium',
                'angle'    => 'eye_level',
                'comp'     => 'center',
                'action'   => $s . '。',
                'dialogue' => '',
            ];
        }
        return $shots;
    }

    /**
     * 3.4 角色提取 — 保留原方法
     */
    public static function extract_characters(string $script): array
    {
        $characters = [];

        // 显式角色声明: 角色:XXX / Character:XXX
        if (preg_match_all('/(?:角色|Character|人物)\s*[:：]\s*([^\n,，。]+)/i', $script, $m)) {
            foreach ($m[1] as $name) {
                $name = trim($name);
                if ($name && !in_array($name, array_column($characters, 'name'))) {
                    $characters[] = ['name' => $name, 'role' => 'unknown', 'source' => 'explicit'];
                }
            }
        }

        // 中文姓名启发式 (2-4字, 常见姓氏开头)
        $surnames = ['林', '陈', '王', '李', '张', '刘', '杨', '黄', '赵', '周', '吴', '徐', '孙', '马', '朱', '胡', '郭', '何', '高', '林', '苏', '沈', '韩', '叶', '谢', '宋', '夏', '钟', '汪', '田', '任', '姜', '方', '石', '姚', '谭', '廖', '邹', '熊', '金', '陆', '郝', '孔', '白', '崔', '康', '毛', '邱', '秦', '江', '史', '顾', '侯', '邵', '孟', '龙', '万', '段', '雷', '钱', '汤', '尹', '黎', '易', '常', '武', '乔', '贺', '赖', '龚', '文'];
        if (preg_match_all('/([' . implode('', $surnames) . '][\x{4e00}-\x{9fa5}]{1,3})/u', $script, $m)) {
            foreach ($m[1] as $name) {
                $name = trim($name);
                // 过滤常见非人名词
                if (in_array($name, ['林中', '林间', '陈旧', '王子', '李子', '张三', '李四'])) continue;
                if ($name && !in_array($name, array_column($characters, 'name'))) {
                    $characters[] = ['name' => $name, 'role' => 'unknown', 'source' => 'heuristic'];
                }
            }
        }

        return $characters;
    }

    /**
     * 3.5 情绪弧线提取 — 保留原方法
     */
    public static function extract_emotion_arc(string $script): array
    {
        $arc = [];
        foreach (self::EMOTION_SPECTRUM as $emotion) {
            $count = mb_substr_count($script, $emotion);
            if ($count > 0) {
                $arc[] = ['emotion' => $emotion, 'count' => $count];
            }
        }
        // 按出现次数降序
        usort($arc, fn($a, $b) => $b['count'] <=> $a['count']);
        return $arc;
    }

    /**
     * 3.6 主题提取 — 保留原方法
     */
    public static function extract_theme(string $script): string
    {
        // 简单关键词匹配
        $themes = [
            '成长' => ['成长', '蜕变', '改变', '成熟'],
            '复仇' => ['复仇', '报复', '仇恨', '血债'],
            '爱情' => ['爱情', '相爱', '恋人', '心动'],
            '友情' => ['友情', '兄弟', '朋友', '义气'],
            '冒险' => ['冒险', '探索', '旅途', '远行'],
            '悬疑' => ['悬疑', '谜团', '真相', '秘密'],
            '热血' => ['热血', '战斗', '拼搏', '不屈'],
        ];

        $scores = [];
        foreach ($themes as $theme => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                $score += mb_substr_count($script, $kw);
            }
            if ($score > 0) $scores[$theme] = $score;
        }

        if (empty($scores)) return '通用';
        arsort($scores);
        return array_key_first($scores);
    }

    // ===== 工具方法 =====

    private static function strip_markdown(string $text): string
    {
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/`(.+?)`/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
        $text = preg_replace('/^[-*+]\s+/m', '', $text);
        $text = preg_replace('/^>\s+/m', '', $text);
        return $text;
    }

    private static function clean_text(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim($text);
    }

    private static function extract_json(string $raw): ?array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }

    /**
     * v10.0.1 新增: 清除切分缓存
     */
    public static function clear_cache(): int
    {
        global $wpdb;
        $count = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '%_transient_' . self::CACHE_PREFIX . '%',
            '%_transient_timeout_' . self::CACHE_PREFIX . '%'
        ));
        return intval($count / 2);
    }
}
