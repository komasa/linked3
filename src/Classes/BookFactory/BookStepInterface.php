<?php

declare(strict_types=1);
/**
 * BookFactory 步骤接口 (v18.11 新增)
 *
 * 定义写书流水线中每个步骤的统一契约。
 * v18.10.3 中步骤通过 switch-case 硬编码路由, 新增步骤需修改 Book_Factory 核心代码。
 * v18.11 引入接口后, 步骤通过注册表注册, 新增步骤只需实现接口并注册,
 * 无需修改 Book_Factory::run_step() 的路由逻辑。
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
 * Interface BookStepInterface
 *
 * 写书流水线步骤接口契约。
 */
interface BookStepInterface {

	/**
	 * 获取步骤标识 (如 step1_demo, step4_expand)。
	 *
	 * @return string
	 */
	public function get_step_id();

	/**
	 * 获取步骤显示名称。
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * 执行步骤。
	 *
	 * @param BookProjectState $state 项目状态。
	 * @param BookFactory       $factory 工厂实例 (提供 AI 调用等能力)。
	 * @return array|WP_Error 返回执行结果数组或 WP_Error。
	 */
	public function execute( $state, $factory );

	/**
	 * 获取下一步骤 ID (用于链式调度)。
	 *
	 * @param BookProjectState $state 项目状态。
	 * @return string|null 下一步骤 ID, null 表示已完成。
	 */
	public function get_next_step( $state );
}
