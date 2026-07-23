<?php

declare(strict_types=1);
/**
 * BookFactory Ajax Prompts — Static facade for BookPromptManager
 *
 * Provides static Ajax handler methods for prompt management endpoints.
 * Handles nonce verification, parameter extraction, and JSON response,
 * then delegates to BookPromptManager.
 *
 * @package Linked3\BookFactory
 * @since   27.1.0 (extracted from legacy BookAjaxPrompts)
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static Ajax facade for prompt management.
 *
 * Replaces the legacy BookAjaxPrompts class.
 */
class BookAjaxPrompts {

	/**
	 * Get all prompts for the frontend editor.
	 *
	 * @return void
	 */
	public static function get_prompts(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'linked3-ai' ) ), 403 );
		}

		$result = BookPromptManager::get_all();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Save a prompt for a given step.
	 *
	 * @return void
	 */
	public static function save_prompt(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'linked3-ai' ) ), 403 );
		}

		$step_key    = isset( $_POST['step_key'] ) ? sanitize_text_field( wp_unslash( $_POST['step_key'] ) ) : '';
		$prompt_text = isset( $_POST['prompt_text'] ) ? wp_unslash( $_POST['prompt_text'] ) : '';

		if ( empty( $step_key ) ) {
			wp_send_json_error( array( 'message' => __( '步骤key不能为空', 'linked3-ai' ) ) );
		}

		$result = BookPromptManager::save_prompt( $step_key, $prompt_text );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( '已保存', 'linked3-ai' ) ) );
	}

	/**
	 * Preview a prompt with variables filled in.
	 *
	 * @return void
	 */
	public static function preview_prompt(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'linked3-ai' ) ), 403 );
		}

		$step_key   = isset( $_POST['step_key'] ) ? sanitize_text_field( wp_unslash( $_POST['step_key'] ) ) : '';
		$book_title = isset( $_POST['book_title'] ) ? sanitize_text_field( wp_unslash( $_POST['book_title'] ) ) : '';
		$type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'book';
		$mode       = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'ai';
		$level      = isset( $_POST['iteration_level'] ) ? sanitize_text_field( wp_unslash( $_POST['iteration_level'] ) ) : 'standard';

		if ( empty( $step_key ) ) {
			wp_send_json_error( array( 'message' => __( '步骤key不能为空', 'linked3-ai' ) ) );
		}

		$vars   = BookPromptManager::build_context_vars( $book_title, $type, $mode, $level );
		$result = BookPromptManager::get_prompt( $step_key, $vars );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'prompt' => $result ) );
	}
}
