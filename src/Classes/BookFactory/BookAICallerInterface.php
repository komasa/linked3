<?php

declare(strict_types=1);
/**
 * BookFactory 接口契约体系 (v19.0 新增)
 *
 * 定义写书式写作系统的核心接口契约, 实现依赖倒置原则 (DIP)。
 * v18.x 中各模块通过具体类直接耦合, v19.0 引入接口后,
 * 模块间通过接口协作, 可独立替换实现, 支持单元测试 mock。
 *
 * @package Linked3\BookFactory\Interfaces
 * @since   19.0
 */

// Exit if accessed directly.
namespace Linked3\Classes\BookFactory;

use WP_Error;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface BookAICallerInterface
 *
 * AI 调用抽象接口, 解耦 BookFactory 与具体 AI 引擎。
 */
interface BookAICallerInterface {

	/**
	 * 调用 AI 生成内容。
	 *
	 * @param string $prompt  提示词。
	 * @param array  $options 调用选项 (model, temperature, max_tokens 等)。
	 * @param array  $context 调用上下文 (project_id, step, section 等, 用于日志与成本追踪)。
	 * @return array|WP_Error 返回 array('content'=>..., 'usage'=>..., 'cost'=>...) 或 WP_Error。
	 */
	public function call( string $prompt, array $options = array(), array $context = array() ): array|WP_Error ;
}


