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
     * 构建上下文摘要 (供下一节使用)
     *
     * @param string $content
     * @param int $max_length
     * @return string
     */
    protected function build_context_summary(string $content, int $max_length = 80) : mixed {
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
    protected function sanitize_section_content(string $content): string {
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
