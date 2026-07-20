<?php

declare(strict_types=1);
/**
 * BookFactory 默认成本追踪器 (v19.0 新增)
 *
 * 实现 BookCostTrackerInterface, 委托给 Cost_Tracker Trait 的逻辑。
 * 作为默认实现, 保持与 v18.x 的向后兼容。
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
 * Class BookDefaultCostTracker
 *
 * 默认成本追踪器, 将成本数据写入项目状态。
 */
class BookDefaultCostTracker implements BookCostTrackerInterface {

	/**
	 * {@inheritdoc}
	 */
	public function record( $project_id, $usage, $cost, $model ) : void {
		$state = BookProjectState::get_project( $project_id );
		if ( ! $state ) {
			return;
		}

		// 累加成本。
		$total_cost   = (float) $state->get( 'total_cost', 0 ) + (float) $cost;
		$total_tokens = (int) $state->get( 'total_tokens', 0 );
		$total_calls  = (int) $state->get( 'total_calls', 0 ) + 1;

		if ( isset( $usage['prompt_tokens'] ) ) {
			$total_tokens += (int) $usage['prompt_tokens'];
		}
		if ( isset( $usage['completion_tokens'] ) ) {
			$total_tokens += (int) $usage['completion_tokens'];
		}

		$state->set( 'total_cost', $total_cost );
		$state->set( 'total_tokens', $total_tokens );
		$state->set( 'total_calls', $total_calls );
		$state->save_state();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_total( $project_id ) : array {
		$state = BookProjectState::get_project( $project_id );
		if ( ! $state ) {
			return array( 'total_cost' => 0, 'total_tokens' => 0, 'calls' => 0 );
		}

		return array(
			'total_cost'   => (float) $state->get( 'total_cost', 0 ),
			'total_tokens' => (int) $state->get( 'total_tokens', 0 ),
			'calls'        => (int) $state->get( 'total_calls', 0 ),
		);
	}
}
