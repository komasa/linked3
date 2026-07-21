<?php

declare(strict_types=1);
/**
 * 视频脚本 JSON 解析 & 标准化 — 从 VideoGenerator 拆分 (v5.4.0)。
 *
 * 职责:
 *   - 解析 AI 返回的 JSON (scenes / frames / outline / single_scene / animation_segment)
 *   - 标准化字段 (中英文兼容, 补全缺失字段)
 *   - 失败兜底 (返回默认结构, 不抛异常)
 *
 * 原 VideoGenerator 中 10 个 private parse_* / normalize_* / default_* 方法 +
 * is_indexed_array 辅助方法整体迁移至此。extract_first_json_object 通过
 * JsonExtractor trait 引入。
 *
 * DRY 改进: 5 处重复的 markdown 代码块去除逻辑提取为 strip_markdown_code_block()。
 *
 * @package Linked3
 * @subpackage Classes\Media
 * @since 27.4.1
 */

namespace Linked3\Classes\Media;

use Linked3\Includes\Traits\JsonExtractor;

if (!defined('ABSPATH')) {
    exit;
}

final class VideoResponseParser
{
    use JsonExtractor;

    // ─── scenes 解析 ──────────────────────────────────────────────────

    /**
     * v5.3.2: 容错 JSON 解析 — 同时支持 {scenes:[...]} 和 [...] 两种格式。
     * 失败返回空数组 (不抛异常, 让调用方决定如何处理)。
     *
     * @param string $raw AI 返回的原始文本
     * @return array 标准化后的 scenes 数组
     */
    public function parse_scenes_json(string $raw): mixed
    {
        if (empty($raw)) return [];

        $text = trim($raw);
        $text = $this->strip_markdown_code_block($text);

        // 2. 尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            // 2a. {"scenes": [...]} 格式
            if (isset($decoded['scenes']) && is_array($decoded['scenes'])) {
                return $this->normalize_scenes($decoded['scenes']);
            }
            // 2b. [...] 直接数组
            if ($this->is_indexed_array($decoded)) {
                return $this->normalize_scenes($decoded);
            }
        }

        // 3. 提取第一个 JSON 对象/数组 (容错: 前后有解释文字)
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $obj = json_decode($m[0], true);
            if (is_array($obj) && isset($obj['scenes']) && is_array($obj['scenes'])) {
                return $this->normalize_scenes($obj['scenes']);
            }
        }
        if (preg_match('/\[[\s\S]*\]/', $text, $m)) {
            $arr = json_decode($m[0], true);
            if (is_array($arr) && $this->is_indexed_array($arr)) {
                return $this->normalize_scenes($arr);
            }
        }

        // 4. 全部失败 → 返回空
        return [];
    }

    /**
     * v5.3.2: 标准化 scenes 数组 (补全缺失字段)。
     *
     * @param array $scenes
     * @return array
     */
    private function normalize_scenes(array $scenes): array
    {
        $result = [];
        $i = 1;
        foreach ($scenes as $s) {
            if (!is_array($s)) continue;
            $result[] = [
                'scene'     => $s['scene'] ?? $i,
                'page'      => $s['page'] ?? sprintf('P%02d', min($i, 9)),
                'visual'    => $s['visual'] ?? $s['画面'] ?? '',
                'narration' => $s['narration'] ?? $s['旁白'] ?? '',
                'text'      => $s['text'] ?? $s['字幕'] ?? '',
                'duration'  => (int) ($s['duration'] ?? $s['时长'] ?? 5),
            ];
            $i++;
        }
        return $result;
    }

    /**
     * 判断是否为索引数组 (非关联数组)。
     */
    private function is_indexed_array(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    // ─── frames 解析 ──────────────────────────────────────────────────

    /**
     * v5.3.3: 解析 frames JSON。
     *
     * @param string $raw
     * @return array
     */
    public function parse_frames_json(string $raw): mixed
    {
        if (empty($raw)) return [];
        $text = trim($raw);
        $text = $this->strip_markdown_code_block($text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) return [];

        $frames = $decoded['frames'] ?? $decoded['scenes'] ?? [];
        if (!is_array($frames)) return [];

        // 标准化
        $out = [];
        $i = 1;
        foreach ($frames as $f) {
            if (!is_array($f)) continue;
            $type = $f['type'] ?? 'scene';
            $out[] = [
                'type'           => $type,
                'index'          => $f['index'] ?? $i,
                'visual_prompt'  => $f['visual_prompt'] ?? $f['visual'] ?? '',
                'script'         => $f['script'] ?? $f['narration'] ?? '',
                'voiceover'      => $f['voiceover'] ?? '',
                'description'    => $f['description'] ?? '',
                'duration'       => (int) ($f['duration'] ?? ($type === 'image' ? 5 : 8)),
            ];
            $i++;
        }
        return $out;
    }

    // ─── outline 解析 ─────────────────────────────────────────────────

    /**
     * v5.3.4: 解析大纲 JSON (容错 + 失败兜底)。
     *
     * @param string $raw
     * @param int    $expected_count
     * @param string $output_mode
     * @return array
     */
    public function parse_outline_json(string $raw, int $expected_count, string $output_mode): mixed
    {
        if (empty($raw)) return $this->default_outline($expected_count, $output_mode);
        $text = trim($raw);
        $text = $this->strip_markdown_code_block($text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        $items = [];
        if (is_array($decoded)) {
            $items = $decoded['outline'] ?? $decoded['scenes'] ?? $decoded;
            if (!is_array($items)) $items = [];
        }

        if (empty($items)) {
            return $this->default_outline($expected_count, $output_mode);
        }

        $out = [];
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                $item = ['title' => (string) $item];
            }
            $out[] = [
                'index'    => (int) ($item['index'] ?? ($i + 1)),
                'page'     => $item['page'] ?? sprintf('P%02d', min($i + 1, 9)),
                'title'    => $item['title'] ?? ('分镜 ' . ($i + 1)),
                'duration' => (int) ($item['duration'] ?? ($output_mode === 'frames' ? 15 : 8)),
            ];
        }
        return $out;
    }

    /**
     * v5.3.4: 默认大纲 (解析失败兜底)。
     */
    private function default_outline(int $expected_count, string $output_mode): array
    {
        $default_pages = ['P01', 'P02', 'P03', 'P05', 'P08', 'P09'];
        $default_titles = ['封面钩子', '问题定义', '原理拆解', '方法步骤', '总结升华', '品牌闭环'];
        $out = [];
        $count = min($expected_count, count($default_pages));
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'index'    => $i + 1,
                'page'     => $default_pages[$i],
                'title'    => $default_titles[$i],
                'duration' => $output_mode === 'frames' ? 15 : 10,
            ];
        }
        return $out;
    }

    // ─── single scene 解析 ────────────────────────────────────────────

    /**
     * v5.3.4: 解析单个 scene JSON (容错 + 兜底)。
     * v5.3.5: 改用平衡括号法提取第一个完整 JSON 对象, 解决贪婪匹配失败。
     *
     * @param string $raw
     * @param array  $outline_item
     * @return array
     */
    public function parse_single_scene_json(string $raw, array $outline_item): mixed
    {
        if (empty($raw)) return $this->default_scene($outline_item);
        $text = trim($raw);
        $text = $this->strip_markdown_code_block($text);

        // 先尝试直接解析
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $this->normalize_scene($decoded, $outline_item);
        }

        // v5.3.5: 平衡括号法提取第一个完整 JSON 对象
        $json_str = $this->extract_first_json_object($text);
        if (!empty($json_str)) {
            $decoded = json_decode($json_str, true);
            if (is_array($decoded)) {
                return $this->normalize_scene($decoded, $outline_item);
            }
        }

        return $this->default_scene($outline_item);
    }

    /**
     * v5.3.5: 标准化 scene (从 AI 返回的任意结构提取字段)。
     */
    private function normalize_scene(array $decoded, array $outline_item): array
    {
        return [
            'scene'     => (int) ($decoded['scene'] ?? $decoded['index'] ?? $outline_item['index']),
            'page'      => $decoded['page'] ?? $outline_item['page'],
            'visual'    => $decoded['visual'] ?? $decoded['画面'] ?? $decoded['description'] ?? '',
            'narration' => $decoded['narration'] ?? $decoded['旁白'] ?? $decoded['script'] ?? '',
            'text'      => $decoded['text'] ?? $decoded['字幕'] ?? $decoded['caption'] ?? '',
            'duration'  => (int) ($decoded['duration'] ?? $decoded['时长'] ?? $outline_item['duration']),
        ];
    }

    /**
     * v5.3.4: 默认 scene (解析失败兜底)。
     */
    private function default_scene(array $outline_item): array
    {
        return [
            'scene'     => $outline_item['index'],
            'page'      => $outline_item['page'],
            'visual'    => $outline_item['title'] . ' 的画面 (AI 解析失败, 请重试)',
            'narration' => $outline_item['title'] . ' 的旁白',
            'text'      => $outline_item['title'],
            'duration'  => $outline_item['duration'],
        ];
    }

    // ─── animation segment 解析 ───────────────────────────────────────

    /**
     * v5.3.7: 解析动画分镜 JSON (容错 + 兜底)。
     * 支持 V14 三层结构: image_prompt (Layer1) + script_prompt (Layer2)
     *
     * @param string $raw
     * @param array  $outline_item
     * @param array  $ctx  V15 上下文 (用于生成兜底默认值)
     * @return array
     */
    public function parse_animation_segment_json(string $raw, array $outline_item, array $ctx): mixed
    {
        $default_image = sprintf(
            "[META:animation_kf%02d] Brand:%s | Signature:%s | Color:%s | Mood:%s | FrameRate:24fps\nA 9:16 vertical animation keyframe, frame %d for %s. %s (AI 解析失败, 请重试)",
            $outline_item['index'], $ctx['brand'], $ctx['signature'], $ctx['color'], $ctx['mood'],
            $outline_item['index'], $ctx['topic'], $outline_item['title']
        );
        $default_script = sprintf(
            "# Script: %s (AI 解析失败, 请重试)\nArc: 起(0-30%%)->转(30-70%%)->合(70-100%%)\nEmotionMap: 待生成\nSoundDesign: 待生成\nKeyframe: K1(0s) K2(%ds) K3(%ds)\nAtmosphere: 待生成",
            $outline_item['title'],
            (int)($outline_item['duration'] * 0.3),
            (int)($outline_item['duration'] * 0.7)
        );

        $default = [
            'index'         => $outline_item['index'],
            'page'          => $outline_item['page'],
            'title'         => $outline_item['title'],
            'image_prompt'  => $default_image,
            'script_prompt' => $default_script,
            'duration'      => $outline_item['duration'],
            'type'          => 'animation',
        ];

        if (empty($raw)) return $default;
        $text = trim($raw);
        $text = $this->strip_markdown_code_block($text);

        // 先尝试直接解析
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            // v5.3.7: 平衡括号法提取第一个完整 JSON 对象
            $json_str = $this->extract_first_json_object($text);
            if (!empty($json_str)) {
                $decoded = json_decode($json_str, true);
            }
        }
        if (!is_array($decoded)) return $default;

        return [
            'index'         => (int) ($decoded['index'] ?? $decoded['section'] ?? $outline_item['index']),
            'page'          => $decoded['page'] ?? $outline_item['page'],
            'title'         => $decoded['title'] ?? $outline_item['title'],
            'image_prompt'  => $decoded['image_prompt'] ?? $decoded['imagePrompt'] ?? $default_image,
            'script_prompt' => $decoded['script_prompt'] ?? $decoded['scriptPrompt'] ?? $default_script,
            'duration'      => (int) ($decoded['duration'] ?? $outline_item['duration']),
            'type'          => 'animation',
        ];
    }

    // ─── 辅助方法 ─────────────────────────────────────────────────────

    /**
     * 去除 markdown 代码块包裹 (提取自 5 处重复代码)。
     *
     * @param string $text
     * @return string
     */
    private function strip_markdown_code_block(string $text): string
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        return $text;
    }
}
