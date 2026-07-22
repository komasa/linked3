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

    /**
     * 重新生成指定章节内容。
     *
     * v19.0.2: 从 BookPipelineOrchestrator / BookSectionExpanderService
     * 提取到 Trait, 消除重复代码 + 修复 "Call to undefined method
     * do_regenerate_section" 错误。
     *
     * @param BookProjectState $state          项目状态。
     * @param int              $chapter_index  章索引。
     * @param int              $section_index  节索引。
     * @return array|WP_Error
     */
    protected function do_regenerate_section( $state, int $chapter_index, int $section_index ) {
        $chapters = $state->get( 'chapters', array() );
        $sections = $state->get( 'sections', array() );

        if ( ! isset( $chapters[ $chapter_index ] ) ) {
            return new \WP_Error( 'chapter_not_found', __( '章节不存在', 'linked3-ai' ) );
        }
        if ( ! isset( $chapters[ $chapter_index ]['sections'][ $section_index ] ) ) {
            return new \WP_Error( 'section_not_found', __( '小节不存在', 'linked3-ai' ) );
        }

        $section = $chapters[ $chapter_index ]['sections'][ $section_index ];
        $book_title = $state->get( 'book_title', '' );
        $chapter_title = $chapters[ $chapter_index ]['title'] ?? '';
        $section_title = $section['title'] ?? '';

        // 构建上下文摘要
        $context_summary = '';
        if ( ! empty( $sections[ $chapter_index ] ) && is_array( $sections[ $chapter_index ] ) ) {
            $prev_content = implode( "\n\n", array_slice( $sections[ $chapter_index ], 0, $section_index ) );
            $context_summary = $this->build_context_summary( $prev_content );
        }

        // 调用 AI 重新生成
        $prompt = sprintf(
            "书名: %s\n章节: %s\n小节: %s\n前文摘要: %s\n\n请重新生成这一小节的内容, 保持与上下文连贯, 字数 500-800 字。",
            $book_title,
            $chapter_title,
            $section_title,
            $context_summary
        );

        // 委托给 AI 调用 (通过容器获取 dispatcher)
        try {
            if ( class_exists( '\\Linked3\\Classes\\Core\\AIDispatcher' ) ) {
                $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
                $messages = array( array( 'role' => 'user', 'content' => $prompt ) );
                $options = array( 'temperature' => 0.7, 'max_tokens' => 2048 );
                $config = class_exists( '\\Linked3\\Classes\\Core\\TokenManager' )
                    ? []
                    : array();
                $response = $dispatcher->chat( $messages, $options, $config );
            } else {
                return new \WP_Error( 'ai_unavailable', __( 'AI 引擎未加载', 'linked3-ai' ) );
            }
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'ai_call_failed', $e->getMessage() );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $content = '';
        if ( isset( $response['choices'][0]['message']['content'] ) ) {
            $content = $response['choices'][0]['message']['content'];
        } elseif ( isset( $response['content'] ) ) {
            $content = $response['content'];
        }

        $content = $this->sanitize_section_content( $content );

        // 更新状态
        if ( ! isset( $sections[ $chapter_index ] ) ) {
            $sections[ $chapter_index ] = array();
        }
        $sections[ $chapter_index ][ $section_index ] = $content;
        $state->set( 'sections', $sections );
        $state->save_state();

        return array(
            'chapter_index' => $chapter_index,
            'section_index' => $section_index,
            'content'       => $content,
            'message'       => __( '小节已重新生成', 'linked3-ai' ),
        );
    }
}
