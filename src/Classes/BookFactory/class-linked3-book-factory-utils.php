<?php
namespace Linked3\Classes\BookFactory;
if (!defined('ABSPATH')) exit;
class Linked3_Book_Factory_Utils
{
    public function smart_split_outline( $content ) : mixed {
        // 先尝试标准解析
        $outline = $this->parse_outline( $content );

        // 如果解析出≥3章, 直接返回
        if ( count( $outline['chapters'] ) >= 3 ) {
            return $outline;
        }

        // v18.10: 解析失败或<3章, 用智能分段
        $lines = explode( "\n", $content );
        $chapters = array();
        $current_chapter = null;

        // 策略1: 按标题行切分(## / ** / 数字. / 第X章)
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            $is_title = false;
            $title = '';

            // 匹配各种标题格式
            if ( preg_match( '/^#{1,6}\s+(.+)$/', $line, $m ) ) {
                $is_title = true;
                $title = $m[1];
            } elseif ( preg_match( '/^第[一二三四五六七八九十百零\d]+[章节][\s:：]*(.+)$/u', $line, $m ) ) {
                $is_title = true;
                $title = $m[1];
            } elseif ( preg_match( '/^\*{2}(.+?)\*{2}$/', $line, $m ) ) {
                $is_title = true;
                $title = $m[1];
            } elseif ( preg_match( '/^(\d+)[.、]\s*(.+)$/', $line, $m ) ) {
                // 数字开头且是短行(标题)
                if ( mb_strlen( $line ) < 30 ) {
                    $is_title = true;
                    $title = $m[2];
                }
            }

            if ( $is_title && ! empty( $title ) ) {
                // v18.10.1: 清理标题中的"第X章/节"前缀, 避免"第第"重复
                $title = preg_replace( '/^第[一二三四五六七八九十百零\d]+[章节部分][\s:：]*/u', '', $title );
                $title = preg_replace( '/^[#*\-\s]+/', '', $title );
                $title = trim( $title );
                if ( empty( $title ) ) {
                    $title = '章节' . ( count( $chapters ) + 1 );
                }

                if ( $current_chapter ) {
                    $chapters[] = $current_chapter;
                }
                $current_chapter = array(
                    'title' => $title,
                    'sections' => array(),
                );
            } elseif ( $current_chapter ) {
                // v18.10.3: 非标题行作为小节, 但每章最多5节 (避免132节爆炸)
                if ( count( $current_chapter['sections'] ) < 5 ) {
                    $clean = trim( preg_replace( '/^[-*]\s*/', '', $line ) );
                    if ( ! empty( $clean ) && mb_strlen( $clean ) > 2 ) {
                        $current_chapter['sections'][] = array( 'title' => mb_substr( $clean, 0, 50 ) );
                    }
                }
            }
        }
        if ( $current_chapter ) {
            $chapters[] = $current_chapter;
        }

        // 策略2: 如果仍<3章, 按段落强制切分
        if ( count( $chapters ) < 3 ) {
            $paragraphs = array_filter( explode( "\n\n", $content ) );
            $paragraphs = array_filter( $paragraphs, function( $p ) {
                return mb_strlen( trim( $p ) ) > 20;
            });
            $paragraphs = array_values( $paragraphs );

            if ( count( $paragraphs ) >= 3 ) {
                $chapters = array();
                $chapter_count = min( count( $paragraphs ), 12 );
                $paras_per_chapter = max( 1, intval( count( $paragraphs ) / $chapter_count ) );

                for ( $i = 0; $i < $chapter_count; $i++ ) {
                    $start = $i * $paras_per_chapter;
                    $end = min( $start + $paras_per_chapter, count( $paragraphs ) );
                    $chapter_content = implode( "\n\n", array_slice( $paragraphs, $start, $end - $start ) );
                    $first_line = trim( explode( "\n", $chapter_content )[0] );
                    // v18.10.1: 清理标题中的"第X章/部分"前缀, 避免"第第"重复
                    $title = preg_replace( '/^第[一二三四五六七八九十百零\d]+[章节部分][\s:：]*/u', '', $first_line );
                    $title = preg_replace( '/^[#*\-\s]+/', '', $title );
                    $title = trim( mb_substr( $title, 0, 30 ) );
                    if ( empty( $title ) ) {
                        $title = '内容' . ( $i + 1 );
                    }

                    $chapters[] = array(
                        'title' => '第' . ( $i + 1 ) . '章 ' . $title,
                        'sections' => array(
                            array( 'title' => '正文' ),
                        ),
                    );
                }
            }
        }

        // 策略3: 兜底, 强制切成4章
        if ( count( $chapters ) < 3 ) {
            $mid = intval( mb_strlen( $content ) / 4 );
            $chapters = array();
            for ( $i = 0; $i < 4; $i++ ) {
                $start = $i * $mid;
                $chunk = mb_substr( $content, $start, $mid );
                $chapters[] = array(
                    'title' => '第' . ( $i + 1 ) . '章',
                    'sections' => array( array( 'title' => '正文' ) ),
                );
            }
        }

        // 确保每章至少有1个小节
        foreach ( $chapters as &$ch ) {
            if ( empty( $ch['sections'] ) ) {
                $ch['sections'] = array( array( 'title' => '正文' ) );
            }
        }

        return array( 'chapters' => $chapters );
    }

    public function parse_outline( $content ) : mixed {
        $outline = array( 'chapters' => array() );
        $lines = explode( "\n", $content );
        $current_chapter = null;

        // v18.9.2修复: 先去除Markdown前缀(## ### ** - 等), 兼容多种AI输出格式
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            // 去除Markdown前缀: ## / ### / ** / - / * (但保留数字.数字格式如1.1)
            $clean_line = preg_replace( '/^[#*\-\s]+/', '', $line );
            // 只去除 "数字. " 格式(如 "1. "), 不去除 "数字.数字" 格式(如 "1.1")
            $clean_line = preg_replace( '/^(\d+)\.\s+(?=\D)/', '', $clean_line );
            $clean_line = trim( $clean_line );

            // 匹配 "第X章 标题" (兼容中文数字和阿拉伯数字)
            if ( preg_match( '/^第[一二三四五六七八九十百零\d]+章[\s:：]*(.+)$/u', $clean_line, $m ) ) {
                if ( $current_chapter ) {
                    $outline['chapters'][] = $current_chapter;
                }
                $current_chapter = array(
                    'title'    => trim( $m[1] ),
                    'sections' => array(),
                );
            }
            // 匹配 "第X节 标题"
            elseif ( preg_match( '/^第[一二三四五六七八九十百零\d]+节[\s:：]*(.+)$/u', $clean_line, $m ) ) {
                if ( $current_chapter ) {
                    $current_chapter['sections'][] = array( 'title' => trim( $m[1] ) );
                }
            }
            // 匹配 "1.1 标题" 或 "1.1、标题" (数字.数字格式)
            elseif ( preg_match( '/^(\d+)\.(\d+)[\s、:：]*(.+)$/u', $clean_line, $m ) ) {
                if ( $current_chapter && ! empty( trim( $m[3] ) ) ) {
                    $current_chapter['sections'][] = array( 'title' => trim( $m[3] ) );
                }
            }
            // 匹配 "- 标题" 或 "1. 标题" (兜底)
            elseif ( preg_match( '/^[-\d.、]+[\s]*(.+)$/u', $clean_line, $m ) ) {
                if ( $current_chapter && ! empty( trim( $m[1] ) ) && strlen( trim( $m[1] ) ) > 2 ) {
                    $current_chapter['sections'][] = array( 'title' => trim( $m[1] ) );
                }
            }
        }

        if ( $current_chapter ) {
            $outline['chapters'][] = $current_chapter;
        }

        // v18.9.2: 如果解析出0章, 用AI原始输出作为单章兜底
        if ( empty( $outline['chapters'] ) ) {
            $outline['chapters'][] = array(
                'title' => '正文内容',
                'sections' => array(
                    array( 'title' => '完整内容' ),
                ),
            );
            // 记录原始输出供调试
            error_log( '[linked3 book_factory] parse_outline未匹配到章节, 原始输出前500字: ' . mb_substr( $content, 0, 500 ) );
        }

        return $outline;
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
        $result = Linked3_Section_Stitcher::stitch( $full_chapters, $template, $book_title );
        $files = Linked3_Section_Stitcher::save_to_file( $this->state->project_id, $result['markdown'], $result['html'] );

        $this->state->set( 'draft_markdown', $result['markdown'] );
        $this->state->set( 'draft_html', $result['html'] );
        $this->state->set( 'draft_files', $files );
        $this->state->save_state();
    }

    public function load_pipeline_config() {
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
