<?php

declare(strict_types=1);
/**
 * BookStateRepositoryInterface — extracted from BookAICallerInterface.php during PSR-4 migration.
 *
 * @package Linked3\Classes\BookFactory
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

interface BookStateRepositoryInterface {

	/**
	 * 加载项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @return array|null 状态数组, 不存在返回 null。
	 */
	public function load( string $project_id ) : ?array;

	/**
	 * 保存项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @param array  $state      状态数据。
	 * @return bool 保存成功返回 true。
	 */
	public function save( string $project_id, array $state ) : bool;

	/**
	 * 删除项目状态。
	 *
	 * @param string $project_id 项目 ID。
	 * @return bool
	 */
	public function delete( string $project_id ) : bool;
}

/**
 * Interface BookPromptProviderInterface
 *
 * 提示词提供者接口, 解耦提示词获取与具体来源 (DB/JSON/硬编码)。
 */
