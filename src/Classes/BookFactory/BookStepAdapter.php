<?php

declare(strict_types=1);
/**
 * BookFactory 步骤适配器 (v18.11 新增)
 *
 * 将 Book_Factory 中现有的 execute_stepN_xxx 方法适配为 BookStepInterface。
 * 采用适配器模式, 避免一次性重构全部步骤方法, 实现渐进式迁移。
 *
 * 未来 v19.x 可将每个步骤拆分为独立的类, 直接实现接口,
 * 届时适配器可逐步移除。
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
 * Class BookStepAdapter
 */
class BookStepAdapter implements BookStepInterface {

	/**
	 * 步骤 ID。
	 *
	 * @var string
	 */
	protected $step_id;

	/**
	 * 步骤显示名称。
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Book_Factory 中的方法名。
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * 下一步骤 ID。
	 *
	 * @var string|null
	 */
	protected $next_step;

	/**
	 * 构造函数。
	 *
	 * @param string      $step_id   步骤 ID。
	 * @param string      $label     显示名称。
	 * @param string      $method    Book_Factory 方法名。
	 * @param string|null $next_step 下一步骤 ID。
	 */
	public function __construct( $step_id, $label, $method, $next_step ) {
		$this->step_id   = $step_id;
		$this->label     = $label;
		$this->method    = $method;
		$this->next_step = $next_step;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_step_id() : string {
		return $this->step_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( BookProjectState $state, object $factory ): array|WP_Error {
		if ( ! method_exists( $factory, $this->method ) ) {
			return new WP_Error(
				'method_not_found',
				sprintf( '步骤方法 %s 不存在', $this->method )
			);
		}
		return call_user_func( array( $factory, $this->method ), $state );
	}

}
