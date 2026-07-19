<?php
/**
 * BookFactory 异步任务调度器 (v18.11 新增)
 *
 * 解决问题: v18.10.3 中前端需要不断轮询 run_step AJAX 端点,
 * 每次轮询间隔 3-5 秒, 60 节书稿需要 60 次轮询, 总耗时 3-5 分钟,
 * 用户体验差且占用 PHP-FPM worker。
 *
 * 方案: 引入后台自动链式执行机制。当 run_step 完成一步后,
 * 如果项目状态为 running, 自动调度下一次 run_step 到 wp_cron,
 * 前端只需轮询 progress 端点获取进度, 无需触发执行。
 *
 * 同时支持 WP-CLI 批量执行模式 (如果 WP-CLI 可用),
 * 在 CLI 环境下可以连续执行多步而不受 PHP max_execution_time 限制。
 *
 * @package Linked3\BookFactory
 * @since   18.11
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Linked3_Book_Async_Runner
 */
class Linked3_Book_Async_Runner {

	/**
	 * 注册 cron hook 与调度。
	 */
	public static function init() : void {
		// 注册 cron hook
		add_action( 'linked3_book_async_run_step', array( __CLASS__, 'cron_run_step' ), 10, 1 );

		// 注册自定义调度间隔 (每 15 秒)
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
	}

	/**
	 * 添加 15 秒的 cron 间隔。
	 *
	 * @param array $schedules 现有调度间隔。
	 * @return array
	 */
	public static function add_cron_interval( $schedules ) : mixed {
		$schedules['linked3_15s'] = array(
			'interval' => 15,
			'display'  => __( 'Linked3 每15秒', 'linked3-ai' ),
		);
		return $schedules;
	}

	/**
	 * 调度下一次异步步骤执行。
	 *
	 * @param string $project_id 项目 ID。
	 * @param int    $delay      延迟秒数 (默认 5 秒, 避免 API 速率限制)。
	 */
	public static function schedule_next_step( $project_id, $delay = 5 ) : void {
		// v18.11: 校验 project_id。
		if ( false === Linked3_Book_Security::validate_project_id( $project_id ) ) {
			return;
		}

		// 清除已有的同项目调度 (避免重复)。
		$timestamp = wp_next_scheduled( 'linked3_book_async_run_step', array( $project_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'linked3_book_async_run_step', array( $project_id ) );
		}

		wp_schedule_single_event( time() + $delay, 'linked3_book_async_run_step', array( $project_id ) );
	}

	/**
	 * Cron 回调: 执行一步并自动链式调度下一步。
	 *
	 * @param string $project_id 项目 ID。
	 */
	public static function cron_run_step( $project_id ) : void {
		// v18.11: 校验 project_id。
		if ( false === Linked3_Book_Security::validate_project_id( $project_id ) ) {
			return;
		}

		$state = Linked3_Book_Project_State::get_project( $project_id );
		if ( ! $state ) {
			return;
		}

		$status = $state->get( 'status' );

		// 已完成或失败, 停止链式调度。
		if ( in_array( $status, array( 'done', 'failed', 'paused' ), true ) ) {
			return;
		}

		// CLI 环境下可以连续执行多步 (无 max_execution_time 限制)。
		if ( self::is_cli() ) {
			self::run_to_completion( $project_id );
			return;
		}

		// Web 环境: 执行一步, 然后调度下一步。
		$result = Linked3_Book_Factory::run_step( $project_id );

		if ( is_wp_error( $result ) ) {
			// 执行失败, 停止链式调度 (状态已被 run_step 设为 failed)。
			return;
		}

		// 检查是否已完成。
		if ( isset( $result['done'] ) && $result['done'] ) {
			return;
		}

		// 调度下一步 (延迟 5 秒, 避免 API 速率限制)。
		self::schedule_next_step( $project_id, 5 );
	}

	/**
	 * CLI 模式: 连续执行直到完成或失败。
	 * 不受 PHP max_execution_time 限制, 适合长书稿生成。
	 *
	 * @param string $project_id 项目 ID。
	 */
	public static function run_to_completion( $project_id ) : void {
		if ( false === Linked3_Book_Security::validate_project_id( $project_id ) ) {
			return;
		}

		$max_steps = 200; // 安全阀, 防止无限循环。
		$step      = 0;

		while ( $step < $max_steps ) {
			$state = Linked3_Book_Project_State::get_project( $project_id );
			if ( ! $state ) {
				break;
			}

			$status = $state->get( 'status' );
			if ( in_array( $status, array( 'done', 'failed', 'paused' ), true ) ) {
				break;
			}

			$result = Linked3_Book_Factory::run_step( $project_id );

			if ( is_wp_error( $result ) ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					\WP_CLI::error( '步骤执行失败: ' . $result->get_error_message() );
				}
				break;
			}

			if ( isset( $result['done'] ) && $result['done'] ) {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					\WP_CLI::success( '书稿生成完成!' );
				}
				break;
			}

			$step++;

			// CLI 进度输出。
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$completed = isset( $result['completed'] ) ? $result['completed'] : 0;
				$total     = isset( $result['total'] ) ? $result['total'] : '?';
				\WP_CLI::log( sprintf( '[%d/%s] 步骤 %s 完成', $completed, $total, $result['step'] ) );
			}
		}
	}

	/**
	 * 检测当前是否在 CLI 环境。
	 *
	 * @return bool
	 */
	private static function is_cli() : mixed {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || ( php_sapi_name() === 'cli' );
	}

	/**
	 * 取消项目的所有异步调度。
	 *
	 * @param string $project_id 项目 ID。
	 */
	public static function cancel( $project_id ) : void {
		if ( false === Linked3_Book_Security::validate_project_id( $project_id ) ) {
			return;
		}

		$timestamp = wp_next_scheduled( 'linked3_book_async_run_step', array( $project_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'linked3_book_async_run_step', array( $project_id ) );
		}
	}
}

// 初始化异步调度器。
Linked3_Book_Async_Runner::init();
