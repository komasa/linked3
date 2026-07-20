<?php

declare(strict_types=1);
/**
 * Linked3_Book_Cost_Tracker_Interface — extracted from BookAICallerInterface.php during PSR-4 migration.
 *
 * @package Linked3\Classes\BookFactory
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

interface BookCostTrackerInterface {

	/**
	 * 记录一次 AI 调用的成本。
	 *
	 * @param string $project_id 项目 ID。
	 * @param array  $usage      token 用量 (prompt_tokens, completion_tokens)。
	 * @param float  $cost       美元成本。
	 * @param string $model      模型名称。
	 * @return void
	 */
	public function record( $project_id, $usage, $cost, $model );

	/**
	 * 获取项目累计成本。
	 *
	 * @param string $project_id 项目 ID。
	 * @return array array('total_cost'=>..., 'total_tokens'=>..., 'calls'=>...)
	 */
	public function get_total( $project_id );
}
