<?php

declare(strict_types=1);
/**
 * Linked3 Section Stitcher — 段落拼接器
 *
 * 方案: S4 (G1) + S13 (G2增量拼接) + H5约束(Markdown清洗)
 * 公理: N章×M节 → 完整书稿Markdown+HTML
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) exit;

class SectionStitcher {

    /**
     * 拼接完整书稿
     *
     * @param array  $chapters    章节列表
     * @param array  $template    输出模板配置
     * @param string $book_title  书名
     * @param array  $meta        元数据
     * @return array {markdown, html}
     */
    public static function stitch( $chapters, $template, $book_title, $meta = array() ) : array {
        $meta = wp_parse_args( $meta, array(
            'author'    => '',
            'date'      => current_time( 'Y-m-d' ),
            'preface'   => '',
            'afterword' => '',
        ) );

        $md   = self::build_markdown( $chapters, $template, $book_title, $meta );
        $html = self::markdown_to_html( $md );

        return array(
            'markdown' => $md,
            'html'     => $html,
        );
    }

    /**
     * 增量拼接单章 (S13 — 审阅后仅重拼受影响章节)
     *
     * @param array  $chapter     单章数据
     * @param array  $template    输出模板配置
     * @param int    $chapter_index 章节序号(1-based)
     * @return string Markdown
     */
    public static function stitch_chapter( $chapter, $template, $chapter_index ) : mixed {
        $prefix = isset( $template['chapter_prefix'] ) ? $template['chapter_prefix'] : '第';
        $suffix = isset( $template['chapter_suffix'] ) ? $template['chapter_suffix'] : '章';
        $number_format = isset( $template['number_format'] ) ? $template['number_format'] : 'chinese';

        $chapter_num = $number_format === 'arabic' ? $chapter_index : self::number_to_chinese( $chapter_index );

        $md = "\n\n## {$prefix}{$chapter_num}{$suffix} {$chapter['title']}\n\n";

        if ( isset( $chapter['sections'] ) ) {
            $sec_prefix = isset( $template['section_prefix'] ) ? $template['section_prefix'] : '第';
            $sec_suffix = isset( $template['section_suffix'] ) ? $template['section_suffix'] : '节';

            foreach ( $chapter['sections'] as $sec_idx => $section ) {
                $sec_num = $number_format === 'arabic' ? ( $sec_idx + 1 ) : self::number_to_chinese( $sec_idx + 1 );

                if ( ! empty( $sec_prefix ) ) {
                    $md .= "### {$sec_prefix}{$sec_num}{$sec_suffix} {$section['title']}\n\n";
                } else {
                    $md .= "### {$section['title']}\n\n";
                }

                if ( isset( $section['content'] ) ) {
                    $md .= self::sanitize_markdown( $section['content'] ) . "\n\n";
                }
            }
        }

        return $md;
    }

    /**
     * 构建完整 Markdown
     *
     * @param array  $chapters
     * @param array  $template
     * @param string $book_title
     * @param array  $meta
     * @return string
     */
    private static function build_markdown( $chapters, $template, $book_title, $meta ) : mixed {
        $md = '';

        // 标题
        $md .= "# {$book_title}\n\n";

        // 元数据
        if ( ! empty( $meta['author'] ) ) {
            $md .= "> 作者: {$meta['author']}\n";
        }
        $md .= "> 生成日期: {$meta['date']}\n";
        $md .= "> 由 Linked3 AI 写书工厂生成\n\n";
        $md .= "---\n\n";

        // 目录
        if ( ! empty( $template['include_toc'] ) ) {
            $md .= "## 目录\n\n";
            $prefix = isset( $template['chapter_prefix'] ) ? $template['chapter_prefix'] : '第';
            $suffix = isset( $template['chapter_suffix'] ) ? $template['chapter_suffix'] : '章';
            $number_format = isset( $template['number_format'] ) ? $template['number_format'] : 'chinese';

            foreach ( $chapters as $idx => $chapter ) {
                $chapter_num = $number_format === 'arabic' ? ( $idx + 1 ) : self::number_to_chinese( $idx + 1 );
                $md .= "- {$prefix}{$chapter_num}{$suffix} {$chapter['title']}\n";
            }
            $md .= "\n---\n\n";
        }

        // 前言
        if ( ! empty( $template['include_preface'] ) && ! empty( $meta['preface'] ) ) {
            $md .= "## 前言\n\n{$meta['preface']}\n\n---\n\n";
        }

        // 正文
        foreach ( $chapters as $idx => $chapter ) {
            $md .= self::stitch_chapter( $chapter, $template, $idx + 1 );
        }

        // 后记
        if ( ! empty( $meta['afterword'] ) ) {
            $md .= "\n---\n\n## 后记\n\n{$meta['afterword']}\n";
        }

        return $md;
    }

    /**
     * Markdown 转 HTML
     *
     * @param string $markdown
     * @return string
     */
    private static function markdown_to_html( $markdown ) {
        // 优先使用 Parsedown
        if ( class_exists( 'Parsedown' ) ) {
            $parsedown = new Parsedown();
            return $parsedown->text( $markdown );
        }

        // fallback: 简易转换
        $html = $markdown;
        $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );
        $html = preg_replace( '/^---$/m', '<hr/>', $html );
        $html = preg_replace( '/^\> (.+)$/m', '<blockquote>$1</blockquote>', $html );
        $html = preg_replace( '/^\- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
        $html = nl2br( $html );

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>书稿</title></head><body>' . $html . '</body></html>';
    }

    /**
     * 清洗 Markdown (H5约束: AI输出不稳定)
     *
     * @param string $content
     * @return string
     */
    public static function sanitize_markdown( $content ) {
        // 去除危险HTML标签
        $content = wp_strip_all_tags( $content, true );

        // 修复未闭合代码块
        $code_block_count = substr_count( $content, '```' );
        if ( $code_block_count % 2 !== 0 ) {
            $content .= "\n```";
        }

        // 统一换行
        $content = preg_replace( '/\r\n/', "\n", $content );
        $content = preg_replace( '/\n{3,}/', "\n\n", $content );

        return trim( $content );
    }

    /**
     * 数字转中文 (1-99)
     *
     * @param int $num
     * @return string
     */
    private static function number_to_chinese( $num ) {
        $chinese = array( '零', '一', '二', '三', '四', '五', '六', '七', '八', '九' );

        if ( $num < 10 ) {
            return $chinese[ $num ];
        }
        if ( $num < 20 ) {
            return '十' . ( $num > 10 ? $chinese[ $num - 10 ] : '' );
        }
        if ( $num < 100 ) {
            $tens = intval( $num / 10 );
            $ones = $num % 10;
            $result = $chinese[ $tens ] . '十';
            if ( $ones > 0 ) {
                $result .= $chinese[ $ones ];
            }
            return $result;
        }

        return (string) $num;
    }

    /**
     * 保存书稿到文件
     *
     * @param string $project_id
     * @param string $markdown
     * @param string $html
     * @return array
     */
    public static function save_to_file( $project_id, $markdown, $html ) : array {
        $upload_dir = wp_upload_dir();
        $book_dir = $upload_dir['basedir'] . '/linked3-book-projects/' . $project_id;
        if ( ! file_exists( $book_dir ) ) {
            wp_mkdir_p( $book_dir );
        }

        $safe_name = sanitize_file_name( $project_id );
        $md_path   = $book_dir . '/' . $safe_name . '.md';
        $html_path = $book_dir . '/' . $safe_name . '.html';

        // v18.11: 原子写入 MD/HTML 文件，防止并发写入导致文件损坏。
        try {
            BookSecurity::atomic_write( $md_path, $markdown );
            BookSecurity::atomic_write( $html_path, $html );
        } catch ( \RuntimeException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[BookFactory] 草稿文件原子写入失败: ' . $e->getMessage() );
            }
            // 回退到普通写入
            file_put_contents( $md_path, $markdown );
            file_put_contents( $html_path, $html );
        }

        return array(
            'md_path'   => $md_path,
            'html_path' => $html_path,
            'md_url'    => $upload_dir['baseurl'] . '/linked3-book-projects/' . $project_id . '/' . $safe_name . '.md',
            'html_url'  => $upload_dir['baseurl'] . '/linked3-book-projects/' . $project_id . '/' . $safe_name . '.html',
        );
    }
}
