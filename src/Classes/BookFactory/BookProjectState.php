<?php

declare(strict_types=1);
/**
 * Linked3 Book Project State — 写书项目状态机
 *
 * 方案: S2 (G1) + S11 (G2实时同步) + S15 (G2项目隔离)
 * 公理: 状态即真相 — Book_Project_State 是唯一真相源
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) exit;

class BookProjectState {

    /** @var string 项目ID */
    public $project_id;

    /** @var array 完整状态数据 */
    private $state = array();

    /** @var array 多项目隔离 (S15) */
    private static $projects = array();

    /** 状态枚举 */
    const STATUS_IDLE       = 'idle';
    const STATUS_DEMOING    = 'demoing';
    const STATUS_EXPLORING  = 'exploring';
    const STATUS_OUTLINING  = 'outlining';
    const STATUS_EXPANDING  = 'expanding';
    const STATUS_COMPLETING = 'completing';
    const STATUS_REVIEWING  = 'reviewing';
    const STATUS_DONE       = 'done';
    const STATUS_FAILED     = 'failed';
    const STATUS_PAUSED     = 'paused';

    /**
     * 状态 schema 版本号 (v18.11 新增)
     * 用于未来状态结构变更时的自动迁移。
     * v1 = 18.11 之前的无版本号状态
     * v2 = 18.11 引入 schema_version 字段
     */
    const SCHEMA_VERSION = 2;

    /**
     * 构造 — 加载或初始化状态
     *
     * @param string $project_id 项目ID(空则新建)
     * @param array  $init_data  初始化数据
     */
    public function __construct( $project_id = '', $init_data = array() ) {
        if ( empty( $project_id ) ) {
            $project_id = 'book_' . substr( md5( uniqid( '', true ) ), 0, 12 );
        }
        // v18.11: project_id 路径白名单校验，防止路径遍历攻击。
        $validated = BookSecurity::validate_project_id( $project_id );
        if ( false === $validated ) {
            // 校验失败时生成一个新的安全 ID，而非直接使用不安全输入。
            $project_id = 'book_' . substr( md5( uniqid( '', true ) ), 0, 12 );
        } else {
            $project_id = $validated;
        }
        $this->project_id = $project_id;

        if ( ! empty( $init_data ) ) {
            $this->init_state( $init_data );
        } else {
            $this->load_state();
        }

        self::$projects[ $project_id ] = $this;
    }

    /**
     * 初始化状态
     *
     * @param array $init_data {book_title, type, mode, iteration_level}
     */
    private function init_state(array $init_data) : void {
        $this->state = array(
            'project_id'          => $this->project_id,
            'schema_version'      => self::SCHEMA_VERSION, // v18.11: 状态 schema 版本号，支持未来迁移
            'book_title'          => isset( $init_data['book_title'] ) ? sanitize_text_field( $init_data['book_title'] ) : '',
            'type'                => isset( $init_data['type'] ) ? sanitize_text_field( $init_data['type'] ) : 'book',
            'mode'                => isset( $init_data['mode'] ) ? sanitize_text_field( $init_data['mode'] ) : 'ai',
            'iteration_level'     => isset( $init_data['iteration_level'] ) ? sanitize_text_field( $init_data['iteration_level'] ) : 'standard',
            'status'              => self::STATUS_IDLE,
            'current_step'        => '',
            'demo_questions'      => '',
            'theme_keywords'      => array(),
            'core_arguments'      => array(),
            'exploration'         => '',
            'outline_versions'    => array(),
            'outline_raw'         => '',
            'final_outline'       => array( 'chapters' => array() ),
            'chapters'            => array(),
            'sections'            => array(),
            'section_outputs'     => array(),
            'draft_markdown'      => '',
            'draft_html'          => '',
            'draft_path'          => '',
            'draft_files'         => array(),
            'reviewed_markdown'   => '',
            'review_suggestions'  => array(),
            'review_output'       => '',
            'step_history'        => array(),
            'step_outputs'        => array(),
            'cost_log'            => array(),
            'budget_total'        => 5.00,
            'context_summary_length' => 80,
            'current_prompt'      => '',
            'current_prompt_step' => '',
            'current_output'      => '',
            'current_chapter_idx' => 0,
            'current_section_idx' => 0,
            'outline_iter_cursor' => 0,
            'expand_chapter_cursor' => 0,
            'expand_section_cursor' => 0,
            'created_at'          => current_time( 'mysql' ),
            'updated_at'          => current_time( 'mysql' ),
        );
        $this->save_state();
    }

    /**
     * 加载状态 (双写持久化: transient + JSON文件)
     *
     * @return bool
     */
    public function load_state() : bool {
        // 优先从 transient 读取
        $cached = get_transient( $this->get_transient_key() );
        if ( false !== $cached && is_array( $cached ) ) {
            $this->state = $cached;
            return true;
        }

        // fallback: 从 JSON 文件读取
        $path = $this->get_json_path();
        if ( file_exists( $path ) ) {
            $json = file_get_contents( $path );
            $data = json_decode( $json, true );
            if ( is_array( $data ) ) {
                // v18.11: 状态 schema 迁移
                $data = $this->maybe_migrate( $data );
                $this->state = $data;
                // 回填 transient
                set_transient( $this->get_transient_key(), $data, DAY_IN_SECONDS );
                return true;
            }
        }

        return false;
    }

    /**
     * 状态 schema 迁移 (v18.11 新增)
     *
     * 根据状态文件中的 schema_version 字段，逐步迁移到当前版本。
     * v1 (无版本号) → v2: 添加 schema_version 字段。
     *
     * @param array $data 从文件/transient 加载的状态数据。
     * @return array 迁移后的状态数据。
     */
    private function maybe_migrate(array $data) : mixed {
        $version = isset( $data['schema_version'] ) ? (int) $data['schema_version'] : 1;

        // v1 → v2: 添加 schema_version 字段
        if ( $version < 2 ) {
            $data['schema_version'] = 2;
            $version = 2;
        }

        // 未来迁移: if ( $version < 3 ) { ... }

        return $data;
    }

    /**
     * 保存状态 (双写)
     *
     * @return self
     */
    public function save_state() : mixed {
        $this->state['updated_at'] = current_time( 'mysql' );

        // v18.10.3: 序列化state, 检查大小
        $json = wp_json_encode( $this->state, JSON_UNESCAPED_UNICODE );
        $json_size = strlen( $json );

        // v18.10.3: transient限制1MB, 超过则只存JSON文件 (避免transient溢出导致序列化失败)
        if ( $json_size < 1048576 ) {
            set_transient( $this->get_transient_key(), $this->state, 7 * DAY_IN_SECONDS );
        } else {
            // 大state: transient只存轻量元数据, 完整数据存JSON文件
            $lite_state = $this->state;
            if ( isset( $lite_state['sections'] ) ) {
                $lite_state['sections'] = array(); // 清空大字段
            }
            if ( isset( $lite_state['section_outputs'] ) ) {
                $lite_state['section_outputs'] = array();
            }
            if ( isset( $lite_state['step_outputs'] ) ) {
                $lite_state['step_outputs'] = array();
            }
            if ( isset( $lite_state['draft_markdown'] ) && strlen( $lite_state['draft_markdown'] ) > 5000 ) {
                $lite_state['draft_markdown'] = mb_substr( $lite_state['draft_markdown'], 0, 5000 ) . '...[截断,完整内容见JSON文件]';
            }
            if ( isset( $lite_state['draft_html'] ) && strlen( $lite_state['draft_html'] ) > 5000 ) {
                $lite_state['draft_html'] = '';
            }
            set_transient( $this->get_transient_key(), $lite_state, 7 * DAY_IN_SECONDS );
        }

        // v18.11: 原子写入 JSON 文件 (完整数据, 长期备份)
        // 使用临时文件 + rename 确保文件不会出现半写入状态，加 LOCK_EX 防止并发竞态。
        $path = $this->get_json_path();
        try {
            BookSecurity::atomic_write(
                $path,
                wp_json_encode( $this->state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
            );
        } catch ( \RuntimeException $e ) {
            // 原子写入失败时记录日志，但不中断流程（transient 已写入）。
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[BookFactory] 状态文件原子写入失败: ' . $e->getMessage() );
            }
        }

        // 触发状态变更事件 (S11 实时同步)
        do_action( 'linked3/book/state_changed', $this->project_id, $this->state );

        return $this;
    }

    /**
     * 获取状态字段
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        return isset( $this->state[ $key ] ) ? $this->state[ $key ] : $default;
    }

    /**
     * 设置状态字段 (链式)
     *
     * @param string $key
     * @param mixed  $value
     * @return self
     */
    public function set(string $key, $value): self {
        $this->state[ $key ] = $value;
        return $this;
    }

    /**
     * 记录步骤历史
     *
     * @param string $step_id
     * @param string $status  start|complete|failed
     * @param array  $extra
     * @return self
     */
    public function log_step(string $step_id, string $status, array $extra = array()): self {
        $this->state['step_history'][] = array_merge( array(
            'step_id'   => $step_id,
            'status'    => $status,
            'timestamp' => current_time( 'mysql' ),
            'microtime' => microtime( true ),
        ), $extra );
        return $this;
    }

    /**
     * 设置项目状态 (R1修复: 补全缺失方法)
     *
     * @param string $status
     * @return self
     */
    public function set_status(string $status): self {
        $this->state['status'] = $status;
        return $this;
    }

    /**
     * 记录成本
     *
     * @param string $step_id
     * @param int    $tokens_in
     * @param int    $tokens_out
     * @param float  $cost
     * @return self
     */
    public function log_cost(string $step_id, int $tokens_in, int $tokens_out, float $cost): self {
        $this->state['cost_log'][] = array(
            'step_id'    => $step_id,
            'tokens_in'  => $tokens_in,
            'tokens_out' => $tokens_out,
            'cost'       => $cost,
            'timestamp'  => current_time( 'mysql' ),
        );
        return $this;
    }

    /**
     * 获取 transient key
     *
     * @return string
     */
    private function get_transient_key() : string {
        return 'linked3_book_project_' . $this->project_id;
    }

    /**
     * 获取 JSON 文件路径
     *
     * @return string
     */
    private function get_json_path(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/linked3-book-projects/' . $this->project_id . '.json';
    }

    /**
     * 获取项目实例 (S15 多项目隔离)
     *
     * @param string $project_id
     * @return self|null
     */
    public static function get_project(string $project_id): ?self {
        // v18.11: 校验 project_id 防止路径遍历。
        if ( false === BookSecurity::validate_project_id( $project_id ) ) {
            return null;
        }
        if ( isset( self::$projects[ $project_id ] ) ) {
            return self::$projects[ $project_id ];
        }
        $state = new self( $project_id );
        if ( $state->load_state() ) {
            return $state;
        }
        return null;
    }

    /**
     * 删除项目
     *
     * @return bool
     */
    public function delete() : bool {
        delete_transient( $this->get_transient_key() );
        $path = $this->get_json_path();
        if ( file_exists( $path ) ) {
            unlink( $path );
        }
        unset( self::$projects[ $this->project_id ] );
        return true;
    }
}
