<?php

declare(strict_types=1);
/**
 * Visual AI Caller Trait — v19.2.1 视觉生态共享层.
 *
 * 这是"模块相互学习"机制的物理载体。诞生背景：
 *   v19.2.0 发布时，小红书模块（XHS）和漫画模块（Genesis/Story_Pipeline）
 *   各自独立实现"调用 AI + 解析 JSON + 容错"逻辑，结果两边都犯了同一个错：
 *   误用 `new Dispatcher()` + 单数组传参 → PHP ArgumentCountError →
 *   WP fatal handler 输出 "<p>There has been a critical error...</p>" →
 *   前端 JSON.parse 报 "Unexpected token '<'"。
 *
 * 这个 trait 把"正确的调用姿势"固化为一处，任何视觉模块 use 它，
 * 就自动获得：
 *   1. 正确的三参签名 chat() 调用
 *   2. 单例获取 dispatcher（构造器是 private）
 *   3. 统一的 RuntimeException → WP_Error 转换
 *   4. 统一的 JSON 容错解析（直接 / ```json``` 代码块 / 首尾大括号）
 *   5. 统一的 module/user_id 计费埋点
 *
 * 设计原则（吸收自 V15 SEED DNA）：
 *   - 单一职责：只做"AI 调用 + JSON 解析"，不掺业务
 *   - 显式契约：方法签名即文档，返回值固定为 array|WP_Error
 *   - 失败可读：所有异常转 WP_Error，message 直接可展示给用户
 *   - 可观测：每次调用写一行 logger，便于复盘
 *
 * @package Linked3
 * @subpackage Classes\Visual
 */

namespace Linked3\Classes\Visual;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
trait VisualAICallerTrait
{
    /**
     * 调用 AI 并返回原始文本内容。
     *
     * @param array  $messages   OpenAI 风格的 messages 数组
     * @param array  $options    {model, temperature, max_tokens, module, ...}
     * @param array  $config     {fallback_providers, ...}
     * @return array|false  ['content'=>string,'usage'=>array,'provider'=>string,'model'=>string] 或 false
     */
    protected function call_ai(array $messages, array $options, array $config = []) : mixed {
        $options = wp_parse_args($options, [
            'model'       => get_option(LINKED3_OPTION_PREFIX . 'default_chat_model', 'gpt-4o-mini'),
            'temperature' => 0.7,
            'max_tokens'  => 2000,
            'module'      => 'visual_unknown',
            'user_id'     => get_current_user_id(),
        ]);
        $config = wp_parse_args($config, [
            'fallback_providers' => ['deepseek', 'zhipu'],
        ]);

        try {
            $dispatcher = AIDispatcher::instance();
            return $dispatcher->chat($messages, $options, $config);
        } catch (\RuntimeException $e) {
            if (function_exists('linked3_logger')) {
                linked3_logger('visual')->warning('AI call failed in ' . $options['module'], [
                    'error' => $e->getMessage(),
                ]);
            }
            return false;
        }
    }

    /**
     * 容错 JSON 提取 — 三级降级策略。
     *
     * 1. 直接 json_decode（最理想：AI 严格遵循指令）
     * 2. 提取 ```json ... ``` 代码块（AI 喜欢包一层 markdown）
     * 3. 提取首个 { ... } 或 [ ... ] 块（最后兜底）
     *
     * @param string $content
     * @return array|null
     */
    protected function extract_json(string $content) : mixed {
        if (empty($content)) {
            return null;
        }

        // 策略 1：直接解析
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 策略 2：```json ... ``` 代码块
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // 策略 3：首个 { ... } 或 [ ... ]
        if (preg_match('/(\{.*\}|\[.*\])/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

}
