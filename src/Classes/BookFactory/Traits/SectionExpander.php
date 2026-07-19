<?php

declare(strict_types=1);
/**
 * Linked3 Section Expander — 扩写上下文传递
 *
 * 方案: S7 (G1) + S12 (G2上下文缓存)
 * 公理: 前段摘要 + 完整大纲作上下文 → 章节连贯性
 *
 * @package Linked3\BookFactory\Traits
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait SectionExpander {

    /**
     * 构建扩写提示词
     *
     * @param BookProjectState $state
     * @param array $route
     * @param array $chapter
     * @param array $section
     * @param string $context_summary 前段摘要
     * @return string
     */
    protected function build_expand_prompt( $state, $route, $chapter, $section, $context_summary = '' ) : mixed {
        $book_title = $state->get( 'book_title' );
        $type_label = TypeModeRouter::get_type_label( $state->get( 'type' ) );
        $type_unit = $route['type_unit'];

        // 应用路由的提示词覆盖 (S3)
        $expand_override = isset( $route['prompt_overrides']['step4_expand'] )
            ? $route['prompt_overrides']['step4_expand']
            : '请以结构清晰、论据充分的风格扩写';

        $prompt = "开始完善《{$book_title}》这{$type_unit}{$type_label}的小节内容。\n\n";
        $prompt .= "当前章节: {$chapter['title']}\n";
        $prompt .= "当前小节: {$section['title']}\n\n";

        // 注入完整大纲作上下文 (S7)
        $outline = $state->get( 'final_outline' );
        if ( ! empty( $outline['chapters'] ) ) {
            $prompt .= "完整大纲(供参考，确保章节连贯):\n";
            foreach ( $outline['chapters'] as $idx => $ch ) {
                $prompt .= "第" . ( $idx + 1 ) . "章 " . $ch['title'] . "\n";
            }
            $prompt .= "\n";
        }

        // 注入前段摘要 (S7)
        if ( ! empty( $context_summary ) ) {
            $prompt .= "前文摘要(确保内容衔接):\n{$context_summary}\n\n";
        }

        $prompt .= "扩写要求:\n";
        $prompt .= "- {$expand_override}\n";
        $prompt .= "- 字数: 800-1500字\n";
        $prompt .= "- 格式: Markdown\n";
        $prompt .= "- 确保与前后章节逻辑连贯\n\n";
        $prompt .= "请直接输出小节内容(不要标题，不要解释):";

        return $prompt;
    }

    /**
     * 构建上下文摘要 (供下一节使用)
     *
     * @param string $content
     * @param int $max_length
     * @return string
     */
    protected function build_context_summary( $content, $max_length = 80 ) : mixed {
        // 去除Markdown标记
        $plain = wp_strip_all_tags( $content );
        $plain = preg_replace( '/[#*`>\-]/u', '', $plain );
        $plain = preg_replace( '/\s+/u', ' ', $plain );
        $plain = trim( $plain );

        // 截取前N字
        if ( mb_strlen( $plain ) > $max_length ) {
            $plain = mb_substr( $plain, 0, $max_length ) . '...';
        }

        return $plain;
    }

    /**
     * 清洗AI输出的小节内容
     *
     * @param string $content
     * @return string
     */
    protected function sanitize_section_content( $content ) {
        // 复用拼接器的清洗逻辑 (H5约束)
        if ( class_exists( '\Linked3\Classes\BookFactory\Traits\SectionStitcher' ) ) {
            return SectionStitcher::sanitize_markdown( $content );
        }

        // fallback
        $content = wp_strip_all_tags( $content, true );
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );
        return trim( $content );
    }
}
