<?php

declare(strict_types=1);
/**
 * Linked3 Type Mode Router — 类型×模式路由表
 *
 * 方案: S3 (G1) + S14 (G2动态生成fallback)
 * 公理: 6类型×3模式=18条路径 → 路由表统一映射
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) exit;

class TypeModeRouter {

    /**
     * 路由表 — 6类型×3模式=18条路径
     *
     * @var array
     */
    private static $routes = array(

        // ═══════════════════════════════════════
        // 类型: book (图书)
        // ═══════════════════════════════════════
        'book_handwrite' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'book_default',
            'prompt_overrides' => array(
                'step4_expand' => '请以手工写作风格扩写，注重逻辑连贯与语言精炼',
            ),
        ),
        'book_voice' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'book_default',
            'prompt_overrides' => array(
                'step4_expand' => '请以语音转写风格扩写，保留口语化表达与自然停顿',
            ),
        ),
        'book_ai' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'book_default',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助写作风格扩写，结构清晰、论据充分',
            ),
        ),

        // ═══════════════════════════════════════
        // 类型: thesis (论文)
        // ═══════════════════════════════════════
        'thesis_handwrite' => array(
            'type_unit'        => '篇',
            'yaml_config'      => array( 'max_outline_iterations' => 5, 'max_sections_per_chapter' => 4 ),
            'output_template'  => 'thesis_academic',
            'prompt_overrides' => array(
                'step4_expand' => '请以学术论文风格扩写，包含文献引用与论证逻辑',
            ),
        ),
        'thesis_voice' => array(
            'type_unit'        => '篇',
            'yaml_config'      => array( 'max_outline_iterations' => 5 ),
            'output_template'  => 'thesis_academic',
            'prompt_overrides' => array(
                'step4_expand' => '请以学术演讲风格扩写，适合口头答辩场景',
            ),
        ),
        'thesis_ai' => array(
            'type_unit'        => '篇',
            'yaml_config'      => array( 'max_outline_iterations' => 5 ),
            'output_template'  => 'thesis_academic',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助学术写作风格扩写，注重严谨性与创新性',
            ),
        ),

        // ═══════════════════════════════════════
        // 类型: script (剧本)
        // ═══════════════════════════════════════
        'script_handwrite' => array(
            'type_unit'        => '部',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'script_dialogue',
            'prompt_overrides' => array(
                'step4_expand' => '请以剧本格式扩写，包含场景描述、人物对话与舞台指示',
            ),
        ),
        'script_voice' => array(
            'type_unit'        => '部',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'script_dialogue',
            'prompt_overrides' => array(
                'step4_expand' => '请以广播剧格式扩写，注重对话节奏与音效提示',
            ),
        ),
        'script_ai' => array(
            'type_unit'        => '部',
            'yaml_config'      => array( 'max_outline_iterations' => 3 ),
            'output_template'  => 'script_dialogue',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助剧本创作风格扩写，结构紧凑、冲突鲜明',
            ),
        ),

        // ═══════════════════════════════════════
        // 类型: manual (手册)
        // ═══════════════════════════════════════
        'manual_handwrite' => array(
            'type_unit'        => '册',
            'yaml_config'      => array( 'max_outline_iterations' => 2 ),
            'output_template'  => 'manual_steps',
            'prompt_overrides' => array(
                'step4_expand' => '请以操作手册风格扩写，步骤清晰、配图说明',
            ),
        ),
        'manual_voice' => array(
            'type_unit'        => '册',
            'yaml_config'      => array( 'max_outline_iterations' => 2 ),
            'output_template'  => 'manual_steps',
            'prompt_overrides' => array(
                'step4_expand' => '请以语音教程风格扩写，适合音频指导',
            ),
        ),
        'manual_ai' => array(
            'type_unit'        => '册',
            'yaml_config'      => array( 'max_outline_iterations' => 2 ),
            'output_template'  => 'manual_steps',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助手册风格扩写，结构化、可检索',
            ),
        ),

        // ═══════════════════════════════════════
        // 类型: textbook (教材)
        // ═══════════════════════════════════════
        'textbook_handwrite' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'textbook_lesson',
            'prompt_overrides' => array(
                'step4_expand' => '请以教材风格扩写，包含知识点、例题与练习',
            ),
        ),
        'textbook_voice' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'textbook_lesson',
            'prompt_overrides' => array(
                'step4_expand' => '请以课堂讲授风格扩写，适合录音教学',
            ),
        ),
        'textbook_ai' => array(
            'type_unit'        => '本',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'textbook_lesson',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助教材风格扩写，知识图谱清晰',
            ),
        ),

        // ═══════════════════════════════════════
        // 类型: whitepaper (白皮书)
        // ═══════════════════════════════════════
        'whitepaper_handwrite' => array(
            'type_unit'        => '份',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'whitepaper_report',
            'prompt_overrides' => array(
                'step4_expand' => '请以行业白皮书风格扩写，数据驱动、趋势分析',
            ),
        ),
        'whitepaper_voice' => array(
            'type_unit'        => '份',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'whitepaper_report',
            'prompt_overrides' => array(
                'step4_expand' => '请以行业演讲风格扩写，适合发布会场景',
            ),
        ),
        'whitepaper_ai' => array(
            'type_unit'        => '份',
            'yaml_config'      => array( 'max_outline_iterations' => 4 ),
            'output_template'  => 'whitepaper_report',
            'prompt_overrides' => array(
                'step4_expand' => '请以AI辅助白皮书风格扩写，专业严谨、前瞻性强',
            ),
        ),
    );

    /**
     * 路由查询
     *
     * @param string $type book|thesis|script|manual|textbook|whitepaper
     * @param string $mode handwrite|voice|ai
     * @return array 路由配置 (含 fallback)
     */
    public static function route( $type, $mode ) : mixed {
        $key = $type . '_' . $mode;

        if ( isset( self::$routes[ $key ] ) ) {
            $route = self::$routes[ $key ];
        } else {
            // S14 fallback: 动态生成默认配置
            error_log( "[Linked3 BookFactory] 未知路由 {$key}, 使用 fallback" );
            $route = array(
                'type_unit'        => '本',
                'yaml_config'      => array( 'max_outline_iterations' => 3 ),
                'output_template'  => 'book_default',
                'prompt_overrides' => array(
                    'step4_expand' => '请以结构清晰、论据充分的风格扩写',
                ),
            );
        }

        // 注入输出模板配置
        $route['output_template_config'] = self::get_output_template( $route['output_template'] );

        return $route;
    }

    /**
     * 获取输出模板配置
     *
     * @param string $template_name
     * @return array
     */
    private static function get_output_template( $template_name ) : mixed {
        $templates = array(
            'book_default' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '章',
                'section_prefix' => '第',
                'section_suffix' => '节',
                'include_toc'    => true,
                'include_preface'=> true,
            ),
            'thesis_academic' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '章',
                'section_prefix' => '',
                'section_suffix' => '',
                'include_toc'    => true,
                'include_preface'=> true,
                'number_format'  => 'arabic',
            ),
            'script_dialogue' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '幕',
                'section_prefix' => '场景',
                'section_suffix' => '',
                'include_toc'    => true,
                'include_preface'=> false,
            ),
            'manual_steps' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '部分',
                'section_prefix' => '步骤',
                'section_suffix' => '',
                'include_toc'    => true,
                'include_preface'=> true,
            ),
            'textbook_lesson' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '课',
                'section_prefix' => '第',
                'section_suffix' => '节',
                'include_toc'    => true,
                'include_preface'=> true,
            ),
            'whitepaper_report' => array(
                'chapter_prefix' => '第',
                'chapter_suffix' => '部分',
                'section_prefix' => '',
                'section_suffix' => '',
                'include_toc'    => true,
                'include_preface'=> true,
                'number_format'  => 'arabic',
            ),
        );

        return isset( $templates[ $template_name ] ) ? $templates[ $template_name ] : $templates['book_default'];
    }

    /**
     * 获取类型标签
     *
     * @param string $type
     * @return string
     */
    public static function get_type_label( $type ) {
        $labels = array(
            'book'       => '图书',
            'thesis'     => '论文',
            'script'     => '剧本',
            'manual'     => '手册',
            'textbook'   => '教材',
            'whitepaper' => '白皮书',
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : '图书';
    }

    /**
     * 获取所有类型 (供UI渲染)
     *
     * @return array
     */
    public static function get_all_types() : array {
        return array(
            'book'       => '图书',
            'thesis'     => '论文',
            'script'     => '剧本',
            'manual'     => '手册',
            'textbook'   => '教材',
            'whitepaper' => '白皮书',
        );
    }

    /**
     * 获取所有模式
     *
     * @return array
     */
    public static function get_all_modes() : array {
        return array(
            'handwrite' => '手工写作',
            'voice'     => '语音写作',
            'ai'        => 'AI写书',
        );
    }

    /**
     * v19.1: 获取指定探索原型的配置
     *
     * @param string $prototype_key 原型key (book/experimental/observational/...)
     * @return array|null
     */
    public static function get_exploration_prototype( $prototype_key ) {
        return BookExplorationPrototypes::get( $prototype_key );
    }

    /**
     * 获取所有迭代档位 (O部幻觉3修正)
     *
     * @return array
     */
    public static function get_all_iteration_levels() : array {
        return array(
            'quick' => array(
                'label'       => '快速',
                'description' => '1次大纲迭代，适合快速出稿',
                'iterations'  => 1,
            ),
            'standard' => array(
                'label'       => '标准',
                'description' => '3次大纲迭代，平衡质量与速度',
                'iterations'  => 3,
            ),
            'deep' => array(
                'label'       => '深度',
                'description' => '10次大纲迭代，最高质量',
                'iterations'  => 10,
            ),
        );
    }
}
