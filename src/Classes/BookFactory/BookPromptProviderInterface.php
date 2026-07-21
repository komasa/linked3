<?php

declare(strict_types=1);
/**
 * BookPromptProviderInterface — extracted from BookAICallerInterface.php during PSR-4 migration.
 *
 * @package Linked3\Classes\BookFactory
 */

namespace Linked3\Classes\BookFactory;

if (!defined('ABSPATH')) exit;

interface BookPromptProviderInterface {

	/**
	 * 获取提示词 (三级回退: DB → JSON → 硬编码)。
	 *
	 * @param string $key      提示词键名。
	 * @param array  $variables 变量键值对。
	 * @return string 填充变量后的提示词。
	 */
	public function get( string $key, array $variables = array() ) : string;

	/**
	 * 保存提示词到 DB。
	 *
	 * @param string $key   提示词键名。
	 * @param string $value 提示词内容。
	 * @return bool
	 */
	public function save( string $key, string $value ) : bool;
}

/**
 * Interface BookCostTrackerInterface
 *
 * 成本追踪接口, 解耦成本计算与具体实现。
 */
