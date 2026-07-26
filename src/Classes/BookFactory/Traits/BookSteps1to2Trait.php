<?php

declare(strict_types=1);
/**
 * BookSteps1to2Trait — Demo + Explore step execution.
 *
 * @package Linked3\BookFactory
 */

namespace Linked3\Classes\BookFactory\Traits;

if (!defined('ABSPATH')) exit;

trait BookSteps1to2Trait
{
    public function execute_step1_demo( $state ) : array {
        $state->set_status( BookProjectState::STATUS_DEMOING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = BookPromptManager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = BookPromptManager::get_prompt( 'step1_demo', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step1_demo' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt, $state );
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
        $state->set_status( BookProjectState::STATUS_EXPLORING );
        $state->set( 'current_step', 'step2_explore' );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step1_demo', 'next' => 'step2_explore' );
    }

    public function execute_step2_explore( $state ) : array {
        $state->set_status( BookProjectState::STATUS_EXPLORING );
        $state->save_state();

        try {
            $book_title = $state->get( 'book_title' );
            $type = $state->get( 'type' );
            $mode = $state->get( 'mode' );
            $level = $state->get( 'iteration_level', 'standard' );
            $vars = BookPromptManager::build_context_vars( $book_title, $type, $mode, $level );
            $prompt = BookPromptManager::get_prompt( 'step2_explore', $vars, 1 );

            $state->set( 'current_prompt', $prompt );
            $state->set( 'current_prompt_step', 'step2_explore' );
            $state->save_state();

            $response = $this->call_ai_with_rate_limit( $prompt, $state );
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
        $state->set_status( BookProjectState::STATUS_OUTLINING );
        $state->set( 'current_step', 'step3_outline' );
        $state->set( 'outline_iter_cursor', 0 );
        $state->save_state();

        return array( 'done' => false, 'step' => 'step2_explore', 'next' => 'step3_outline' );
    }
}
