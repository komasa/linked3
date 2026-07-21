<?php

declare(strict_types=1);
/**
 * Linked3 Outline Merger — 大纲迭代合并
 *
 * 方案: S6 (G1) + S12 (G2上下文缓存)
 * 公理: 2-10次大纲迭代 → 合并为最终大纲
 *
 * @package Linked3\BookFactory\Traits
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

trait OutlineMerger {

    /**
     * 合并多次大纲版本
     *
     * @param array $versions 大纲版本数组
     * @return array 最终大纲
     */
    protected function merge_outlines(array $versions) : mixed {
        if ( empty( $versions ) ) {
            return array( 'chapters' => array() );
        }

        if ( count( $versions ) === 1 ) {
            return $versions[0];
        }

        // 策略: 以最后一个版本为基准, 合并前面版本的独有章节
        $final = end( $versions );
        $final_chapter_titles = array_column( $final['chapters'], 'title' );

        foreach ( $versions as $version ) {
            foreach ( $version['chapters'] as $chapter ) {
                if ( ! in_array( $chapter['title'], $final_chapter_titles, true ) ) {
                    $final['chapters'][] = $chapter;
                    $final_chapter_titles[] = $chapter['title'];
                } else {
                    // 已有章节: 合并独有小节
                    $idx = array_search( $chapter['title'], $final_chapter_titles, true );
                    if ( false !== $idx && isset( $final['chapters'][ $idx ]['sections'] ) ) {
                        $existing_section_titles = array_column( $final['chapters'][ $idx ]['sections'], 'title' );
                        foreach ( $chapter['sections'] as $section ) {
                            if ( ! in_array( $section['title'], $existing_section_titles, true ) ) {
                                $final['chapters'][ $idx ]['sections'][] = $section;
                            }
                        }
                    }
                }
            }
        }

        return $final;
    }

    /**
     * 构建大纲生成提示词
     *
     * @param BookProjectState $state
     * @param array $route
     * @param int $iteration 当前迭代次数(1-based)
     * @param array $previous_versions 之前的大纲版本
     * @return string
     */
    protected function build_outline_prompt(BookProjectState $state, array $route, int $iteration, array $previous_versions) : mixed {
        $book_title = $state->get( 'book_title' );
        $type_label = TypeModeRouter::get_type_label( $state->get( 'type' ) );
        $type_unit = $route['type_unit'];

        $max_chapters = isset( $route['yaml_config']['max_chapters'] ) ? $route['yaml_config']['max_chapters'] : 12;
        $max_sections = isset( $route['yaml_config']['max_sections_per_chapter'] ) ? $route['yaml_config']['max_sections_per_chapter'] : 5;

        $prompt = "请为《{$book_title}》这{$type_unit}{$type_label}撰写大纲。\n\n";
        $prompt .= "要求:\n";
        $prompt .= "- 章节数: {$max_chapters}章\n";
        $prompt .= "- 每章小节数: {$max_sections}节\n";
        $prompt .= "- 格式: 第X章 章标题 / 第X节 节标题\n\n";

        if ( $iteration > 1 && ! empty( $previous_versions ) ) {
            $last_version = end( $previous_versions );
            $prompt .= "这是第{$iteration}次迭代，请基于上一版本优化:\n";
            $prompt .= "- 保留优质章节\n";
            $prompt .= "- 合并重复内容\n";
            $prompt .= "- 补充遗漏主题\n";
            $prompt .= "- 调整章节顺序\n\n";
            $prompt .= "上一版本大纲:\n";
            foreach ( $last_version['chapters'] as $idx => $ch ) {
                $prompt .= "第" . ( $idx + 1 ) . "章 " . $ch['title'] . "\n";
                foreach ( $ch['sections'] as $sec ) {
                    $prompt .= "  - " . $sec['title'] . "\n";
                }
            }
            $prompt .= "\n";
        }

        $prompt .= "请输出完整大纲(仅Markdown格式，不要解释):";

        return $prompt;
    }
}
