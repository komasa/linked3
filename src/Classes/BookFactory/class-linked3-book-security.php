<?php
/**
 * BookFactory 安全工具类 (v18.11 新增)
 *
 * 集中处理 project_id 路径白名单校验、format 参数枚举校验、
 * 错误信息脱敏等安全相关逻辑，避免安全检查散落在各处。
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
 * Class Linked3_Book_Security
 *
 * 提供静态安全校验方法，供 Book_Project_State、Book_Ajax_Actions 等调用。
 */
class Linked3_Book_Security {

	/**
	 * project_id 合法格式：仅允许字母、数字、下划线、连字符，长度 1-64。
	 * 防止路径遍历攻击（如 ../../../etc/passwd）。
	 *
	 * @param string $project_id 待校验的项目 ID。
	 * @return string|false 校验通过返回清洗后的 project_id，失败返回 false。
	 */
	public static function validate_project_id( $project_id ) : mixed {
		if ( ! is_string( $project_id ) || '' === $project_id ) {
			return false;
		}
		// 限制长度 1-64 字符。
		if ( strlen( $project_id ) > 64 ) {
			return false;
		}
		// 仅允许 [a-zA-Z0-9_-]，禁止 . / \ 等路径字符。
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $project_id ) ) {
			return false;
		}
		return $project_id;
	}

	/**
	 * 校验并返回合法的 format 参数值。
	 *
	 * @param string $format 用户传入的 format 参数。
	 * @return string 合法的 format 值（md 或 html），默认 md。
	 */
	public static function validate_format( $format ) : mixed {
		$allowed = array( 'md', 'html' );
		$format  = strtolower( (string) $format );
		return in_array( $format, $allowed, true ) ? $format : 'md';
	}

	/**
	 * 对错误信息进行脱敏处理，避免向用户暴露文件路径、SQL 语句等敏感信息。
	 *
	 * @param string|\WP_Error $error 错误信息或 WP_Error 对象。
	 * @return string 脱敏后的安全错误消息。
	 */
	public static function sanitize_error_message( $error ) {
		if ( is_wp_error( $error ) ) {
			$error = $error->get_error_message();
		}
		$message = (string) $error;

		// 移除绝对路径（如 /var/www/html/...）。
		$message = preg_replace( '#(/[\w./-]+)+#', '[path]', $message );
		// 移除 Windows 路径。
		$message = preg_replace( '#[A-Za-z]:\\\\[\w\\\\.-]+#', '[path]', $message );
		// 移除 SQL 关键字提示。
		$message = preg_replace( '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b.*$/i', '[sql]', $message );

		return trim( $message );
	}

	/**
	 * 原子写入文件：先写临时文件，再 rename，确保文件不会出现半写入状态。
	 * 同时加 LOCK_EX 防止并发写入竞态。
	 *
	 * @param string $filepath 目标文件路径。
	 * @param string $content  文件内容。
	 * @return bool 写入成功返回 true。
	 * @throws \RuntimeException 写入或 rename 失败时抛出异常。
	 */
	public static function atomic_write( $filepath, $content ) : bool {
		$dir = dirname( $filepath );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$temp = $filepath . '.tmp.' . wp_rand( 10000, 99999 );

		// 写入临时文件（带排他锁）。
		$result = file_put_contents( $temp, $content, LOCK_EX );
		if ( false === $result ) {
			@unlink( $temp );
			throw new \RuntimeException( '写入临时文件失败' );
		}

		// 同步文件数据到磁盘（best-effort）。
		@chmod( $temp, 0644 );

		// 原子 rename（同分区下 rename 是原子的）。
		if ( ! @rename( $temp, $filepath ) ) {
			@unlink( $temp );
			throw new \RuntimeException( '原子重命名失败' );
		}

		return true;
	}
}
