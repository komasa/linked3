<?php

declare(strict_types=1);
/**
 * Linked3_Book_State_Repository_Interface — extracted from BookAICallerInterface.php during PSR-4 migration.
 *
 * @package Linked3\Classes\BookFactory

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

interface Linked3_Book_State_Repository_Interface {

	/**
	 * 加载项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @return array|null 状态数组, 不存在返回 null。
	 */
	public function load( $project_id );

	/**
	 * 保存项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @param array  $state      状态数据。
	 * @return bool 保存成功返回 true。
	 */
	public function save( $project_id, $state );

	/**
	 * 删除项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @return bool
	 */
	public function delete( $project_id );
}

/**
 * Interface Linked3_Book_Prompt_Provider_Interface
 *
 * 提示词提供者接口, 解耦提示词获取与具体来源 (DB/JSON/硬编码)。
 */
