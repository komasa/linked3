<?php
/**
 * BookFactory 大纲处理器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 大纲处理 — 大纲合并、智能拆分、章节索引管理。
 *
 * @package Linked3\Classes\BookFactory
 * @since   19.0
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) {
    exit;
}

class Linked3_Book_Outline_Processor
{
    use \Linked3\Classes\BookFactory\Traits\Linked3_Outline_Merger;


        /**
         * 智能拆分大纲为章节列表。
         *
         * 当大纲章节数超过阈值时, 自动拆分为多个子章节,
         * 避免单次 AI 调用 token 超限。
         *
         * @param array $outline 原始大纲。
         * @param int   $max_sections_per_chapter 每章最大节数 (默认 8)。
         * @return array 拆分后的大纲。
         */
        public function smart_split( $outline, $max_sections_per_chapter = 8 ) : mixed {
                if ( empty( $outline ) || ! is_array( $outline ) ) {
                        return $outline;
                }

                $result = array();
                foreach ( $outline as $chapter ) {
                        if ( ! isset( $chapter['sections'] ) || count( $chapter['sections'] ) <= $max_sections_per_chapter ) {
                                $result[] = $chapter;
                                continue;
                        }

                        // 拆分大章节为多个子章节。
                        $sections = $chapter['sections'];
                        $chunks   = array_chunk( $sections, $max_sections_per_chapter );

                        foreach ( $chunks as $idx => $chunk ) {
                                $sub_chapter = $chapter;
                                $sub_chapter['title']    = $chapter['title'] . ' (Part ' . ( $idx + 1 ) . ')';
                                $sub_chapter['sections'] = $chunk;
                                $result[] = $sub_chapter;
                        }
                }

                return $result;
        }

        /**
         * 统计大纲中的总节数。
         *
         * @param array $outline 大纲。
         * @return int 总节数。
         */
        public function count_sections( $outline ) : mixed {
                $total = 0;
                if ( empty( $outline ) || ! is_array( $outline ) ) {
                        return 0;
                }
                foreach ( $outline as $chapter ) {
                        if ( isset( $chapter['sections'] ) && is_array( $chapter['sections'] ) ) {
                                $total += count( $chapter['sections'] );
                        }
                }
                return $total;
        }

        /**
         * 获取指定章节的索引信息。
         *
         * @param array $outline        大纲。
         * @param int   $chapter_index  章索引。
         * @param int   $section_index  节索引。
         * @return array|null 返回 array('chapter'=>..., 'section'=>...) 或 null。
         */
        public function get_section_info( $outline, $chapter_index, $section_index ) : ?array {
                if ( ! isset( $outline[ $chapter_index ] ) ) {
                        return null;
                }
                $chapter = $outline[ $chapter_index ];
                if ( ! isset( $chapter['sections'][ $section_index ] ) ) {
                        return null;
                }
                return array(
                        'chapter' => $chapter,
                        'section' => $chapter['sections'][ $section_index ],
                );
        }
}
