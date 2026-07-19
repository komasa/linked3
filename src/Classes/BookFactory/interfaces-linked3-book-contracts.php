<?php
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Linked3_Book_AI_Caller_Interface
 *
 * AI 调用抽象接口, 解耦 BookFactory 与具体 AI 引擎。
 */
interface Linked3_Book_AI_Caller_Interface {

	/**
	 * 调用 AI 生成内容。
	 *
	 * @param string $prompt  提示词。
	 * @param array  $options 调用选项 (model, temperature, max_tokens 等)。
	 * @param array  $context 调用上下文 (project_id, step, section 等, 用于日志与成本追踪)。
	 * @return array|WP_Error 返回 array('content'=>..., 'usage'=>..., 'cost'=>...) 或 WP_Error。
	 */
	public function call( $prompt, $options = array(), $context = array() );
}

/**
 * Interface Linked3_Book_State_Repository_Interface
 *
 * 状态仓储接口, 解耦状态读写与具体存储实现 (transient/JSON/DB)。
 */
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
interface Linked3_Book_Prompt_Provider_Interface {

	/**
	 * 获取提示词 (三级回退: DB → JSON → 硬编码)。
	 *
	 * @param string $key      提示词键名。
	 * @param array  $variables 变量键值对。
	 * @return string 填充变量后的提示词。
	 */
	public function get( $key, $variables = array() );

	/**
	 * 保存提示词到 DB。
	 *
	 * @param string $key   提示词键名。
	 * @param string $value 提示词内容。
	 * @return bool
	 */
	public function save( $key, $value );
}

/**
 * Interface Linked3_Book_Cost_Tracker_Interface
 *
 * 成本追踪接口, 解耦成本计算与具体实现。
 */
interface Linked3_Book_Cost_Tracker_Interface {

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
