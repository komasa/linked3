<?php
/**
 * Chat Triggers — condition → action engine.
 *
 * Mirrors aipower's triggers/ three-layer pattern:
 *   - ConditionRunner: evaluates whether a trigger should fire
 *   - ActionExecutor: performs the action (popup/redirect/message)
 *   - EventProcessor: listens to DOM events (entrance/scroll/exit-intent)
 *
 * Triggers are stored as postmeta-like JSON config per bot.
 *
 * @package Linked3
 * @subpackage Classes\Chat\Triggers
 */

namespace Linked3\Classes\Chat\Triggers;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Chat_Trigger_Engine
{
    /**
     * Evaluate a trigger's condition against the current context.
     *
     * @param array $trigger {condition: {type, params}, action: {type, params}}
     * @param array $context {time_on_page, scroll_pct, exit_intent, visits, user_logged_in}
     * @return bool
     */
    public function should_fire(array $trigger, array $context) : mixed {
        $cond = $trigger['condition'] ?? [];
        $type = $cond['type'] ?? '';
        $params = $cond['params'] ?? [];
        switch ($type) {
            case 'time_on_page':
                return ($context['time_on_page'] ?? 0) >= (int) ($params['seconds'] ?? 30);
            case 'scroll_pct':
                return ($context['scroll_pct'] ?? 0) >= (int) ($params['pct'] ?? 50);
            case 'exit_intent':
                return !empty($context['exit_intent']);
            case 'visits':
                return ($context['visits'] ?? 0) >= (int) ($params['count'] ?? 3);
            case 'logged_out':
                return empty($context['user_logged_in']);
            case 'url_contains':
                $needle = $params['needle'] ?? '';
                return $needle && strpos($context['url'] ?? '', $needle) !== false;
            default:
                return false;
        }
    }

    /**
     * Build the action payload to send to the frontend.
     *
     * @param array $trigger
     * @return array {type, params}
     */
    public function build_action(array $trigger) : mixed     {
        return $trigger['action'] ?? ['type' => 'message', 'params' => ['text' => __('您好!需要帮助吗?', 'linked3')]];
    }
}
