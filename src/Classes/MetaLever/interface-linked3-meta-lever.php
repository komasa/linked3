<?php
/**
 * Meta Lever Interface — v19.40 元提示词杠杆接口契约.
 *
 * 每个元能力杠杆实现此接口，通过注册表统一管理。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

interface Linked3_Meta_Lever_Interface
{
    /**
     * 杠杆唯一标识.
     *
     * @return string e.g. 'meta_learning'
     */
    public function id(): string;

    /**
     * 杠杆显示名称.
     *
     * @return string e.g. '通用元学习能力'
     */
    public function label(): string;

    /**
     * 杠杆描述.
     *
     * @return string
     */
    public function description(): string;

    /**
     * 注入到 system_prompt 的指令文本.
     *
     * 这是杠杆的核心——一段精心设计的元提示词，
     * 教会 AI "怎么思考"而非"思考什么"。
     *
     * @return string
     */
    public function system_prompt(): string;

    /**
     * 杠杆标签（用于自动匹配任务）.
     *
     * @return array e.g. ['learning', 'transfer', 'pattern']
     */
    public function tags(): array;

    /**
     * 该杠杆适用的任务类型.
     *
     * @return array e.g. ['xhs_generate', 'seo_article', 'comic_split']
     */
    public function applicable_tasks(): array;

    /**
     * 输出中应包含的 trace 字段名.
     *
     * 用于验证杠杆是否生效。
     *
     * @return string e.g. 'learning_trace'
     */
    public function trace_field(): string;
}
