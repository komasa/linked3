<?php
/**
 * BookFactory Steps — extracted from Book_Factory God Class (G4.3).
 *
 * Contains the 6 step execution methods (demo→explore→outline→expand→complete→review).
 *
 * @package Linked3
 * @subpackage Classes\BookFactory
 * @since      27.5.0
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

class Linked3_Book_Factory_Steps
{
    public function execute_step1_demo( $state ) : array {
        $state->set_status( Linked3_Book_Project_State::STATUS_DEMOING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step1_demo', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step1_demo' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt );
            if ( is_wp_error( $response ) ) {
                $state->log_step( 'step1_demo', 'failed', array( 'error' => $response->get_error_message() ) );
            } else {
                $output = $response['content'];
                $state->set( 'demo_questions', $output );
                $state->set( 'current_output', $output );
                $step_outputs = $state->get( 'step_outputs', array() );
                $step_outputs['step1_demo'] = $output;
                $state->set( 'step_outputs', $step_outputs );
                $state->log_step( 'step1_demo', 'success' );
                $this->rebuild_draft_incremental( $state );
            }
        } catch ( \Throwable $e ) {
            $state->log_step( 'step1_demo', 'failed', array( 'error' => $e->getMessage() ) );
        }

        // 进入step2
        $state->set_status( Linked3_Book_Project_State::STATUS_EXPLORING );
        $state->set( 'current_step', 'step2_explore' );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step1_demo', 'next' => 'step2_explore' );
    }

    public function execute_step2_explore( $state ) : array {
        $state->set_status( Linked3_Book_Project_State::STATUS_EXPLORING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step2_explore', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step2_explore' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt );
            if ( is_wp_error( $response ) ) {
                $state->log_step( 'step2_explore', 'failed', array( 'error' => $response->get_error_message() ) );
            } else {
                $output = $response['content'];
                $state->set( 'exploration', $output );
                $state->set( 'current_output', $output );
                $step_outputs = $state->get( 'step_outputs', array() );
                $step_outputs['step2_explore'] = $output;
                $state->set( 'step_outputs', $step_outputs );
                $state->log_step( 'step2_explore', 'success' );
                $this->rebuild_draft_incremental( $state );
            }
        } catch ( \Throwable $e ) {
            $state->log_step( 'step2_explore', 'failed', array( 'error' => $e->getMessage() ) );
        }

        // 进入step3
        $state->set_status( Linked3_Book_Project_State::STATUS_OUTLINING );
        $state->set( 'current_step', 'step3_outline' );
        $state->set( 'outline_iter_cursor', 0 );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step2_explore', 'next' => 'step3_outline' );
    }

    public function execute_step3_outline_iter( $state ) : mixed {
        $state->set_status( Linked3_Book_Project_State::STATUS_OUTLINING );
        $state->save_state();

        $level = $state->get( 'iteration_level', 'standard' );
        $levels = Linked3_Type_Mode_Router::get_all_iteration_levels();
        $max_iter = isset( $levels[ $level ]['iterations'] ) ? $levels[ $level ]['iterations'] : 3;
        $iter_cursor = $state->get( 'outline_iter_cursor', 0 );

        $book_title = $state->get( 'book_title' );
        $type = $state->get( 'type' );
        $mode = $state->get( 'mode' );
        $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );

        // 获取已有版本供迭代参考
        $versions = $state->get( 'outline_versions', array() );
        $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step3_outline', $vars, $iter_cursor + 1 );

        $state->set( 'current_prompt', $prompt );
        $state->set( 'current_prompt_step', 'step3_outline' );
        $state->set( 'current_prompt_iter', $iter_cursor + 1 );
        $state->save_state();

        $response = $this->call_ai_with_rate_limit( $prompt );
        if ( is_wp_error( $response ) ) {
            $state->set_status( Linked3_Book_Project_State::STATUS_FAILED );
            $state->save_state();
            return new WP_Error( 'outline_failed', $response->get_error_message() );
        }

        $raw_content = $response['content'];
        $state->set( 'outline_raw', $raw_content );
        $state->set( 'current_output', $raw_content );
        $step_outputs = $state->get( 'step_outputs', array() );
        $step_outputs['step3_outline'] = $raw_content;
        $step_outputs['step3_outline_iter_' . $iter_cursor] = $raw_content;
        $state->set( 'step_outputs', $step_outputs );

        // v18.10: 用smart_split_outline替代parse_outline (保证≥3章)
        $parsed = $this->smart_split_outline( $raw_content );
        $parsed['raw_content'] = $raw_content;
        $versions[] = $parsed;
        $iter_cursor++;

        $state->set( 'outline_versions', $versions );
        $state->set( 'outline_iter_cursor', $iter_cursor );
        $state->log_step( 'step3_outline', 'success', array( 'iter' => $iter_cursor, 'chapters' => count( $parsed['chapters'] ) ) );
        $this->rebuild_draft_incremental( $state );
        $state->save_state();

        // 检查是否迭代完成
        if ( $iter_cursor >= $max_iter ) {
            // 合并大纲
            $final_outline = $this->merge_outlines( $versions );
            $state->set( 'final_outline', $final_outline );
            $state->set( 'chapters', $final_outline['chapters'] );

            // 进入step4
            $state->set_status( Linked3_Book_Project_State::STATUS_EXPANDING );
            $state->set( 'current_step', 'step4_expand' );
            $state->set( 'expand_chapter_cursor', 0 );
            $state->set( 'expand_section_cursor', 0 );
            $state->save_state();

            return array(
                'done' => false,
                'step' => 'step3_outline',
                'next' => 'step4_expand',
                'outline_ready' => true,
                'chapters' => count( $final_outline['chapters'] ),
            );
        }

        return array(
            'done' => false,
            'step' => 'step3_outline',
            'iter' => $iter_cursor,
            'max_iter' => $max_iter,
            'next' => 'step3_outline',
        );
    }

    public function execute_step4_expand_one( $state ) : mixed {
        $state->set_status( Linked3_Book_Project_State::STATUS_EXPANDING );
        $state->save_state();

        $chapters = $state->get( 'chapters' );
        $sections = $state->get( 'sections', array() );

        if ( empty( $chapters ) ) {
            return new WP_Error( 'no_outline', '大纲未生成' );
        }

        // 查找下一个未完成的节
        $total = 0;
        $completed = 0;
        $found_pending = false;

        foreach ( $chapters as $ch_idx => $chapter ) {
            $total += count( $chapter['sections'] );
            if ( $found_pending ) continue;

            if ( ! isset( $sections[ $ch_idx ] ) ) {
                $sections[ $ch_idx ] = array();
            }

            foreach ( $chapter['sections'] as $sec_idx => $section ) {
                if ( isset( $sections[ $ch_idx ][ $sec_idx ] ) && ! empty( $sections[ $ch_idx ][ $sec_idx ] ) ) {
                    $completed++;
                    continue;
                }

                $found_pending = true;

                // 构建上下文摘要
                $context_summary = '';
                if ( $sec_idx > 0 && isset( $sections[ $ch_idx ][ $sec_idx - 1 ] ) ) {
                    $context_summary = $this->build_context_summary(
                        $sections[ $ch_idx ][ $sec_idx - 1 ],
                        $state->get( 'context_summary_length', 80 )
                    );
                }

                $book_title = $state->get( 'book_title' );
                $type = $state->get( 'type' );
                $mode = $state->get( 'mode' );
                $level = $state->get( 'iteration_level', 'standard' );
                $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );
                $section_vars = array_merge( $vars, array(
                    'chapter_title'  => $chapter['title'],
                    'section_title'  => $section['title'],
                    'context_summary'=> $context_summary,
                    'chapter_index'  => $ch_idx + 1,
                    'section_index'  => $sec_idx + 1,
                ) );
                $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step4_expand', $section_vars, 1 );

                $state->set( 'current_prompt', $prompt );
                $state->set( 'current_prompt_step', 'step4_expand' );
                $state->set( 'current_chapter_idx', $ch_idx );
                $state->set( 'current_section_idx', $sec_idx );
                $state->save_state();

                $response = $this->call_ai_with_rate_limit( $prompt );
                if ( is_wp_error( $response ) ) {
                    $state->set_status( Linked3_Book_Project_State::STATUS_FAILED );
                    $state->save_state();
                    return new WP_Error( 'expand_failed', $response->get_error_message() );
                }

                $content = $this->sanitize_section_content( $response['content'] );
                $sections[ $ch_idx ][ $sec_idx ] = $content;
                $completed++;

                $state->set( 'sections', $sections );
                $state->set( 'expand_chapter_cursor', $ch_idx );
                $state->set( 'expand_section_cursor', $sec_idx );
                $state->set( 'current_output', $content );

                $section_outputs = $state->get( 'section_outputs', array() );
                $section_outputs[ $ch_idx . '_' . $sec_idx ] = array(
                    'chapter' => $chapter['title'],
                    'section' => $section['title'],
                    'content' => $content,
                );
                $state->set( 'section_outputs', $section_outputs );

                $step_outputs = $state->get( 'step_outputs', array() );
                $step_outputs['step4_expand_' . $completed] = '【' . $chapter['title'] . ' - ' . $section['title'] . '】\n' . $content;
                $state->set( 'step_outputs', $step_outputs );

                // v18.10.3: step4不调用rebuild_draft (性能优化, 避免132次全量拼接)
                // 只更新current_output供前端显示, draft_markdown在step5统一生成
                $state->set( 'current_output', $content );
                $state->save_state();

                break;
            }
        }

        // 检查是否全部完成
        if ( $completed >= $total && ! $found_pending ) {
            // v18.10.3: step4全部完成时, 才调用rebuild_draft生成最终书稿
            $this->rebuild_draft_incremental( $state );
            $state->set( 'current_step', 'step5_complete' );
            $state->set_status( Linked3_Book_Project_State::STATUS_COMPLETING );
            $state->save_state();
            return array(
                'done' => false,
                'step' => 'step4_expand',
                'next' => 'step5_complete',
                'completed' => $completed,
                'total' => $total,
            );
        }

        return array(
            'done' => false,
            'step' => 'step4_expand',
            'next' => 'step4_expand',
            'completed' => $completed,
            'total' => $total,
        );
    }

    public function execute_step5_complete( $state ) : array {
        $state->set_status( Linked3_Book_Project_State::STATUS_COMPLETING );
        $state->save_state();

        try {
            $this->pipeline_step5_complete();
            $state->log_step( 'step5_complete', 'success' );
        } catch ( \Throwable $e ) {
            $state->log_step( 'step5_complete', 'failed', array( 'error' => $e->getMessage() ) );
        }

        // 进入step6
        $state->set_status( Linked3_Book_Project_State::STATUS_REVIEWING );
        $state->set( 'current_step', 'step6_review' );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step5_complete', 'next' => 'step6_review' );
    }

    public function execute_step6_review( $state ) : array {
        $state->set_status( Linked3_Book_Project_State::STATUS_REVIEWING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = Linked3_Book_Prompt_Manager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = Linked3_Book_Prompt_Manager::get_prompt( 'step6_review', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step6_review' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt );
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
        $state->set_status( Linked3_Book_Project_State::STATUS_DONE );
        $state->set( 'current_step', 'done' );
        $state->save_state();

        return array( 'done' => true, 'step' => 'step6_review', 'next' => 'done' );
    }

}
