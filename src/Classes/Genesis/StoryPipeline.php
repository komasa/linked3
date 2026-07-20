<?php

declare(strict_types=1);
/**
 * Story Pipeline v8.2.1 — M3 长文本→分镜三层管线
 *
 * v10.0.1 dead code cleanup (2026-07-20):
 *   删除7个死方法 (import_text, ai_assisted_split, regex_split,
 *   extract_characters, extract_emotion_arc, extract_theme, clear_cache)
 *   及9个随之孤立的private helper + 5个死常量。
 *   parse() 委托 StoryParser::parse() 实现。
 *
 * @package Linked3\Genesis
 * @since 8.2.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class StoryPipeline
{
    /**
     * 3.2 剧本拆解 — delegates to StoryParser::parse()
     *
     * @param string $script      原始剧本
     * @param array  $opts {chapter_marker, split_mode, panel_count, use_cache, use_ai}
     * @return array {scenes, total_shots, total_scenes, source, cached, elapsed_ms}
     */
    public static function parse(string $script, array $opts = []): mixed
    {
        return StoryParser::parse($script, $opts);
    }
}
