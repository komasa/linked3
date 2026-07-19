<?php
/**
 * BookFactory 默认 AI 调用器 (v19.0 新增)
 *
 * 实现 Linked3_Book_AI_Caller_Interface, 委托给现有的 AI 引擎。
 * 作为默认实现, 保持与 v18.x 的向后兼容。
 *
 * 未来可替换为其他实现 (如多模型适配器、Mock 测试器)。
 *
 * @package Linked3\BookFactory
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Linked3_Book_Default_AI_Caller
 *
 * 默认 AI 调用器, 委托给 Linked3_AI_Engine 或 Linked3_OpenAI。
 */
class Linked3_Book_Default_AI_Caller implements Linked3_Book_AI_Caller_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function call( $prompt, $options = array(), $context = array() ) {
		// 委托给现有的 AI 引擎。
		// v19.0 保持与 v18.x 的调用方式一致, 未来可在此处增加多模型路由。
		if ( class_exists( '\Linked3\Classes\BookFactory\Linked3_AI_Engine' ) ) {
			$result = Linked3_AI_Engine::generate( $prompt, $options );
		} elseif ( class_exists( '\Linked3\Classes\BookFactory\Linked3_OpenAI' ) ) {
			$result = Linked3_OpenAI::chat( $prompt, $options );
		} else {
			return new WP_Error( 'ai_engine_missing', 'AI 引擎未加载' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 标准化返回格式。
		return array(
			'content' => isset( $result['content'] ) ? $result['content'] : ( is_string( $result ) ? $result : '' ),
			'usage'   => isset( $result['usage'] ) ? $result['usage'] : array(),
			'cost'    => isset( $result['cost'] ) ? $result['cost'] : 0,
			'model'   => isset( $options['model'] ) ? $options['model'] : 'default',
		);
	}
}
