<?php

declare(strict_types=1);
/**
 * BookFactory Ajax Pipeline — Static facade for BookPipelineOrchestrator
 *
 * Provides static Ajax handler methods that BookAjaxActions delegates to.
 * Handles nonce verification, parameter extraction, and JSON response,
 * then delegates business logic to BookPipelineOrchestrator.
 *
 * @package Linked3\BookFactory
 * @since   27.1.0 (extracted from legacy BookAjaxPipeline)
 */

namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static Ajax facade for the book pipeline.
 *
 * Replaces the legacy BookAjaxPipeline class.
 * All methods are static Ajax endpoints — they read from $_POST/$_REQUEST,
 * verify nonces, delegate to BookPipelineOrchestrator, and send JSON.
 */
class BookAjaxPipeline {

	/**
	 * Shared orchestrator instance (lazy-loaded).
	 *
	 * @var BookPipelineOrchestrator|null
	 */
	private static $orchestrator = null;

	/**
	 * Get the shared orchestrator instance.
	 *
	 * @return BookPipelineOrchestrator
	 */
	private static function orchestrator(): BookPipelineOrchestrator {
		if ( self::$orchestrator === null ) {
			self::$orchestrator = new BookPipelineOrchestrator();
		}
		return self::$orchestrator;
	}

	/**
	 * Start a new book generation project.
	 *
	 * @return void
	 */
	public static function start(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'linked3-ai' ) ), 403 );
		}

		$args = array(
			'title'     => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'type'      => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'book',
			'mode'      => isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'ai',
			'level'     => isset( $_POST['iteration_level'] ) ? sanitize_text_field( wp_unslash( $_POST['iteration_level'] ) ) : 'standard',
			'prototype' => isset( $_POST['prototype_key'] ) ? sanitize_text_field( wp_unslash( $_POST['prototype_key'] ) ) : 'book',
		);

		$result = self::orchestrator()->create_book( $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Start async book generation (non-blocking).
	 *
	 * @return void
	 */
	public static function start_async(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足', 'linked3-ai' ) ), 403 );
		}

		$args = array(
			'title'     => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'type'      => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'book',
			'mode'      => isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'ai',
			'level'     => isset( $_POST['iteration_level'] ) ? sanitize_text_field( wp_unslash( $_POST['iteration_level'] ) ) : 'standard',
			'prototype' => isset( $_POST['prototype_key'] ) ? sanitize_text_field( wp_unslash( $_POST['prototype_key'] ) ) : 'book',
			'async'     => true,
		);

		$result = self::orchestrator()->create_book( $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Schedule background processing
		if ( isset( $result['project_id'] ) ) {
			wp_schedule_single_event( time(), 'linked3_book_factory_run_step', array( $result['project_id'] ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Cancel an async generation.
	 *
	 * @return void
	 */
	public static function cancel_async(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

		if ( empty( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( '项目ID不能为空', 'linked3-ai' ) ) );
		}

		$state = BookProjectState::get_project( $project_id );
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( '项目不存在', 'linked3-ai' ) ) );
		}

		$state->set( 'status', 'cancelled' );
		$state->save_state();

		wp_send_json_success( array( 'message' => __( '已取消', 'linked3-ai' ) ) );
	}

	/**
	 * Query project progress.
	 *
	 * @return void
	 */
	public static function progress(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id = isset( $_REQUEST['project_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['project_id'] ) ) : '';

		if ( empty( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( '项目ID不能为空', 'linked3-ai' ) ) );
		}

		$result = self::orchestrator()->get_progress( $project_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Regenerate a specific section.
	 *
	 * @return void
	 */
	public static function regenerate_section(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id     = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';
		$chapter_index  = isset( $_POST['chapter_index'] ) ? absint( $_POST['chapter_index'] ) : 0;
		$section_index  = isset( $_POST['section_index'] ) ? absint( $_POST['section_index'] ) : 0;

		if ( empty( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( '项目ID不能为空', 'linked3-ai' ) ) );
		}

		$result = self::orchestrator()->regenerate_section( $project_id, $chapter_index, $section_index );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Download the generated book.
	 *
	 * @return mixed
	 */
	public static function download(): mixed {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id = isset( $_GET['project_id'] ) ? sanitize_text_field( wp_unslash( $_GET['project_id'] ) ) : '';
		$format     = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'txt';

		if ( empty( $project_id ) ) {
			wp_die( __( '项目ID不能为空', 'linked3-ai' ) );
		}

		$state = BookProjectState::get_project( $project_id );
		if ( ! $state ) {
			wp_die( __( '项目不存在', 'linked3-ai' ) );
		}

		$content = $state->get( 'final_content', '' );
		$title   = $state->get( 'title', 'book' );

		$filename = sanitize_title( $title ) . '.' . $format;

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );

		echo $content; // phpcs:ignore
		exit;
	}

	/**
	 * Resume a paused/interrupted project.
	 *
	 * @return void
	 */
	public static function resume(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

		if ( empty( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( '项目ID不能为空', 'linked3-ai' ) ) );
		}

		$state = BookProjectState::get_project( $project_id );
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( '项目不存在', 'linked3-ai' ) ) );
		}

		$state->set( 'status', 'running' );
		$state->save_state();

		// Schedule next step
		wp_schedule_single_event( time(), 'linked3_book_factory_run_step', array( $project_id ) );

		wp_send_json_success( array( 'message' => __( '已恢复', 'linked3-ai' ) ) );
	}

	/**
	 * Rollback to a previous outline version.
	 *
	 * @return void
	 */
	public static function rollback_outline(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id     = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';
		$version_index  = isset( $_POST['version_index'] ) ? absint( $_POST['version_index'] ) : 0;

		$result = self::orchestrator()->rollback_version( $project_id, $version_index );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Execute a single pipeline step (polled by frontend).
	 *
	 * @return void
	 */
	public static function run_step(): void {
		check_ajax_referer( 'linked3_book_factory', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';

		if ( empty( $project_id ) ) {
			wp_send_json_error( array( 'message' => __( '项目ID不能为空', 'linked3-ai' ) ) );
		}

		$result = self::orchestrator()->run_step( $project_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
