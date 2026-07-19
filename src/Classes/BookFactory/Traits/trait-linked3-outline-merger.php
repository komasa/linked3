<?php
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

trait Linked3_Outline_Merger {

    /**
     * 合并多次大纲版本
     *
     * @param array $versions 大纲版本数组
     * @return array 最终大纲
     */
    protected function merge_outlines( $versions ) : mixed {
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
     * 解析AI输出的大纲文本为结构化数据
     *
     * @param string $text AI输出的大纲文本
     * @return array {chapters:[{title, sections:[{title}]}]}
     */
    protected function parse_outline_text( $text ) : array {
        $chapters = array();
        $current_chapter = null;

        $lines = explode( "\n", $text );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            // 匹配章节: "第X章 标题" 或 "## 第X章 标题" 或 "X. 标题"
            if ( preg_match( '/^(?:#+\s*)?第[一二三四五六七八九十百\d]+[章节幕课部分].*?\s*(.+)$/u', $line, $m ) ) {
                if ( $current_chapter ) {
                    $chapters[] = $current_chapter;
                }
                $current_chapter = array(
                    'title'    => trim( $m[1] ),
                    'sections' => array(),
                );
            }
            // 匹配小节: "- 标题" 或 "X.X 标题" 或 "第X节 标题"
            elseif ( preg_match( '/^(?:[-*]\s+|\d+[\.\、]\s*)(?:第[一二三四五六七八九十\d]+[节步])?\s*(.+)$/u', $line, $m ) ) {
                if ( $current_chapter ) {
                    $current_chapter['sections'][] = array( 'title' => trim( $m[1] ) );
                }
            }
        }

        if ( $current_chapter ) {
            $chapters[] = $current_chapter;
        }

        return array( 'chapters' => $chapters );
    }

    /**
     * 构建大纲生成提示词
     *
     * @param Linked3_Book_Project_State $state
     * @param array $route
     * @param int $iteration 当前迭代次数(1-based)
     * @param array $previous_versions 之前的大纲版本
     * @return string
     */
    protected function build_outline_prompt( $state, $route, $iteration, $previous_versions ) : mixed {
        $book_title = $state->get( 'book_title' );
        $type_label = Linked3_Type_Mode_Router::get_type_label( $state->get( 'type' ) );
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
