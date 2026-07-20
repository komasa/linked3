<?php

declare(strict_types=1);
/**
 * Chat session storage — CRUD on linked3_chat_logs.
 *
 * @package Linked3
 * @subpackage Classes\Chat\Storage
 */

namespace Linked3\Classes\Chat\Storage;

if (!defined('ABSPATH')) {
    exit;
}

final class ChatStorage
{
    /**
     * @param string $session_id
     * @param int    $bot_id
     * @param int    $user_id  0 for guest
     * @param string $module
     * @return array|null
     */
    public function get_session($session_id, $bot_id, $user_id, $module = 'chat') : mixed {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_chat_logs';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s AND bot_id = %d AND user_id = %d AND module = %s LIMIT 1",
            $session_id, $bot_id, $user_id, $module
        ), ARRAY_A);
        if (!$row) return null;
        $row['messages'] = json_decode($row['messages'], true) ?: [];
        return $row;
    }

    /**
     * Create a new session row.
     *
     * @param array $data
     * @return int
     */
    public function create_session(array $data) : mixed     {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_chat_logs';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (bot_id, user_id, session_id, conversation_uuid, module, messages, message_count, tokens_used) VALUES (%d, %d, %s, %s, %s, %s, %d, %d)",
            (int) ($data['bot_id'] ?? 0), (int) ($data['user_id'] ?? 0), sanitize_text_field($data['session_id'] ?? wp_generate_password(24, false)), sanitize_text_field($data['conversation_uuid'] ?? wp_generate_uuid4()), sanitize_text_field($data['module'] ?? 'chat'), wp_json_encode([]), 0, 0
        ));
        return (int) $wpdb->insert_id;
    }

    /**
     * Append a message to a session.
     *
     * @param string $session_id
     * @param int    $bot_id
     * @param int    $user_id
     * @param array  $message {role, content, ts}
     * @param int    $tokens_used
     * @return void
     */
    public function append_message($session_id, $bot_id, $user_id, array $message, $tokens_used = 0)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_chat_logs';
        $session = $this->get_session($session_id, $bot_id, $user_id);
        if (!$session) {
            $this->create_session(['session_id' => $session_id, 'bot_id' => $bot_id, 'user_id' => $user_id]);
            $session = $this->get_session($session_id, $bot_id, $user_id);
        }
        $messages = $session['messages'];
        $message['ts'] = time();
        $messages[] = $message;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET messages = %s, message_count = %d, tokens_used = %d WHERE id = %d",
            wp_json_encode($messages), count($messages), (int) $session['tokens_used'] + (int) $tokens_used, $session['id']
        ));
    }

}
