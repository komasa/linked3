<?php
/**
 * Linked3 Book Factory — 写书工厂门面 (v19.0: 外观模式)
 *
 * v18.x: 上帝类, 1420 行, 承担流程编排+AI调用+成本核算+草稿重建等多重职责
 * v19.0: 保留为向后兼容的外观类 (Facade), 静态 API 不变,
 *        新代码应使用 Linked3_Book_Pipeline_Orchestrator (依赖注入, 可测试)
 *
 * 方案: S5 (G1) + S15 (G2内置State) + S16 (G2断点续作) + S17 (G2速率控制)
 * 公理: 工厂门面 — 对外暴露 create_book()，内部协调6步管线
 *
 * @package Linked3\BookFactory
 * @since   18.5.0
 * @deprecated 19.0 新代码请使用 Linked3_Book_Pipeline_Orchestrator
 */

namespace Linked3\Classes\BookFactory;



    use \Linked3\Classes\BookFactory\Traits\Linked3_Outline_Merger;
    use \Linked3\Classes\BookFactory\Traits\Linked3_Section_Expander;
    use \Linked3\Classes\BookFactory\Traits\Linked3_Review_Linker;
    use \Linked3\Classes\BookFactory\Traits\Linked3_Cost_Tracker;



if ( ! defined( 'ABSPATH' ) ) exit;
// 显式加载 Trait (自动加载器无法解析 trait 的 Linked3_X_Y 命名)
$trait_dir = __DIR__ . '/Traits/';
require_once $trait_dir . 'trait-linked3-outline-merger.php';
require_once $trait_dir . 'trait-linked3-section-expander.php';
require_once $trait_dir . 'trait-linked3-review-linker.php';
require_once $trait_dir . 'trait-linked3-cost-tracker.php';

class Linked3_Book_Factory {
    /**
     * v19.0: 获取 Pipeline_Orchestrator 实例 (推荐新代码使用)。
     *
     * @return Linked3_Book_Pipeline_Orchestrator
     */
    public static function orchestrator() : mixed {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new Linked3_Book_Pipeline_Orchestrator();
        }
        return $instance;
    }

    /** @var array 管线配置 (从 book.yaml 加载) */
    private $pipeline_config = null;

    /** @var Linked3_Book_Project_State 项目状态 */
    private $state = null;

    /** @var array 路由配置 */
    private $route = null;

    /** @var float 上次AI调用时间戳 (S17 速率控制) */
    private $last_api_call = 0;

    /**
     * 启动写书工厂
     *
     * @param array $params {book_title, type, mode, iteration_level, options}
     * @return array|WP_Error {project_id, status}
     */
    public static function create_book( $params ) : mixed {
        $params = wp_parse_args( $params, array(
            'book_title'      => '',
            'type'            => 'book',
            'mode'            => 'ai',
            'iteration_level' => 'standard',
            'options'         => array(),
        ) );

        if ( empty( $params['book_title'] ) ) {
            return new WP_Error( 'empty_title', '书名不能为空' );
        }

        // 路由查询 (S3)
        $route = Linked3_Type_Mode_Router::route( $params['type'], $params['mode'] );

        // 初始化项目状态 (S2 + S15)
        $state = new Linked3_Book_Project_State( '', array(
            'book_title'      => $params['book_title'],
            'type'            => $params['type'],
            'mode'            => $params['mode'],
            'iteration_level' => $params['iteration_level'],
            'route'           => $route,
            'options'         => $params['options'],
        ) );

        $project_id = $state->project_id;

        // v18.7: 零AI调用 — 只初始化状态, 不执行任何AI调用
        // 前端通过 run_step 分步触发 step1→step2→step3→step4→step5→step6
        // 每次Ajax只执行1次AI调用, 避免PHP超时
        $state->set_status( Linked3_Book_Project_State::STATUS_IDLE );
        $state->set( 'current_step', 'step1_demo' );
        $state->set( 'outline_iter_cursor', 0 ); // step3大纲迭代游标
        $state->set( 'expand_chapter_cursor', 0 );
        $state->set( 'expand_section_cursor', 0 );
        $state->save_state();

        return array(
            'project_id'     => $project_id,
            'status'         => 'idle',
            'progress_nonce' => Linked3_Book_Ajax_Actions::generate_progress_nonce( $project_id ),
        );
    }

    /**
     * 执行管线 (wp_cron 触发)
     *
     * @param string $project_id
     */
    public static function run_pipeline( $project_id ) : void {
        $factory = new self();
        $factory->execute( $project_id );
    }

    /**
     * v18.7: 智能路由分步执行 (每次只1次AI调用, 避免PHP超时)
     *
     * 根据 current_step 路由到对应步骤:
     *   step1_demo    → 执行1次AI演示, 完成后→step2_explore
     *   step2_explore → 执行1次AI探索, 完成后→step3_outline
     *   step3_outline → 执行1次大纲迭代, 迭代完→step4_expand
     *   step4_expand  → 执行1节扩写, 全部完→step5_complete
     *   step5_complete→ 拼接书稿(零AI), →step6_review
     *   step6_review  → 执行1次AI审阅, →done
     *
     * @param string $project_id
     * @return array|WP_Error
     */
    public static function run_step( $project_id ) {
        $state = Linked3_Book_Project_State::get_project( $project_id );
        if ( ! $state ) {
            return new WP_Error( 'no_project', '项目不存在' );
        }

        $factory = new self();
        $factory->state = $state;
        $factory->pipeline_config = $factory->load_pipeline_config();
        $factory->route = $state->get( 'route' );

        $current_step = $state->get( 'current_step' );
        $status = $state->get( 'status' );

        // 已完成或失败, 不再执行
        if ( $status === 'done' ) {
            return array( 'done' => true, 'message' => __('已完成', 'linked3-ai') );
        }
        if ( $status === 'failed' ) {
            return new WP_Error( 'already_failed', '项目已失败' );
        }

        // v18.11: 通过步骤注册表路由, 替代 switch-case 硬编码。
        // 第三方插件可通过 linked3_book_register_step 钩子注册自定义步骤。
        $step = Linked3_Book_Step_Registry::get_step( $current_step );

        if ( $step instanceof Linked3_Book_Step_Interface ) {
            return $step->execute( $state, $factory );
        }

        // 向后兼容: 如果注册表中没有, 回退到 switch-case (处理 done 等特殊状态)。
        switch ( $current_step ) {
            case 'done':
                return array( 'done' => true, 'message' => __('已完成', 'linked3-ai') );
            default:
                return new WP_Error( 'unknown_step', '未知步骤: ' . $current_step );
        }
    }

    /**
     * v18.7: 执行step1演示 (1次AI调用)
     */
        public function execute_step1_demo( $state ) { return Linked3_Book_Factory_Steps::execute_step1_demo($state); }

    /**
     * v18.7: 执行step2探索 (1次AI调用)
     */
        public function execute_step2_explore( $state ) { return Linked3_Book_Factory_Steps::execute_step2_explore($state); }

    /**
     * v18.7: 执行step3单次大纲迭代 (1次AI调用)
     */
        public function execute_step3_outline_iter( $state ) { return Linked3_Book_Factory_Steps::execute_step3_outline_iter($state); }

    /**
     * v18.7: 执行step4单节扩写 (1次AI调用)
     */
        public function execute_step4_expand_one( $state ) { return Linked3_Book_Factory_Steps::execute_step4_expand_one($state); }

    /**
     * v18.7: 执行step5拼接 (零AI调用)
     */
        public function execute_step5_complete( $state ) { return Linked3_Book_Factory_Steps::execute_step5_complete($state); }

    /**
     * v18.7: 执行step6审阅 (1次AI调用)
     */
        public function execute_step6_review( $state ) { return Linked3_Book_Factory_Steps::execute_step6_review($state); }

    /**
     * 执行6步管线
     *
     * @param string $project_id
     */
    private function execute( $project_id ) : void {
        $this->state = Linked3_Book_Project_State::get_project( $project_id );
        if ( ! $this->state ) {
            return;
        }

        // 加载管线配置
        $this->pipeline_config = $this->load_pipeline_config();
        $this->route = $this->state->get( 'route' );

        $current_step = $this->state->get( 'current_step' );

        // 6步顺序执行 (可中断恢复)
        $steps = array(
            'step1_demo'     => array( 'optional' => true,  'method' => 'pipeline_step1_demo' ),
            'step2_explore'  => array( 'optional' => true,  'method' => 'pipeline_step2_explore' ),
            'step3_outline'  => array( 'optional' => false, 'method' => 'pipeline_step3_outline' ),
            'step4_expand'   => array( 'optional' => false, 'method' => 'pipeline_step4_expand' ),
            'step5_complete' => array( 'optional' => false, 'method' => 'pipeline_step5_complete' ),
            'step6_review'   => array( 'optional' => true,  'method' => 'pipeline_step6_review' ),
        );

        $resume = ( $current_step !== 'idle' && $current_step !== '' );
        $started = false;

        foreach ( $steps as $step_id => $config ) {
            // 断点续作: 跳过已完成的步骤
            if ( $resume && ! $started ) {
                if ( $step_id === $current_step ) {
                    $started = true;
                } else {
                    continue;
                }
            }

            // 预算检查 (S20)
            if ( $this->state->is_budget_exceeded() ) {
                $this->state->set_status( Linked3_Book_Project_State::STATUS_PAUSED );
                $this->state->log_step( $step_id, 'skipped', '预算超限' );
                return;
            }

            // 可选步骤: 检查用户选项
            if ( $config['optional'] && ! $this->is_step_enabled( $step_id ) ) {
                $this->state->log_step( $step_id, 'skipped', '用户禁用' );
                continue;
            }

            // 执行步骤
            $this->state->set_status( $this->step_to_status( $step_id ) );
            $this->state->set( 'current_step', $step_id );
            $this->state->save_state();

            do_action( 'linked3/book/step_start', $project_id, $step_id );

            try {
                $result = call_user_func( array( $this, $config['method'] ) );
                $this->state->log_step( $step_id, 'success', $result );
                do_action( 'linked3/book/step_complete', $project_id, $step_id, $result );
            } catch ( \Throwable $e ) {
                $this->state->log_step( $step_id, 'failed', $e->getMessage() );
                $this->state->set_status( Linked3_Book_Project_State::STATUS_FAILED );
                $this->state->save_state();
                do_action( 'linked3/book/step_failed', $project_id, $step_id, $e->getMessage() );
                return;
            }

            $this->state->save_state();
        }

        $this->state->set_status( Linked3_Book_Project_State::STATUS_DONE );
        $this->state->set( 'current_step', 'done' );
        $this->state->save_state();
        do_action( 'linked3/book/progress', $project_id, 'done' );
    }

    /**
     * Step 1: AI演示
     */
    public function pipeline_step1_demo() : array {
        $book_title = $this->state->get( 'book_title' );
        $type_label = Linked3_Type_Mode_Router::get_type_label( $this->state->get( 'type' ) );

        $prompt = "请为《{$book_title}》这{$this->route['type_unit']}{$type_label}模拟3位读者可能提出的问题，演示书稿价值。\n\n";
        $prompt .= "输出格式:\n读者1: 问题\n读者2: 问题\n读者3: 问题";

        $response = $this->call_ai_with_rate_limit( $prompt );
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $this->state->set( 'demo_questions', $response['content'] );
        return array( 'demo_questions' => $response['content'] );
    }

    /**
     * Step 2: 探索主题
     */
    public function pipeline_step2_explore() : array {
        $book_title = $this->state->get( 'book_title' );
        $type_label = Linked3_Type_Mode_Router::get_type_label( $this->state->get( 'type' ) );

        $prompt = "请探索《{$book_title}》这{$this->route['type_unit']}{$type_label}的核心主题、目标读者、价值主张。\n\n";
        $prompt .= "输出格式:\n核心主题: ...\n目标读者: ...\n价值主张: ...\n关键章节方向: ...";

        $response = $this->call_ai_with_rate_limit( $prompt );
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $this->state->set( 'exploration', $response['content'] );
        return array( 'exploration' => $response['content'] );
    }

    /**
     * Step 3: 撰写大纲 (N1外置提示词 + N2就近复用)
     */
    public function pipeline_step3_outline() : array {
        $level = $this->state->get( 'iteration_level', 'standard' );
        $levels = Linked3_Type_Mode_Router::get_all_iteration_levels();
        $max_iter = isset( $levels[ $level ]['iterations'] ) ? $levels[ $level ]['iterations'] : 3;

        // N1: 从外置提示词管理器获取提示词模板
        $book_title = $this->state->get( 'book_title' );
        $type = $this->state->get( 'type' );
        $mode = $this->state->get( 'mode' );
        $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );

        $versions = array();
        for ( $i = 1; $i <= $max_iter; $i++ ) {
            // N1: 使用外置提示词, 填充变量
            $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step3_outline', $vars, $i );

            // 记录当前提示词到State (N4: 前端可见)
            $this->state->set( 'current_prompt', $prompt );
            $this->state->set( 'current_prompt_step', 'step3_outline' );
            $this->state->set( 'current_prompt_iter', $i );
            $this->state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt );
            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            $parsed = $this->parse_outline( $response['content'] );
            $versions[] = $parsed;

            $this->state->set( 'outline_versions', $versions );
            $this->state->save_state();

            do_action( 'linked3/book/progress', $this->state->project_id, "outline_iter_{$i}/{$max_iter}" );
        }

        // 合并所有版本 (S6)
        $final_outline = $this->merge_outlines( $versions );

        // 缓存到State (S12: 避免扩写时重复加载)
        $this->state->set( 'final_outline', $final_outline );
        $this->state->set( 'chapters', $final_outline['chapters'] );

        return array(
            'outline' => $final_outline,
            'iterations' => $max_iter,
        );
    }

    /**
     * Step 4: 扩写小节 (N1外置提示词 + N2就近复用)
     */
    public function pipeline_step4_expand() : array {
        $chapters = $this->state->get( 'chapters' );
        $sections = $this->state->get( 'sections', array() );
        $total = 0;
        $done = 0;

        // N1: 准备变量上下文
        $book_title = $this->state->get( 'book_title' );
        $type = $this->state->get( 'type' );
        $mode = $this->state->get( 'mode' );
        $level = $this->state->get( 'iteration_level', 'standard' );
        $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );

        // 计算总数
        foreach ( $chapters as $ch ) {
            $total += count( $ch['sections'] );
        }

        foreach ( $chapters as $ch_idx => $chapter ) {
            if ( ! isset( $sections[ $ch_idx ] ) ) {
                $sections[ $ch_idx ] = array();
            }

            foreach ( $chapter['sections'] as $sec_idx => $section ) {
                // 断点续作: 跳过已完成的
                if ( isset( $sections[ $ch_idx ][ $sec_idx ] ) && ! empty( $sections[ $ch_idx ][ $sec_idx ] ) ) {
                    $done++;
                    continue;
                }

                // 构建上下文摘要 (S7)
                $context_summary = '';
                if ( $sec_idx > 0 && isset( $sections[ $ch_idx ][ $sec_idx - 1 ] ) ) {
                    $context_summary = $this->build_context_summary(
                        $sections[ $ch_idx ][ $sec_idx - 1 ],
                        $this->state->get( 'context_summary_length', 80 )
                    );
                }

                // N1: 使用外置提示词, 注入章节/小节/上下文变量
                $section_vars = array_merge( $vars, array(
                    'chapter_title'  => $chapter['title'],
                    'section_title'  => $section['title'],
                    'context_summary'=> $context_summary,
                    'chapter_index'  => $ch_idx + 1,
                    'section_index'  => $sec_idx + 1,
                ) );
                $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step4_expand', $section_vars, 1 );

                // N4: 记录当前提示词到State (前端可见)
                $this->state->set( 'current_prompt', $prompt );
                $this->state->set( 'current_prompt_step', 'step4_expand' );
                $this->state->set( 'current_chapter_idx', $ch_idx );
                $this->state->set( 'current_section_idx', $sec_idx );
                $this->state->save_state();

                $response = $this->call_ai_with_rate_limit( $prompt );
                if ( is_wp_error( $response ) ) {
                    throw new Exception( $response->get_error_message() );
                }

                $content = $this->sanitize_section_content( $response['content'] );
                $sections[ $ch_idx ][ $sec_idx ] = $content;

                $this->state->set( 'sections', $sections );
                $this->state->save_state();

                $done++;
                do_action( 'linked3/book/progress', $this->state->project_id, "expand_{$done}/{$total}" );
            }
        }

        return array( 'sections_count' => $done );
    }

    /**
     * Step 5: 完成初稿 (拼接, S4)
     */
    public function pipeline_step5_complete() : array {
        $chapters = $this->state->get( 'chapters' );
        $sections = $this->state->get( 'sections' );
        $book_title = $this->state->get( 'book_title' );

        // v18.9.2: 如果大纲为空, 用step3的AI原始输出作为正文兜底
        if ( empty( $chapters ) ) {
            $outline_versions = $this->state->get( 'outline_versions', array() );
            $raw_outline = '';
            if ( ! empty( $outline_versions ) ) {
                $last_version = end( $outline_versions );
                if ( isset( $last_version['raw_content'] ) ) {
                    $raw_outline = $last_version['raw_content'];
                }
            }
            // 用step2探索内容或step1演示内容兜底
            if ( empty( $raw_outline ) ) {
                $raw_outline = $this->state->get( 'exploration', '' ) ?: $this->state->get( 'demo_questions', '' );
            }
            $chapters = array(
                array(
                    'title' => $book_title,
                    'sections' => array(
                        array( 'title' => '正文', 'content' => $raw_outline ?: '（大纲生成异常,请重试）' ),
                    ),
                ),
            );
            $sections = array( array( 0 => $raw_outline ?: '' ) );
            $this->state->set( 'chapters', $chapters );
            $this->state->set( 'sections', $sections );
        }

        // 合并章节内容
        $full_chapters = array();
        foreach ( $chapters as $ch_idx => $chapter ) {
            $ch_data = array(
                'title'    => $chapter['title'] ?? '未命名章节',
                'sections' => array(),
            );
            $chapter_sections = $chapter['sections'] ?? array();
            foreach ( $chapter_sections as $sec_idx => $section ) {
                $ch_data['sections'][] = array(
                    'title'   => $section['title'] ?? '未命名小节',
                    'content' => isset( $sections[ $ch_idx ][ $sec_idx ] ) ? $sections[ $ch_idx ][ $sec_idx ] : '',
                );
            }
            $full_chapters[] = $ch_data;
        }

        // v18.9.2修复: output_template_config兜底
        $template = isset( $this->route['output_template_config'] ) ? $this->route['output_template_config'] : array();
        if ( empty( $template ) ) {
            $template = array( 'chapter_prefix' => '第', 'chapter_suffix' => '章' );
        }
        $result = Linked3_Section_Stitcher::stitch( $full_chapters, $template, $book_title );
        $files = Linked3_Section_Stitcher::save_to_file( $this->state->project_id, $result['markdown'], $result['html'] );

        $this->state->set( 'draft_markdown', $result['markdown'] );
        $this->state->set( 'draft_html', $result['html'] );
        $this->state->set( 'draft_files', $files );

        return array(
            'md_url'   => $files['md_url'],
            'html_url' => $files['html_url'],
        );
    }

    /**
     * Step 6: 阅读修改 (联动审阅, S8+S13)
     */
    public function pipeline_step6_review() : array {
        $draft = $this->state->get( 'draft_markdown' );
        $book_title = $this->state->get( 'book_title' );

        $suggestions = array();

        // 联动SEO模块 (S8)
        $seo = $this->call_seo_review( $draft );
        if ( ! is_wp_error( $seo ) ) {
            $suggestions['seo'] = $seo;
        }

        // 联动改写模块 (S8)
        $rewrite = $this->call_rewrite_review( $draft );
        if ( ! is_wp_error( $rewrite ) ) {
            $suggestions['rewrite'] = $rewrite;
        }

        // 联动标题模块 (S8)
        $title = $this->call_title_review( $draft, $book_title );
        if ( ! is_wp_error( $title ) ) {
            $suggestions['title'] = $title;
        }

        // 应用建议 (增量拼接, S13)
        $reviewed = $this->apply_review_suggestions( $draft, $suggestions );

        $this->state->set( 'reviewed_markdown', $reviewed );
        $this->state->set( 'review_suggestions', $suggestions );

        // 重新保存文件
        $html = Linked3_Section_Stitcher::markdown_to_html( $reviewed );
        $files = Linked3_Section_Stitcher::save_to_file( $this->state->project_id, $reviewed, $html );
        $this->state->set( 'draft_files', $files );

        return array(
            'suggestions' => array_keys( $suggestions ),
            'md_url'      => $files['md_url'],
        );
    }

    /**
     * 章节级重生成 (Q4盲区)
     *
     * @param string $project_id
     * @param int    $chapter_index
     * @param int    $section_index
     * @return array|WP_Error
     */
    public static function regenerate_section( $project_id, $chapter_index, $section_index ) {
        $state = Linked3_Book_Project_State::get_project( $project_id );
        if ( ! $state ) {
            return new WP_Error( 'no_project', '项目不存在' );
        }

        $factory = new self();
        $factory->state = $state;
        $factory->route = $state->get( 'route' );
        $factory->pipeline_config = $factory->load_pipeline_config();

        $chapters = $state->get( 'chapters' );
        if ( ! isset( $chapters[ $chapter_index ] ) ) {
            return new WP_Error( 'no_chapter', '章节不存在' );
        }
        $chapter = $chapters[ $chapter_index ];
        if ( ! isset( $chapter['sections'][ $section_index ] ) ) {
            return new WP_Error( 'no_section', '小节不存在' );
        }
        $section = $chapter['sections'][ $section_index ];

        // 构建上下文摘要
        $context_summary = '';
        $sections = $state->get( 'sections' );
        if ( $section_index > 0 && isset( $sections[ $chapter_index ][ $section_index - 1 ] ) ) {
            $context_summary = $factory->build_context_summary(
                $sections[ $chapter_index ][ $section_index - 1 ],
                $state->get( 'context_summary_length', 80 )
            );
        }

        $prompt = $factory->build_expand_prompt( $state, $factory->route, $chapter, $section, $context_summary );
        $response = $factory->call_ai_with_rate_limit( $prompt );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $content = $factory->sanitize_section_content( $response['content'] );

        // 更新State
        $sections[ $chapter_index ][ $section_index ] = $content;
        $state->set( 'sections', $sections );
        $state->save_state();

        // 增量重拼该章 + 全书 (S13)
        $factory->rebuild_draft();

        return array(
            'content'  => $content,
            'md_url'   => $state->get( 'draft_files' )['md_url'],
            'html_url' => $state->get( 'draft_files' )['html_url'],
        );
    }

    /**
     * 增量重拼书稿 (S13)
     */
        public function rebuild_draft() { return Linked3_Book_Factory_Utils::rebuild_draft(); }

    /**
     * 加载管线配置 (book.yaml)
     *
     * @return array
     */
        public function load_pipeline_config() { return Linked3_Book_Factory_Utils::load_pipeline_config(); }

    /**
     * 检查步骤是否启用
     *
     * @param string $step_id
     * @return bool
     */
    private function is_step_enabled( $step_id ) {
        $options = $this->state->get( 'options', array() );
        $key = 'enable_' . $step_id;
        if ( isset( $options[ $key ] ) ) {
            return (bool) $options[ $key ];
        }
        // 默认: step1/step2/step6 启用, 可由用户关闭
        return true;
    }

    /**
     * 步骤ID转状态枚举
     *
     * @param string $step_id
     * @return string
     */
    private function step_to_status( $step_id ) {
        $map = array(
            'step1_demo'     => Linked3_Book_Project_State::STATUS_DEMOING,
            'step2_explore'  => Linked3_Book_Project_State::STATUS_EXPLORING,
            'step3_outline'  => Linked3_Book_Project_State::STATUS_OUTLINING,
            'step4_expand'   => Linked3_Book_Project_State::STATUS_EXPANDING,
            'step5_complete' => Linked3_Book_Project_State::STATUS_COMPLETING,
            'step6_review'   => Linked3_Book_Project_State::STATUS_REVIEWING,
        );
        return isset( $map[ $step_id ] ) ? $map[ $step_id ] : Linked3_Book_Project_State::STATUS_IDLE;
    }

    private function smart_split_outline( $content ) {
        return Linked3_Book_Factory_Utils::smart_split_outline( $content );
    }
    private function parse_outline( $content ) {
        return Linked3_Book_Factory_Utils::parse_outline( $content );
    }
    private function call_ai_with_rate_limit( $prompt ) {
        $min_interval = 1.0;
        $elapsed = microtime( true ) - $this->last_api_call;
        if ( $elapsed < $min_interval ) usleep( (int) ( ( $min_interval - $elapsed ) * 1000000 ) );
        $this->last_api_call = microtime( true );
        try {
            $dispatcher = AIDispatcher::instance();
            $messages = array( array( 'role' => 'user', 'content' => $prompt ) );
            $options = array( 'temperature' => 0.7, 'max_tokens' => 4096 );
            $config = TokenManager::get_active_config();
            $response = $dispatcher->chat( $messages, $options, $config );
        } catch ( \Throwable $e ) {
            throw new \RuntimeException( 'AI call failed: ' . $e->getMessage(), 0, $e );
        }
        if ( is_wp_error( $response ) ) return $response;
        $content = ''; $tokens_in = 0; $tokens_out = 0;
        if ( isset( $response['choices'][0]['message']['content'] ) ) $content = $response['choices'][0]['message']['content'];
        elseif ( isset( $response['content'] ) ) $content = $response['content'];
        if ( isset( $response['usage']['prompt_tokens'] ) ) $tokens_in = intval( $response['usage']['prompt_tokens'] );
        if ( isset( $response['usage']['completion_tokens'] ) ) $tokens_out = intval( $response['usage']['completion_tokens'] );
        $cost = $this->calculate_cost( $tokens_in, $tokens_out );
        $this->log_cost_to_state( $this->state, 'ai_call', $tokens_in, $tokens_out, $cost );
        return array( 'content' => $content, 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out, 'cost' => $cost );
    }
}