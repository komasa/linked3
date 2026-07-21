<?php

declare(strict_types=1);
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

final class ChatTriggerEngine
{
}
