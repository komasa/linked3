<?php

declare(strict_types=1);
/**
 * Chat Manager — orchestrates a chat turn:
 *   moderation → quota → RAG (fail-open) → AI dispatch → store.
 *
 * @package Linked3
 * @subpackage Classes\Chat
 */

namespace Linked3\Classes\Chat;

use Linked3\Classes\Chat\Storage\ChatStorage;
use Linked3\Includes\Log\Linked3_Logger;



if (!defined('ABSPATH')) {
    exit;
}
use Linked3\Classes\Core\{AIDispatcher, TokenManager};
use Linked3\Classes\License\{LicenseService, PlanDefinitions};
final class ChatManager
{
    /** @var self|null */
    private static $instance;

    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Linked3_Container')) {
                $container = \Linked3\Includes\Linked3_Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Handle a chat turn from a logged-in user or guest.
     *
     * @param string $session_id
     * @param string $message
     * @param int    $bot_id
     * @param array  $bot_config {provider, model, system_prompt, use_rag, temperature}
     * @return array{ok:bool, reply:string, sources:array, usage:array, message:string}
     */
    public function chat($session_id, $message, $bot_id, array $bot_config)
    : array {
        // v0.8.0 hardening: allow callers (e.g. AutoGPT comment-reply
        // processor) to override the operating user via $bot_config['user_id']
        // so background tasks bill against the task OWNER's quota instead of
        // falling into the guest branch (which would (a) enforce the 10/day
        // guest request ceiling against every comment reply and (b) bleed
        // into the shared guest token bucket).
        $user_id = isset($bot_config['user_id']) ? (int) $bot_config['user_id'] : get_current_user_id();
        $is_guest = $user_id === 0;

        // 0) Moderation 3-layer pre-check (BannedWords + BannedIP + OpenAI).
        $moderation = new ChatModeration();
        $mod = $moderation->check($message, ['user_id' => $user_id]);
        if (!$mod['ok']) {
            return ['ok' => false, 'reply' => '', 'sources' => [], 'usage' => [], 'message' => $mod['reason']];
        }

        // 1) Quota check (also increments guest request counter atomically).
        $quota = $this->check_quota($user_id, $session_id, $bot_id);
        if (!$quota['ok']) {
            return ['ok' => false, 'reply' => '', 'sources' => [], 'usage' => [], 'message' => $quota['message']];
        }

        // 2) Load history.
        $storage = new \Linked3\Classes\Chat\Storage\ChatStorage();
        $session = $storage->get_session($session_id, $bot_id, $user_id);
        if (!$session) {
            $storage->create_session(['session_id' => $session_id, 'bot_id' => $bot_id, 'user_id' => $user_id]);
            $session = $storage->get_session($session_id, $bot_id, $user_id);
        }
        $messages = $session['messages'];

        // 3) RAG retrieval (optional, fail-open — vector store errors must
        //    never block the chat, just degrade to no-context mode).
        $sources = [];
        $context_prompt = '';
        if (!empty($bot_config['use_rag'])) {
            try {
                $rag = new RAGRetriever();
                $sources = $rag->retrieve($message, 5);
                $context_prompt = $rag->build_context_prompt($sources);
            } catch (\Exception $e) {
                $this->log('warning', 'RAG retrieval failed, degrading to no-context: ' . $e->getMessage());
                $sources = [];
                $context_prompt = '';
            }
        }

        // 4) Build the full message array for the AI.
        $system = $bot_config['system_prompt'] ?? __('您是一位乐于助人的助手。', 'linked3');
        if ($context_prompt) {
            $system .= "\n\n" . $context_prompt;
        }
        $ai_messages = [['role' => 'system', 'content' => $system]];
        // Keep last 10 turns of history.
        $history = array_slice($messages, -10);
        foreach ($history as $m) {
            if (in_array($m['role'], ['user', 'assistant'], true)) {
                $ai_messages[] = ['role' => $m['role'], 'content' => $m['content']];
            }
        }
        $ai_messages[] = ['role' => 'user', 'content' => $message];

        // 5) Dispatch to AI. Pass session_id + bot_id via $options so the
        //    AI_Dispatcher's internal Token_Manager::record() call writes
        //    to the correct (session_id, bot_id) row.
        try {
            $result = AIDispatcher::instance()->chat(
                $ai_messages,
                [
                    'provider'    => $bot_config['provider'] ?? 'openai',
                    'model'       => $bot_config['model'] ?? 'gpt-4o-mini',
                    'temperature' => $bot_config['temperature'] ?? 0.7,
                    'max_tokens'  => $bot_config['max_tokens'] ?? 1000,
                    'module'      => 'chat',
                    'session_id'  => $session_id,
                    'bot_id'      => $bot_id,
                    'user_id'     => $user_id,
                ],
                [
                    'fallback_providers' => [],
                ]
            );
        } catch (\Exception $e) {
            return ['ok' => false, 'reply' => '', 'sources' => [], 'usage' => [], 'message' => $e->getMessage()];
        }

        // 6) Token usage is already recorded by AI_Dispatcher::chat() (it
        //    calls Token_Manager::record() with session_id+bot_id from
        //    $options). We must NOT call record() again here — that would
        //    double-count tokens AND guest request counter.

        // 7) Store both messages (assistant tokens for accounting display).
        $tokens = isset($result['usage']['total_tokens']) ? (int) $result['usage']['total_tokens'] : 0;
        $storage->append_message($session_id, $bot_id, $user_id, ['role' => 'user', 'content' => $message], 0);
        $storage->append_message($session_id, $bot_id, $user_id, ['role' => 'assistant', 'content' => $result['content']], $tokens);

        return [
            'ok' => true,
            'reply' => $result['content'],
            'sources' => $sources,
            'usage' => $result['usage'],
            'message' => 'ok',
        ];
    }

    /**
     * @param int    $user_id
     * @param string $session_id
     * @param int    $bot_id
     * @return array{ok:bool, message:string}
     */
    private function check_quota($user_id, $session_id, $bot_id)
    : array {
        if ($user_id > 0) {
            $plan = LicenseService::instance()->plan();
            $module_access = PlanDefinitions::module_access($plan, 'chat');
            if ($module_access === false) {
                return ['ok' => false, 'message' => __('当前套餐不可使用对话功能。', 'linked3')];
            }
            $check = TokenManager::instance()->check($user_id, '', 100);
            if (!$check['ok']) {
                return ['ok' => false, 'message' => __('每日 Token 配额已用完。', 'linked3')];
            }
        } else {
            // Guest — daily request count via linked3_guest_token_usage.
            // Increment atomically BEFORE the AI dispatch so quota is
            // enforced even if the AI call later fails (prevents
            // DOS-by-failure bypass). The (session_id, bot_id) composite
            // UNIQUE key matches what Token_Manager::record_guest() writes.
            global $wpdb;
            $table = $wpdb->prefix . 'linked3_guest_token_usage';
            $now = current_time('mysql', true); // UTC stamp
            $guest_limit = (int) get_option(LINKED3_OPTION_PREFIX . 'guest_chat_limit', 10);

            // guest_limit = 0 means "disabled for guests" (admin hint).
            if ($guest_limit <= 0) {
                return ['ok' => false, 'message' => __('游客对话已禁用,请注册或登录。', 'linked3')];
            }

            // Read current counter for this (session_id, bot_id).
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT requests, reset_at FROM {$table} WHERE session_id = %s AND bot_id = %d LIMIT 1",
                $session_id, $bot_id
            ), ARRAY_A);
            $requests = $row ? (int) $row['requests'] : 0;
            // Reset if past 24h (rolling window from last reset_at).
            if ($row && strtotime($row['reset_at']) < time() - DAY_IN_SECONDS) {
                $requests = 0;
            }
            if ($requests >= $guest_limit) {
                return ['ok' => false, 'message' => sprintf(
                    /* translators: %d: daily message limit. */
                    __('游客限制已达到(每天 %d 条消息),请注册或登录。', 'linked3'),
                    $guest_limit
                )];
            }
            // Increment now (atomic). tokens_used is updated separately by
            // Token_Manager::record_guest() after the AI call succeeds; we
            // only touch `requests` + `reset_at` here.
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (session_id, bot_id, tokens_used, requests, reset_at)
                 VALUES (%s, %d, 0, 1, %s)
                 ON DUPLICATE KEY UPDATE
                   requests = requests + 1,
                   reset_at = %s",
                $session_id, $bot_id, $now, $now
            ));
        }
        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * @param string $provider
     * @return string
     */
    private function get_api_key($provider) : mixed {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        return is_array($keys) && isset($keys[$provider]) ? $keys[$provider] : '';
    }

    /**
     * @param string $level
     * @param string $message
     * @return void
     */
    private function log($level, $message)
    : void {
        if (class_exists('\\Linked3\\Includes\\Log\\Linked3_Logger')) {
            Linked3_Logger::instance()->log('chat', $level, $message);
        }
    }
}
