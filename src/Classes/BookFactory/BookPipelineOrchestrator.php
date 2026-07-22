<?php

declare(strict_types=1);
/**
 * BookFactory 流水线编排器 (v19.0 从 Book_Factory 拆分)
 *
 * 职责: 流程编排 — 管理项目状态机、步骤调度、状态转换。
 * 不再承担 AI 调用、成本核算、草稿重建等职责 (已拆分到独立类)。
 *
 * v18.x: Book_Factory 1420 行上帝类
 * v19.0: Pipeline_Orchestrator ~200 行, 职责单一
 *
 * @package Linked3\BookFactory
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;



        use \Linked3\Classes\BookFactory\Traits\CostTracker;
        use \Linked3\Classes\BookFactory\Traits\SectionExpander;
        use \Linked3\Classes\BookFactory\Traits\OutlineMerger;
        use \Linked3\Classes\BookFactory\Traits\ReviewLinker;



use WP_Error;
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
/**
 * Class BookPipelineOrchestrator
 *
 * 流水线编排器, 通过依赖注入接收协作对象。
 */
class BookPipelineOrchestrator {
        /**
         * AI 调用器 (通过依赖注入)。
         *
         * @var BookAICallerInterface
         */
        protected $ai_caller;

        /**
         * 提示词提供者 (通过依赖注入)。
         *
         * @var BookPromptProviderInterface
         */
        protected $prompt_provider;

        /**
         * 成本追踪器 (通过依赖注入)。
         *
         * @var BookCostTrackerInterface
         */
        protected $cost_tracker;

        /**
         * 构造函数 — 依赖注入。
         *
         * @param BookAICallerInterface|null       $ai_caller       AI 调用器。
         * @param BookPromptProviderInterface|null $prompt_provider 提示词提供者。
         * @param BookCostTrackerInterface|null    $cost_tracker    成本追踪器。
         */
        public function __construct(
                BookAICallerInterface $ai_caller = null,
                BookPromptProviderInterface $prompt_provider = null,
                BookCostTrackerInterface $cost_tracker = null
        ) {
                // 默认使用 Book_Factory 的现有实现 (向后兼容)。
                $this->ai_caller       = $ai_caller ?: new BookDefaultAICaller();
                $this->prompt_provider = $prompt_provider ?: new BookPromptManager();
                $this->cost_tracker    = $cost_tracker ?: new BookDefaultCostTracker();
        }

        /**
         * 创建新书项目。
         *
         * @param array $args 创建参数 (book_title, type, mode, iteration_level, options)。
         * @return array|WP_Error 返回 array('project_id'=>..., 'status'=>...) 或 WP_Error。
         */
        public function create_book(array $args) : mixed {
                $book_title = isset( $args['book_title'] ) ? sanitize_text_field( $args['book_title'] ) : '';
                $type       = isset( $args['type'] ) ? sanitize_text_field( $args['type'] ) : 'book';
                $mode       = isset( $args['mode'] ) ? sanitize_text_field( $args['mode'] ) : 'ai';
                $level      = isset( $args['iteration_level'] ) ? sanitize_text_field( $args['iteration_level'] ) : 'standard';
                $options    = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();

                if ( empty( $book_title ) ) {
                        return new WP_Error( 'empty_title', '书名不能为空' );
                }

                // 创建项目状态。
                $state = new BookProjectState( '', array(
                        'book_title'      => $book_title,
                        'type'            => $type,
                        'mode'            => $mode,
                        'iteration_level' => $level,
                        'options'         => $options,
                ) );

                $state->set( 'status', 'running' );
                $state->set( 'current_step', 'step1_demo' );
                $state->set( 'created_at', current_time( 'mysql' ) );
                $state->save_state();

                return array(
                        'project_id' => $state->get( 'project_id' ),
                        'status'     => 'running',
                        'step'       => 'step1_demo',
                );
        }

        /**
         * 执行单步 (通过步骤注册表路由)。
         *
         * @param string $project_id 项目 ID。
         * @return array|WP_Error
         */
        public function run_step(string $project_id) : mixed {
                $state = BookProjectState::get_project( $project_id );
                if ( ! $state ) {
                        return new WP_Error( 'project_not_found', '项目不存在' );
                }

                $current_step = $state->get( 'current_step' );
                $status       = $state->get( 'status' );

                if ( 'done' === $status || 'done' === $current_step ) {
                        return array( 'done' => true, 'message' => __('已完成', 'linked3-ai') );
                }
                if ( 'failed' === $status ) {
                        return new WP_Error( 'project_failed', '项目已失败: ' . $state->get( 'error' ) );
                }
                if ( 'paused' === $status ) {
                        return new WP_Error( 'project_paused', '项目已暂停' );
                }

                // 通过步骤注册表路由。
                $step = BookStepRegistry::get_step( $current_step );
                if ( $step instanceof BookStepInterface ) {
                        return $step->execute( $state, $this );
                }

                // 回退兼容。
                switch ( $current_step ) {
                        case 'done':
                                return array( 'done' => true, 'message' => __('已完成', 'linked3-ai') );
                        default:
                                return new WP_Error( 'unknown_step', '未知步骤: ' . $current_step );
                }
        }

        /**
         * 获取项目进度。
         *
         * @param string $project_id 项目 ID。
         * @return array|WP_Error
         */
        public function get_progress(string $project_id): array|WP_Error {
                $state = BookProjectState::get_project( $project_id );
                if ( ! $state ) {
                        return new WP_Error( 'project_not_found', '项目不存在' );
                }

                return array(
                        'project_id'    => $project_id,
                        'status'        => $state->get( 'status' ),
                        'current_step'  => $state->get( 'current_step' ),
                        'completed'     => $state->get( 'completed_sections', 0 ),
                        'total'         => $state->get( 'total_sections', 0 ),
                        'cost'          => $state->get( 'total_cost', 0 ),
                        'error'         => $state->get( 'error' ),
                );
        }

        /**
         * 重新生成指定章节。
         *
         * @param string $project_id    项目 ID。
         * @param int    $chapter_index 章索引。
         * @param int    $section_index 节索引。
         * @return array|WP_Error
         */
        public function regenerate_section(string $project_id, int $chapter_index, int $section_index): array|WP_Error {
                $state = BookProjectState::get_project( $project_id );
                if ( ! $state ) {
                        return new WP_Error( 'project_not_found', '项目不存在' );
                }

                // 委托给 Section_Expander Trait。
                return $this->do_regenerate_section( $state, $chapter_index, $section_index );
        }

        /**
         * 回退到历史版本。
         *
         * @param string $project_id    项目 ID。
         * @param int    $version_index 版本索引。
         * @return array|WP_Error
         */
        public function rollback_version(string $project_id, int $version_index): array|WP_Error {
                $state = BookProjectState::get_project( $project_id );
                if ( ! $state ) {
                        return new WP_Error( 'project_not_found', '项目不存在' );
                }

                $versions = $state->get( 'versions', array() );
                if ( ! isset( $versions[ $version_index ] ) ) {
                        return new WP_Error( 'version_not_found', '版本不存在' );
                }

                $version = $versions[ $version_index ];
                $state->set( 'outline', $version['outline'] );
                $state->set( 'current_step', 'step4_expand' );
                $state->set( 'status', 'running' );
                $state->save_state();

                return array( 'message' => __('已回退到版本 ', 'linked3-ai') . $version_index );
        }
}
