<?php

declare(strict_types=1);
/**
 * Linked3 Book Prompt Manager — 提示词外置管理器
 *
 * 方案: N1 (提示词外置到DB, 从 _index.json 加载默认, 用户可覆盖)
 * 公理: 提示词可见、可编辑、可保存 — 不闭源
 *
 * 工作原理:
 *   1. 首次加载: 从 _index.json 读取默认提示词, 写入 wp_options (linked3_book_prompts)
 *   2. 后续加载: 优先读 DB (用户可能已修改), fallback 到 _index.json
 *   3. 变量填充: {书名}/{类型}/{模式} 等占位符在执行时动态替换
 *
 * @package Linked3\BookFactory
 * @since   18.6.0
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) exit;

class BookPromptManager {

    /** @var string DB选项名 */
    const OPTION_KEY = 'linked3_book_prompts';

    /** @var array 提示词缓存 */
    private static $cache = null;

    /**
     * 获取所有提示词 (DB优先, fallback到_index.json)
     *
     * @return array
     */
    public static function get_all() : mixed {
        if ( self::$cache !== null ) {
            return self::$cache;
        }

        // 1. 尝试从DB读取
        $db_prompts = get_option( self::OPTION_KEY, array() );
        if ( is_array( $db_prompts ) && ! empty( $db_prompts ) ) {
            self::$cache = $db_prompts;
            return self::$cache;
        }

        // 2. 首次加载: 从 _index.json 读取默认值, 写入DB
        $defaults = self::load_defaults_from_index();
        if ( ! empty( $defaults ) ) {
            update_option( self::OPTION_KEY, $defaults, false );
            self::$cache = $defaults;
        } else {
            self::$cache = array();
        }

        return self::$cache;
    }

    /**
     * 获取指定步骤的提示词
     *
     * @param string $step_key step1_demo|step2_explore|step3_outline|step4_expand|step5_complete|step6_review
     * @return array
     */
    public static function get_step_prompts( $step_key ) : mixed {
        $all = self::get_all();
        return isset( $all[ $step_key ] ) ? $all[ $step_key ] : array();
    }

    /**
     * 获取填充变量后的提示词 (N1+N5: 供工厂执行时调用)
     *
     * @param string $step_key
     * @param array  $vars
     * @param int    $prompt_idx 选择第几个提示词 (1-based)
     * @return string
     */
    public static function get_prompt( $step_key, $vars = array(), $prompt_idx = 1 ) {
        $step_prompts = self::get_step_prompts( $step_key );

        // 选择提示词 (优先用户保存的, fallback到默认)
        $prompt_text = '';
        if ( isset( $step_prompts['prompts'] ) && is_array( $step_prompts['prompts'] ) ) {
            $idx = $prompt_idx - 1;
            if ( isset( $step_prompts['prompts'][ $idx ]['text'] ) ) {
                $prompt_text = $step_prompts['prompts'][ $idx ]['text'];
            } elseif ( isset( $step_prompts['prompts'][0]['text'] ) ) {
                $prompt_text = $step_prompts['prompts'][0]['text'];
            }
        }

        if ( empty( $prompt_text ) ) {
            // fallback: 内置默认提示词
            $prompt_text = self::get_builtin_default( $step_key );
        }

        // N5: 填充变量
        if ( ! empty( $vars ) ) {
            $prompt_text = self::fill_variables( $prompt_text, $vars );
        }

        return $prompt_text;
    }

    /**
     * 保存单个提示词 (N1+N3: 供Ajax调用)
     *
     * @param string $step_key
     * @param string $prompt_text
     * @return bool|WP_Error
     */
    public static function save_prompt( $step_key, $prompt_text ) {
        $all = self::get_all();
        if ( ! isset( $all[ $step_key ] ) ) {
            $all[ $step_key ] = array( 'prompts' => array() );
        }
        if ( ! isset( $all[ $step_key ]['prompts'] ) || ! is_array( $all[ $step_key ]['prompts'] ) ) {
            $all[ $step_key ]['prompts'] = array();
        }
        if ( empty( $all[ $step_key ]['prompts'] ) ) {
            $all[ $step_key ]['prompts'][] = array( 'id' => 'p1', 'text' => $prompt_text );
        } else {
            $all[ $step_key ]['prompts'][0]['text'] = $prompt_text;
        }
        $result = update_option( self::OPTION_KEY, $all, false );
        self::$cache = $all;
        return $result;
    }

    /**
     * 内置默认提示词 (当 _index.json 加载失败时的兜底)
     *
     * @param string $step_key
     * @return string
     */
    private static function get_builtin_default( $step_key ) {
        $defaults = array(
            'step1_demo' => '请系统演示如何一步一步写一本书《{book_title}》。',
            'step2_explore' => '请开放探索有长期价值的{book_type}类别和《{book_title}》名,带{book_type}和人群定位说明。',
            'step3_outline' => '请为{book_type}《{book_title}》系统设计有长期价值的大纲目录，每章{sections_per_chapter}小节，每次输出{chapters_per_output}章。只返回Markdown格式的大纲，不要解释。',
            'step4_expand' => '开始完善《{book_title}》这{type_unit}{book_type}的小节。当前章节: {chapter_title}。当前小节: {section_title}。请深入系统详细完善扩写，生成{word_count}字正文内容。直接输出内容，不要标题和解释。',
            'step5_complete' => '',
            'step6_review' => '请以审稿人身份审阅《{book_title}》的初稿，指出3个最薄弱的逻辑环节并给出改进建议。',
        );
        return isset( $defaults[ $step_key ] ) ? $defaults[ $step_key ] : '';
    }

    /**
     * 从 _index.json 加载默认提示词
     *
     * @return array
     */
    private static function load_defaults_from_index() {
        // v18.9: 优先加载增长黑客版提示词库
        $gh_path = LINKED3_DIR . 'src/Classes/BookFactory/prompt_library/growth_hack_prompts.json';
        if ( file_exists( $gh_path ) ) {
            $gh_json = json_decode( file_get_contents( $gh_path ), true );
            if ( is_array( $gh_json ) && isset( $gh_json['prompts'] ) ) {
                // 合并: 增长黑客版优先, _index.json作为fallback
                $index_prompts = self::load_from_index_json();
                $merged = array_merge( $index_prompts, $gh_json['prompts'] );
                return $merged;
            }
        }

        // fallback: 仅从 _index.json 加载
        return self::load_from_index_json();
    }

    /**
     * 从 _index.json 加载提示词 (原load_defaults_from_index逻辑)
     *
     * @return array
     */
    private static function load_from_index_json() {
        $path = LINKED3_DIR . 'src/Classes/ContentWriter/book_templates/_index.json';
        if ( ! file_exists( $path ) ) {
            return self::get_hardcoded_defaults();
        }

        $json = json_decode( file_get_contents( $path ), true );
        if ( ! is_array( $json ) || ! isset( $json['six_steps'] ) ) {
            return self::get_hardcoded_defaults();
        }

        $prompts = array();
        foreach ( $json['six_steps'] as $step_key => $step_info ) {
            $step_prompts = array();

            // 收集所有提示词变体
            if ( isset( $step_info['prompts'] ) ) {
                foreach ( $step_info['prompts'] as $p ) {
                    $step_prompts[] = array(
                        'id'    => $p['id'] ?? '',
                        'text'  => $p['text'] ?? '',
                        'note'  => $p['note'] ?? '',
                        'tool'  => $p['tool'] ?? '任意大模型',
                    );
                }
            }
            if ( isset( $step_info['prompts_simple'] ) ) {
                foreach ( $step_info['prompts_simple'] as $p ) {
                    $step_prompts[] = array(
                        'id'    => $p['id'] ?? '',
                        'text'  => $p['text'] ?? '',
                        'note'  => $p['note'] ?? '',
                        'tool'  => $p['tool'] ?? '任意大模型',
                    );
                }
            }
            if ( isset( $step_info['prompts_advanced'] ) ) {
                foreach ( $step_info['prompts_advanced'] as $p ) {
                    $step_prompts[] = array(
                        'id'    => $p['id'] ?? '',
                        'text'  => $p['text'] ?? '',
                        'note'  => $p['note'] ?? '',
                        'tool'  => $p['tool'] ?? '任意大模型',
                    );
                }
            }

            $prompts[ $step_key ] = array(
                'name'      => $step_info['name'] ?? $step_key,
                'desc'      => $step_info['desc'] ?? '',
                'variables' => $step_info['variables'] ?? array(),
                'prompts'   => $step_prompts,
            );
        }

        return $prompts;
    }

    /**
     * 硬编码兜底默认值 (当 _index.json 不存在时)
     *
     * @return array
     */
    private static function get_hardcoded_defaults() : array {
        return array(
            'step3_outline' => array(
                'name' => '撰写大纲',
                'desc' => '根据书名生成章节大纲',
                'variables' => array(
                    'book_title' => '写书式学习',
                    'sections_per_chapter' => 5,
                    'chapters_per_output' => 12,
                ),
                'prompts' => array(
                    array(
                        'id' => 'p1',
                        'text' => "请为{book_type}《{book_title}》系统设计有长期价值的大纲目录，每章{sections_per_chapter}小节，每次输出{chapters_per_output}章。\n\n格式要求:\n第X章 章标题\n第X节 节标题\n\n只输出大纲，不要解释。",
                        'note' => '基础版',
                        'tool' => 'Deepseek/GLM',
                    ),
                ),
            ),
            'step4_expand' => array(
                'name' => '扩写小节',
                'desc' => '逐节扩写正文内容',
                'variables' => array(
                    'book_title' => '写书式学习',
                    'type_unit' => '本书',
                    'book_type' => '图书',
                    'language' => '中文',
                    'readers' => '所有人群',
                    'thinking_mode' => '第一性原理',
                    'word_count' => 1500,
                ),
                'prompts' => array(
                    array(
                        'id' => 'p1',
                        'text' => "开始完善《{book_title}》这{type_unit}{book_type}的小节，全文符合{book_type}{language}语言表述习惯，用{readers}能听懂的方式，采用{thinking_mode}深入系统详细完善扩写{section_name}，生成{word_count}字更加丰富的正文内容，依据内容需要，给出适当2-3个例子，不输出总结和解释。",
                        'note' => '高级扩写',
                        'tool' => 'Deepseek/GLM(中文好), GPT/Claude(英文好)',
                    ),
                ),
            ),
        );
    }

    /**
     * 填充提示词变量 (N5: 根据书名/类型/模式动态填充)
     *
     * @param string $prompt_text 原始提示词 (含 {变量} 占位符)
     * @param array  $vars 变量键值对
     * @return string 填充后的提示词
     */
    public static function fill_variables( $prompt_text, $vars ) {
        if ( empty( $prompt_text ) ) {
            return '';
        }

        // 替换 {变量名} 占位符
        foreach ( $vars as $key => $value ) {
            $prompt_text = str_replace( '{' . $key . '}', $value, $prompt_text );
        }

        // 清理未填充的占位符 (避免 {xxx} 残留)
        $prompt_text = preg_replace( '/\{[a-zA-Z_\x{4e00}-\x{9fa5}]+\}/u', '', $prompt_text );

        return $prompt_text;
    }

    /**
     * 构建执行上下文变量 (根据书名/类型/模式)
     *
     * @param string $book_title
     * @param string $type
     * @param string $mode
     * @param string $level
     * @return array
     */
    public static function build_context_vars( $book_title, $type, $mode, $level = 'standard' ) : array {
        $type_labels = TypeModeRouter::get_all_types();
        $type_unit_map = array(
            'book' => '本', 'thesis' => '篇', 'script' => '部',
            'manual' => '本', 'textbook' => '本', 'whitepaper' => '份',
        );

        $level_iterations = array(
            'quick' => 1, 'standard' => 3, 'deep' => 10,
        );

        return array(
            'book_title'             => $book_title,
            'book_type'              => isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : '图书',
            'type_unit'              => isset( $type_unit_map[ $type ] ) ? $type_unit_map[ $type ] : '本',
            'mode'                   => $mode,
            'iteration_level'        => $level,
            'outline_iterations'     => isset( $level_iterations[ $level ] ) ? $level_iterations[ $level ] : 3,
            'sections_per_chapter'   => 5,
            'chapters_per_output'    => 12,
            'language'               => '中文',
            'readers'                => '所有人群',
            'thinking_mode'          => '第一性原理',
            'word_count'             => 1500,
        );
    }
}
