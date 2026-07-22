<?php

declare(strict_types=1);
namespace Linked3\Classes\BookFactory;
if (!defined('ABSPATH')) exit;
class BookFactoryUtils
{
    public static function smart_split_outline( $content ) : mixed {
        // 先尝试标准解析
        $outline = self::parse_outline( $content );
        if ( count( $outline['chapters'] ) >= 3 ) {
            return $outline;
        }

        // v18.10: 解析失败或<3章, 用智能分段
        $chapters = self::split_by_title_lines( $content );

        // 策略2: 如果仍<3章, 按段落强制切分
        if ( count( $chapters ) < 3 ) {
            $chapters = self::split_by_paragraphs( $content );
        }

        // 策略3: 兜底, 强制切成4章
        if ( count( $chapters ) < 3 ) {
            $chapters = self::force_split_quarters( $content );
        }

        // 确保每章至少有1个小节
        self::ensure_min_sections( $chapters );

        return array( 'chapters' => $chapters );
    }

    /**
     * 策略1: 按标题行切分(## / ** / 数字. / 第X章)
     */
    private static function split_by_title_lines( string $content ) : array {
        $lines = explode( "\n", $content );
        $chapters = array();
        $current_chapter = null;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            $title = self::detect_title_line( $line );

            if ( $title !== null ) {
                $title = self::clean_chapter_title( $title, count( $chapters ) + 1 );
                if ( $current_chapter ) {
                    $chapters[] = $current_chapter;
                }
                $current_chapter = array( 'title' => $title, 'sections' => array() );
            } elseif ( $current_chapter ) {
                self::add_section_to_chapter( $current_chapter, $line );
            }
        }
        if ( $current_chapter ) {
            $chapters[] = $current_chapter;
        }
        return $chapters;
    }

    /**
     * 检测行是否为标题, 返回标题文本或 null
     */
    private static function detect_title_line( string $line ) : ?string {
        if ( preg_match( '/^#{1,6}\s+(.+)$/', $line, $m ) ) return $m[1];
        if ( preg_match( '/^第[一二三四五六七八九十百零\d]+[章节][\s:：]*(.+)$/u', $line, $m ) ) return $m[1];
        if ( preg_match( '/^\*{2}(.+?)\*{2}$/', $line, $m ) ) return $m[1];
        if ( preg_match( '/^(\d+)[.、]\s*(.+)$/', $line, $m ) && mb_strlen( $line ) < 30 ) return $m[2];
        return null;
    }

    /**
     * v18.10.1: 清理标题中的"第X章/节"前缀, 避免"第第"重复
     */
    private static function clean_chapter_title( string $title, int $fallback_num ) : string {
        $title = preg_replace( '/^第[一二三四五六七八九十百零\d]+[章节部分][\s:：]*/u', '', $title );
        $title = preg_replace( '/^[#*\-\s]+/', '', $title );
        $title = trim( $title );
        if ( empty( $title ) ) {
            $title = '章节' . $fallback_num;
        }
        return $title;
    }

    /**
     * v18.10.3: 非标题行作为小节, 但每章最多5节 (避免132节爆炸)
     */
    private static function add_section_to_chapter( array &$chapter, string $line ) : void {
        if ( count( $chapter['sections'] ) >= 5 ) return;
        $clean = trim( preg_replace( '/^[-*]\s*/', '', $line ) );
        if ( ! empty( $clean ) && mb_strlen( $clean ) > 2 ) {
            $chapter['sections'][] = array( 'title' => mb_substr( $clean, 0, 50 ) );
        }
    }

    /**
     * 策略2: 按段落强制切分
     */
    private static function split_by_paragraphs( string $content ) : array {
        $paragraphs = array_filter( explode( "\n\n", $content ) );
        $paragraphs = array_filter( $paragraphs, fn( $p ) => mb_strlen( trim( $p ) ) > 20 );
        $paragraphs = array_values( $paragraphs );
        if ( count( $paragraphs ) < 3 ) return [];

        $chapters = array();
        $chapter_count = min( count( $paragraphs ), 12 );
        $paras_per_chapter = max( 1, intval( count( $paragraphs ) / $chapter_count ) );

        for ( $i = 0; $i < $chapter_count; $i++ ) {
            $start = $i * $paras_per_chapter;
            $end = min( $start + $paras_per_chapter, count( $paragraphs ) );
            $chapter_content = implode( "\n\n", array_slice( $paragraphs, $start, $end - $start ) );
            $first_line = trim( explode( "\n", $chapter_content )[0] );
            $title = self::clean_chapter_title( $first_line, $i + 1 );
            $title = trim( mb_substr( $title, 0, 30 ) );
            if ( empty( $title ) ) {
                $title = '内容' . ( $i + 1 );
            }
            $chapters[] = array(
                'title' => '第' . ( $i + 1 ) . '章 ' . $title,
                'sections' => array( array( 'title' => '正文' ) ),
            );
        }
        return $chapters;
    }

    /**
     * 策略3: 兜底, 强制切成4章
     */
    private static function force_split_quarters( string $content ) : array {
        $mid = intval( mb_strlen( $content ) / 4 );
        $chapters = array();
        for ( $i = 0; $i < 4; $i++ ) {
            $chapters[] = array(
                'title' => '第' . ( $i + 1 ) . '章',
                'sections' => array( array( 'title' => '正文' ) ),
            );
        }
        return $chapters;
    }

    /**
     * 确保每章至少有1个小节
     */
    private static function ensure_min_sections( array &$chapters ) : void {
        foreach ( $chapters as &$ch ) {
            if ( empty( $ch['sections'] ) ) {
                $ch['sections'] = array( array( 'title' => '正文' ) );
            }
        }
    }

    public static function parse_outline( $content ) : mixed {
        $outline = array( 'chapters' => array() );
        $lines = explode( "\n", $content );
        $current_chapter = null;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            $clean_line = self::clean_outline_line( $line );
            $current_chapter = self::match_outline_pattern( $clean_line, $current_chapter, $outline );
        }

        if ( $current_chapter ) {
            $outline['chapters'][] = $current_chapter;
        }

        // v18.9.2: 如果解析出0章, 用AI原始输出作为单章兜底
        if ( empty( $outline['chapters'] ) ) {
            $outline['chapters'][] = array(
                'title' => '正文内容',
                'sections' => array( array( 'title' => '完整内容' ) ),
            );
            error_log( '[linked3 book_factory] parse_outline未匹配到章节, 原始输出前500字: ' . mb_substr( $content, 0, 500 ) );
        }

        return $outline;
    }

    /**
     * v18.9.2: 去除Markdown前缀(## ### ** - 等), 兼容多种AI输出格式
     */
    private static function clean_outline_line( string $line ) : string {
        $clean = preg_replace( '/^[#*\-\s]+/', '', $line );
        $clean = preg_replace( '/^(\d+)\.\s+(?=\D)/', '', $clean );
        return trim( $clean );
    }

    /**
     * 匹配大纲行模式 (第X章 / 第X节 / 1.1 / 兜底)
     */
    private static function match_outline_pattern( string $clean_line, ?array $current_chapter, array &$outline ) : ?array {
        // 匹配 "第X章 标题"
        if ( preg_match( '/^第[一二三四五六七八九十百零\d]+章[\s:：]*(.+)$/u', $clean_line, $m ) ) {
            if ( $current_chapter ) {
                $outline['chapters'][] = $current_chapter;
            }
            return array( 'title' => trim( $m[1] ), 'sections' => array() );
        }
        // 匹配 "第X节 标题"
        if ( preg_match( '/^第[一二三四五六七八九十百零\d]+节[\s:：]*(.+)$/u', $clean_line, $m ) ) {
            if ( $current_chapter ) {
                $current_chapter['sections'][] = array( 'title' => trim( $m[1] ) );
            }
            return $current_chapter;
        }
        // 匹配 "1.1 标题" 或 "1.1、标题"
        if ( preg_match( '/^(\d+)\.(\d+)[\s、:：]*(.+)$/u', $clean_line, $m ) ) {
            if ( $current_chapter && ! empty( trim( $m[3] ) ) ) {
                $current_chapter['sections'][] = array( 'title' => trim( $m[3] ) );
            }
            return $current_chapter;
        }
        // 匹配 "- 标题" 或 "1. 标题" (兜底)
        if ( preg_match( '/^[-\d.、]+[\s]*(.+)$/u', $clean_line, $m ) ) {
            if ( $current_chapter && ! empty( trim( $m[1] ) ) && strlen( trim( $m[1] ) ) > 2 ) {
                $current_chapter['sections'][] = array( 'title' => trim( $m[1] ) );
            }
        }
        return $current_chapter;
    }

    public function rebuild_draft() : void {
        $chapters = $this->state->get( 'chapters' );
        $sections = $this->state->get( 'sections' );
        $book_title = $this->state->get( 'book_title' );

        $full_chapters = array();
        foreach ( $chapters as $ch_idx => $chapter ) {
            $ch_data = array(
                'title'    => $chapter['title'],
                'sections' => array(),
            );
            foreach ( $chapter['sections'] as $sec_idx => $section ) {
                $ch_data['sections'][] = array(
                    'title'   => $section['title'],
                    'content' => isset( $sections[ $ch_idx ][ $sec_idx ] ) ? $sections[ $ch_idx ][ $sec_idx ] : '',
                );
            }
            $full_chapters[] = $ch_data;
        }

        $template = $this->route['output_template_config'];
        $result = SectionStitcher::stitch( $full_chapters, $template, $book_title );
        $files = SectionStitcher::save_to_file( $this->state->project_id, $result['markdown'], $result['html'] );

        $this->state->set( 'draft_markdown', $result['markdown'] );
        $this->state->set( 'draft_html', $result['html'] );
        $this->state->set( 'draft_files', $files );
        $this->state->save_state();
    }

    public static function load_pipeline_config() {
        // v18.8.1修复: 使用正确的常量名 (linked3.php定义的是 LINKED3_DIR)
        $yaml_path = LINKED3_DIR . 'src/Classes/Genesis/pipelines/book.yaml';
        if ( ! file_exists( $yaml_path ) ) {
            return array( 'config' => array( 'defaults' => array() ) );
        }

        if ( function_exists( 'yaml_parse_file' ) ) {
            $config = yaml_parse_file( $yaml_path );
        } else {
            // fallback: 简易YAML解析
            $content = file_get_contents( $yaml_path );
            if ($content === false) { return []; }
            $config = array( 'config' => array( 'defaults' => array(
                'max_outline_iterations' => 3,
                'max_chapters' => 12,
                'max_sections_per_chapter' => 5,
                'rate_limit_rpm' => 10,
                'context_summary_length' => 80,
            ) ) );
        }

        return $config ?: array( 'config' => array( 'defaults' => array() ) );
    }

}
