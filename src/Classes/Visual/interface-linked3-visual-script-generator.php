<?php
/**
 * Visual Script Generator Interface — v19.2 统一视觉生态接口契约.
 *
 * 吸收小红书生成器的精华模式，为所有视觉脚本模块（小红书/漫画/图示/视频）
 * 定义统一接口，确保一致的生成流程和可扩展性。
 *
 * 核心模式（源自小红书生成器分析）：
 *   1. 结构化 JSON 输出 — AI 返回可解析的结构化数据
 *   2. 多页/多分镜架构 — 内容拆分为页面/分镜，每页含标题+内容+配图提示词
 *   3. 风格定制 — 用户可选预设风格或自定义风格提示词
 *   4. 封面特殊处理 — 首页/封面获得增强提示词
 *   5. 平台适配 — 每个平台有特定的格式、比例、语气要求
 *
 * @package Linked3
 * @subpackage Classes\Visual
 */

namespace Linked3\Classes\Visual;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Visual_Script_Generator_Interface
{
    /**
     * 生成视觉脚本内容（文本部分）。
     *
     * @param array $params {
     *   @type string $topic       主题
     *   @type string $keyword     关键词
     *   @type string $style       预设风格
     *   @type string $custom_style 自定义风格提示词
     *   @type int    $page_count  页数/分镜数（0=自动）
     *   @type string $model       AI 模型
     *   @type array  $v15_context V15 八维度上下文（可选）
     * }
     * @return array|\WP_Error {
     *   @type string $title       总标题
     *   @type string $main_content 摘要/正文
     *   @type array  $pages       页面数组 [{ title, content, image_prompt, is_cover }]
     * }
     */
    public function generate_script(array $params);

    /**
     * 获取平台标识（xhs/genesis/diagram/video）。
     *
     * @return string
     */
    public function platform();

    /**
     * 获取平台显示名称。
     *
     * @return string
     */
    public function platform_label();

    /**
     * 获取默认系统提示词模板。
     *
     * @return string
     */
    public function default_prompt_template();

    /**
     * 获取可用预设风格列表。
     *
     * @return array [{ id, label, prompt_suffix }]
     */
    public function available_styles();
}
