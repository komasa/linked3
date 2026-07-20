<?php

declare(strict_types=1);
/**
 * Linked3 Book Ajax Actions — 写书工厂Ajax端点
 *
 * 方案: S9 (G1) + H3约束(独立nonce, 7天有效)
 * 公理: 前端控制台 ↔ 后端工厂 的唯一接口
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) exit;

class BookAjaxActions {

    /**
     * 注册所有Ajax端点
     */
    public static function register() : void {
        // 启动工厂
        add_action( 'wp_ajax_linked3_book_factory_start', array( __CLASS__, 'start' ) );
        // 查询进度 (独立nonce, 7天有效, H3约束)
        add_action( 'wp_ajax_linked3_book_factory_progress', array( __CLASS__, 'progress' ) );
        add_action( 'wp_ajax_nopriv_linked3_book_factory_progress', array( __CLASS__, 'progress' ) );
        // 章节级重生成 (Q4盲区)
        add_action( 'wp_ajax_linked3_book_factory_regenerate_section', array( __CLASS__, 'regenerate_section' ) );
        // 下载书稿 (Q6盲区)
        add_action( 'wp_ajax_linked3_book_factory_download', array( __CLASS__, 'download' ) );
        // 断线重连恢复 (Q2盲区)
        add_action( 'wp_ajax_linked3_book_factory_resume', array( __CLASS__, 'resume' ) );
        // 大纲回退 (Q5盲区)
        add_action( 'wp_ajax_linked3_book_factory_rollback_outline', array( __CLASS__, 'rollback_outline' ) );
        // R2修复: 分步执行单节扩写 (前端轮询触发)
        add_action( 'wp_ajax_linked3_book_factory_run_step', array( __CLASS__, 'run_step' ) );
        // v18.11: 异步启动端点 (后台自动链式执行, 前端只需轮询进度)
        add_action( 'wp_ajax_linked3_book_factory_start_async', array( __CLASS__, 'start_async' ) );
        add_action( 'wp_ajax_linked3_book_factory_cancel_async', array( __CLASS__, 'cancel_async' ) );
        // N1+N3: 提示词管理 (获取/保存/预览)
        add_action( 'wp_ajax_linked3_book_factory_get_prompts', array( __CLASS__, 'get_prompts' ) );
        add_action( 'wp_ajax_linked3_book_factory_save_prompt', array( __CLASS__, 'save_prompt' ) );
        add_action( 'wp_ajax_linked3_book_factory_preview_prompt', array( __CLASS__, 'preview_prompt' ) );

        // v19.1: MetaMother 元母体端点 (嵌入自 genesis_meta2_M2_G3 母版)
        add_action( 'wp_ajax_linked3_book_meta_classify', array( __CLASS__, 'meta_classify' ) );
        add_action( 'wp_ajax_linked3_book_meta_prototype', array( __CLASS__, 'meta_prototype' ) );
        add_action( 'wp_ajax_linked3_book_meta_extract', array( __CLASS__, 'meta_extract' ) );
        add_action( 'wp_ajax_linked3_book_meta_create', array( __CLASS__, 'meta_create' ) );
        add_action( 'wp_ajax_linked3_book_meta_info', array( __CLASS__, 'meta_info' ) );
        add_action( 'wp_ajax_linked3_book_meta_prototypes', array( __CLASS__, 'meta_prototypes' ) );

        // wp_cron 钩子 (H1约束: 分步执行, 保留作为兜底)
        add_action( 'linked3_book_factory_run_pipeline', array( '\Linked3\Classes\BookFactory\BookFactory', 'run_pipeline' ) );
    }

    /**
     * 启动写书工厂
     */
        public static function start() : mixed { return BookAjaxPipeline::start(); }

    /**
     * v18.11: 异步启动写书工厂
     *
     * 创建项目后自动调度后台链式执行, 前端只需轮询 progress 端点。
     * 解决 v18.10.3 中前端需反复调用 run_step 的问题。
     */
        public static function start_async() : mixed { return BookAjaxPipeline::start_async(); }

    /**
     * v18.11: 取消异步执行
     */
        public static function cancel_async() { return BookAjaxPipeline::cancel_async(); }

    /**
     * 查询进度
     */
        public static function progress() { return BookAjaxPipeline::progress(); }

    /**
     * 章节级重生成 (Q4盲区)
     */
        public static function regenerate_section() { return BookAjaxPipeline::regenerate_section(); }

    /**
     * 下载书稿 (Q6盲区)
     */
        public static function download() { return BookAjaxPipeline::download(); }

    /**
     * 断线重连恢复 (Q2盲区)
     */
        public static function resume() { return BookAjaxPipeline::resume(); }

    /**
     * 大纲回退 (Q5盲区)
     */
        public static function rollback_outline() { return BookAjaxPipeline::rollback_outline(); }

    /**
     * R2修复: 分步执行单节扩写
     * 前端轮询触发, 每次执行一节, 避免PHP超时
     */
        public static function run_step() { return BookAjaxPipeline::run_step(); }

    /**
     * N1+N3: 获取所有提示词 (供前端显示/编辑)
     */
        public static function get_prompts() { return BookAjaxPrompts::get_prompts(); }

    /**
     * N1+N3: 保存用户修改的提示词
     */
        public static function save_prompt() { return BookAjaxPrompts::save_prompt(); }

    /**
     * N3+N5: 预览提示词 (根据书名/类型/模式填充变量)
     */
        public static function preview_prompt() { return BookAjaxPrompts::preview_prompt(); }

    /**
     * 生成进度查询nonce (7天有效, H3约束)
     *
     * @param string $project_id
     * @return string
     */
    public static function generate_progress_nonce( $project_id ) {
        $action = 'linked3_book_progress_' . $project_id;
        return wp_create_nonce( $action );
    }

    // ════════════════════════════════════════════════════════════════
    // v19.1: MetaMother 元母体端点 (嵌入自 genesis_meta2_M2_G3 母版)
    // ════════════════════════════════════════════════════════════════

    /**
     * v19.1: 第一阶 — 探索方式分类
     * 根据探索意图推荐最佳探索原型。
     */
    public static function meta_classify() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        $intent = sanitize_text_field( $_POST['intent'] ?? '' );

        if ( empty( $intent ) ) {
            wp_send_json_error( array( 'message' => __('探索意图不能为空', 'linked3-ai') ), 400 );
        }

        try {
            $mother = new BookMetaMother();
            $result = $mother->classify_exploration( $intent );

            wp_send_json_success( $result );
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( '[linked3 meta_mother] classify异常: ' . $e->getMessage() );
            }
            wp_send_json_error( array(
                'message' => __('分类异常: ', 'linked3-ai') . BookSecurity::sanitize_error_message( $e->getMessage() ),
            ) );
        }
    }

    /**
     * v19.1: 第二阶 — 系统原型生成
     * 根据原型key生成完整探索系统配置。
     */
    public static function meta_prototype() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        $prototype_key = sanitize_text_field( $_POST['prototype_key'] ?? 'book' );

        try {
            $prototype = BookExplorationPrototypes::get( $prototype_key );

            if ( ! $prototype ) {
                wp_send_json_error( array( 'message' => __('原型不存在: ', 'linked3-ai') . $prototype_key ), 404 );
            }

            wp_send_json_success( array(
                'prototype' => $prototype,
                'prompt_overrides' => self::get_prototype_prompt_overrides( $prototype_key ),
            ) );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array(
                'message' => __('原型生成异常: ', 'linked3-ai') . BookSecurity::sanitize_error_message( $e->getMessage() ),
            ) );
        }
    }

    /**
     * v19.1: 第三阶 — 元规律提炼
     * 评估探索结果的元规律合规性。
     */
    public static function meta_extract() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        $result_text = wp_unslash( $_POST['result_text'] ?? '' );

        if ( empty( $result_text ) ) {
            wp_send_json_error( array( 'message' => __('探索结果不能为空', 'linked3-ai') ), 400 );
        }

        try {
            $mother = new BookMetaMother();
            $assessment = $mother->extract_meta_laws( $result_text );

            wp_send_json_success( $assessment );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array(
                'message' => __('元规律提炼异常: ', 'linked3-ai') . BookSecurity::sanitize_error_message( $e->getMessage() ),
            ) );
        }
    }

    /**
     * v19.1: 第四阶 — 新系统创造
     * 按六步创造法生成新探索系统。
     */
    public static function meta_create() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        $system_name = sanitize_text_field( $_POST['system_name'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( empty( $system_name ) ) {
            wp_send_json_error( array( 'message' => __('系统名称不能为空', 'linked3-ai') ), 400 );
        }

        try {
            $mother = new BookMetaMother();
            $new_system = $mother->create_new_system( $system_name, $description );

            wp_send_json_success( $new_system );
        } catch ( \Throwable $e ) {
            wp_send_json_error( array(
                'message' => __('新系统创造异常: ', 'linked3-ai') . BookSecurity::sanitize_error_message( $e->getMessage() ),
            ) );
        }
    }

    /**
     * v19.1: 获取元母体元信息。
     */
    public static function meta_info() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        wp_send_json_success( array(
            'version'      => BookMetaMother::META_VERSION,
            'meta_laws'    => BookMetaMother::META_LAWS,
            'meta_stages'  => BookMetaMother::META_STAGES,
            'prototypes'   => BookExplorationPrototypes::get_label_map(),
            'core_nucleus' => '探索方式分类引擎 × 系统原型生成引擎 × 元规律提炼引擎 × 新系统创造引擎',
        ) );
    }

    /**
     * v19.1: 获取所有探索原型列表。
     */
    public static function meta_prototypes() : void {
        check_ajax_referer( 'linked3_book_factory', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __('权限不足', 'linked3-ai') ), 403 );
        }

        wp_send_json_success( array(
            'prototypes' => BookExplorationPrototypes::get_all(),
            'by_category' => BookExplorationPrototypes::get_by_category(),
        ) );
    }

    /**
     * v19.1: 获取原型的提示词覆盖配置。
     *
     * @param string $prototype_key 原型key。
     * @return array
     */
    private static function get_prototype_prompt_overrides( $prototype_key ) {
        $overrides = array(
            'book' => array(
                'step4_expand' => '请以系统化、结构化、逻辑严密的方式扩写',
            ),
            'experimental' => array(
                'step4_expand' => '请以严谨、可复现、数据驱动的方式扩写，提出假设并设计实验验证',
            ),
            'observational' => array(
                'step4_expand' => '请以客观、细致、模式导向的方式扩写，系统观察并记录现象',
            ),
            'deductive' => array(
                'step4_expand' => '请以严密、形式化、无矛盾的方式扩写，从公理出发逻辑推演',
            ),
            'meditative' => array(
                'step4_expand' => '请以内观、觉知、非二元的方式扩写，通过静心内观发现真理',
            ),
            'dialogic' => array(
                'step4_expand' => '请以追问、辩证、启发式的方式扩写，通过对话揭示真理',
            ),
            'practical' => array(
                'step4_expand' => '请以实用、迭代、效果导向的方式扩写，在实践中发现真理',
            ),
            'artistic' => array(
                'step4_expand' => '请以感性、象征、多义性的方式扩写，通过艺术表达发现真理',
            ),
            'computational' => array(
                'step4_expand' => '请以形式化、可计算、可复现的方式扩写，通过计算模拟发现真理',
            ),
            'synthetic' => array(
                'step4_expand' => '请以多维、整合、系统化的方式扩写，多维度并行发现真理',
            ),
        );

        return $overrides[ $prototype_key ] ?? $overrides['book'];
    }
}

// 注册Ajax端点
add_action( 'init', array( '\Linked3\Classes\BookFactory\BookAjaxActions', 'register' ) );
