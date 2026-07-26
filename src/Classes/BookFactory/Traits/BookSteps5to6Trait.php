<?php

declare(strict_types=1);
/**
 * BookSteps5to6Trait — Complete + Review step + self-contained helpers.
 *
 * @package Linked3\BookFactory
 */

namespace Linked3\Classes\BookFactory\Traits;

if (!defined('ABSPATH')) exit;

trait BookSteps5to6Trait
{
    public function execute_step5_complete( $state ) : array {
        $state->set_status( BookProjectState::STATUS_COMPLETING );
        $state->save_state();

        try {
            // Inlined pipeline_step5_complete logic (was $this->pipeline_step5_complete())
            $chapters = $state->get( 'chapters' );
            $sections = $state->get( 'sections' );
            $book_title = $state->get( 'book_title' );

            // v18.9.2: 如果大纲为空, 用step3的AI原始输出作为正文兜底
            if ( empty( $chapters ) ) {
                $outline_versions = $state->get( 'outline_versions', array() );
                $raw_outline = '';
                if ( ! empty( $outline_versions ) ) {
                    $last_version = end( $outline_versions );
                    if ( isset( $last_version['raw_content'] ) ) {
                        $raw_outline = $last_version['raw_content'];
                    }
                }
                // 用step2探索内容或step1演示内容兜底
                if ( empty( $raw_outline ) ) {
                    $raw_outline = $state->get( 'exploration', '' ) ?: $state->get( 'demo_questions', '' );
                }
                $chapters = array(
                    array(
                        'title' => $book_title,
                        'sections' => array(
                            array( 'title' => __('正文', 'linked3'), 'content' => $raw_outline ?: '（大纲生成异常,请重试）' ),
                        ),
                    ),
                );
                $sections = array( array( 0 => $raw_outline ?: '' ) );
                $state->set( 'chapters', $chapters );
                $state->set( 'sections', $sections );
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
            $route = $state->get( 'route', array() );
            $template = isset( $route['output_template_config'] ) ? $route['output_template_config'] : array();
            if ( empty( $template ) ) {
                $template = array( 'chapter_prefix' => __('第', 'linked3'), 'chapter_suffix' => __('章', 'linked3') );
            }
            $result = SectionStitcher::stitch( $full_chapters, $template, $book_title );
            $files = SectionStitcher::save_to_file( $state->project_id, $result['markdown'], $result['html'] );

            $state->set( 'draft_markdown', $result['markdown'] );
            $state->set( 'draft_html', $result['html'] );
            $state->set( 'draft_files', $files );

            $state->log_step( 'step5_complete', 'success' );
        } catch ( \Throwable $e ) {
            $state->log_step( 'step5_complete', 'failed', array( 'error' => $e->getMessage() ) );
        }

        // 进入step6
        $state->set_status( BookProjectState::STATUS_REVIEWING );
        $state->set( 'current_step', 'step6_review' );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step5_complete', 'next' => 'step6_review' );
    }

    public function execute_step6_review( $state ) : array {
        $state->set_status( BookProjectState::STATUS_REVIEWING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = BookPromptManager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = BookPromptManager::get_prompt( 'step6_review', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step6_review' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt, $state );
            if ( is_wp_error( $response ) ) {
                $state->log_step( 'step6_review', 'failed', array( 'error' => $response->get_error_message() ) );
            } else {
                $output = $response['content'];
                $state->set( 'review_suggestions', $output );
                $state->set( 'review_output', $output );
                $state->set( 'current_output', $output );
                $step_outputs = $state->get( 'step_outputs', array() );
                $step_outputs['step6_review'] = $output;
                $state->set( 'step_outputs', $step_outputs );
                $state->log_step( 'step6_review', 'success' );
                $this->rebuild_draft_incremental( $state );
            }
        } catch ( \Throwable $e ) {
            $state->log_step( 'step6_review', 'failed', array( 'error' => $e->getMessage() ) );
        }

        // 完成
        $state->set_status( BookProjectState::STATUS_DONE );
        $state->set( 'current_step', 'done' );
        $state->save_state();

        return array( 'done' => true, 'step' => 'step6_review', 'next' => 'done' );
    }

    // ─────────────────────────────────────────
    // Self-contained helper methods (no longer depend on BookFactory)
    // ─────────────────────────────────────────

    /**
     * AI调用 + 速率控制 + 成本记录 (从 BookFactory 迁移)
     *
     * @param string $prompt
     * @param BookProjectState|null $state 用于成本记录, null时跳过
     * @return array|WP_Error
     */
    private function call_ai_with_rate_limit(string $prompt, ?BookProjectState $state = null): array|\WP_Error {
        $min_interval = 1.0;
        $elapsed = microtime( true ) - $this->last_api_call;
        if ( $elapsed < $min_interval ) usleep( (int) ( ( $min_interval - $elapsed ) * 1000000 ) );
        $this->last_api_call = microtime( true );
        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $messages = array( array( 'role' => 'user', 'content' => $prompt ) );
            // v28 PR-10: 添加 timeout=45s (原无 timeout, 4096 token 可能 >60s)
            $options = array( 'temperature' => 0.7, 'max_tokens' => 4096, 'timeout' => 45 );
            $config = [];
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
        if ( $state ) {
            $this->log_cost_to_state( $state, 'ai_call', $tokens_in, $tokens_out, $cost );
        }
        return array( 'content' => $content, 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out, 'cost' => $cost );
    }

    /**
     * 增量重建草稿 (委托给 BookDraftBuilder::rebuild())
     * 替代原幻影方法 rebuild_draft_incremental()
     *
     * @param BookProjectState $state
     */
    private function rebuild_draft_incremental(BookProjectState $state) {
        $builder = new BookDraftBuilder();
        return $builder->rebuild( $state );
    }

    /**
     * 智能分割大纲 (委托给 BookFactoryUtils)
     *
     * @param string $content
     * @return mixed
     */
    private function smart_split_outline(string $content) {
        $utils = new BookFactoryUtils();
        return $utils->smart_split_outline( $content );
    }

    /**
     * 解析大纲 (委托给 BookFactoryUtils)
     *
     * @param string $content
     * @return mixed
     */
    private function parse_outline(string $content) {
        $utils = new BookFactoryUtils();
        return $utils->parse_outline( $content );
    }
}
