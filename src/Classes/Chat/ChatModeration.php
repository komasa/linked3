<?php

declare(strict_types=1);
/**
 * Chat Moderation — 3-layer defence before AI dispatch.
 *
 * Layer 1: Banned Words — substring/regex match against the user message.
 * Layer 2: Banned IP    — blocklist of client IPs (with CIDR support).
 * Layer 3: OpenAI Moderation API — optional external classifier, fail-open
 *          (network/credential errors do not block the message; admin can
 *          switch to fail-closed via the `linked3/moderation_fail_closed`
 *          filter).
 *
 * TheModerator is invoked from Chat_Manager::chat() BEFORE the AI dispatch.
 * It returns ['ok'=>bool, 'reason'=>string, 'layer'=>string]; when ok=false
 * the Chat_Manager short-circuits and returns the rejection message.
 *
 * All three layers are option-driven:
 *   linked3_moderation_banned_words  — newline-separated list / regex per line
 *   linked3_moderation_banned_ips    — newline-separated list (IP or CIDR)
 *   linked3_moderation_openai_enabled — bool (default false; enable when key present)
 *
 * @package Linked3
 * @subpackage Classes\Chat
 */

namespace Linked3\Classes\Chat;

use Linked3\Classes\Security\RateLimiter;
use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class ChatModeration
{
    /** OpenAI Moderation endpoint (allowed host whitelisted via Safe_Remote). */
    const MODERATION_URL = 'https://api.openai.com/v1/moderations';

    /** Categories flagged by OpenAI that we treat as hard-blocks. */
    const HARD_CATEGORIES = ['hate', 'hate/threatening', 'harassment', 'harassment/threatening', 'self-harm', 'self-harm/intent', 'self-harm/instructions', 'sexual', 'sexual/minors', 'violence', 'violence/graphic'];

    /**
     * Run all 3 layers against the incoming user message.
     *
     * @param string $message
     * @param array  $context {ip, user_id}
     * @return array{ok:bool, reason:string, layer:string}
     */
    public function check($message, array $context = []) : mixed {
        $message = (string) $message;
        $ip = isset($context['ip']) ? (string) $context['ip'] : RateLimiter::client_ip();

        // Layer 1: Banned Words.
        $bw = $this->check_banned_words($message);
        if (!$bw['ok']) return $bw;

        // Layer 2: Banned IP.
        $bi = $this->check_banned_ip($ip);
        if (!$bi['ok']) return $bi;

        // Layer 3: OpenAI Moderation (optional, fail-open by default).
        $om = $this->check_openai_moderation($message);
        if (!$om['ok']) return $om;

        return ['ok' => true, 'reason' => '', 'layer' => ''];
    }

    /**
     * Layer 1 — Banned Words.
     * Each line in the option is a pattern; lines starting with `/` are
     * treated as regex (PCRE), otherwise case-insensitive substring match.
     *
     * @param string $message
     * @return array{ok:bool, reason:string, layer:string}
     */
    private function check_banned_words($message)
    : array {
        $raw = get_option(LINKED3_OPTION_PREFIX . 'moderation_banned_words', '');
        $patterns = $this->parse_lines($raw);
        if (empty($patterns)) {
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        }
        $msg_lower = mb_strtolower($message);
        foreach ($patterns as $p) {
            if ($p === '') continue;
            if ($p[0] === '/') {
                // Regex mode — silence eval errors (bad pattern from admin).
                $m = @preg_match($p, $message);
                if ($m === 1) {
                    $this->log_block('banned_words', 'regex match: ' . $p);
                    return [
                        'ok'     => false,
                        'reason' => __('您的消息包含不允许的内容。', 'linked3'),
                        'layer'  => 'banned_words',
                    ];
                }
            } else {
                $p_lower = mb_strtolower($p);
                if (strpos($msg_lower, $p_lower) !== false) {
                    $this->log_block('banned_words', 'substring match: ' . $p);
                    return [
                        'ok'     => false,
                        'reason' => __('您的消息包含不允许的内容。', 'linked3'),
                        'layer'  => 'banned_words',
                    ];
                }
            }
        }
        return ['ok' => true, 'reason' => '', 'layer' => ''];
    }

    /**
     * Layer 2 — Banned IP (supports single IPs and CIDR ranges).
     *
     * @param string $ip
     * @return array{ok:bool, reason:string, layer:string}
     */
    private function check_banned_ip($ip)
    : array {
        if ($ip === '') {
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        }
        $raw = get_option(LINKED3_OPTION_PREFIX . 'moderation_banned_ips', '');
        $entries = $this->parse_lines($raw);
        if (empty($entries)) {
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        }
        foreach ($entries as $entry) {
            if ($entry === '') continue;
            if ($this->ip_matches($ip, $entry)) {
                $this->log_block('banned_ip', 'ip ' . $ip . ' matched ' . $entry);
                return [
                    'ok'     => false,
                    'reason' => __('您的 IP 已被禁止使用对话。', 'linked3'),
                    'layer'  => 'banned_ip',
                ];
            }
        }
        return ['ok' => true, 'reason' => '', 'layer' => ''];
    }

    /**
     * Layer 3 — OpenAI Moderation API (optional).
     *
     * Fail-open by default: if the API key is missing, the request fails,
     * or the response is malformed, the message is allowed through. Set the
     * `linked3/moderation_fail_closed` filter to true to flip to fail-closed.
     *
     * @param string $message
     * @return array{ok:bool, reason:string, layer:string}
     */
    private function check_openai_moderation($message) : mixed     {
        if (!get_option(LINKED3_OPTION_PREFIX . 'moderation_openai_enabled', 0)) {
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        }
        // Skip for very short messages (no point in API call).
        if (mb_strlen(trim($message)) < 2) {
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        }

        $api_key = $this->get_openai_key();
        if ($api_key === '') {
            return $this->fail_open('no API key configured');
        }

        try {
            $resp = SafeRemote::post(self::MODERATION_URL, [
                'timeout'       => 10,
                'headers'       => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'          => wp_json_encode([
                    'model' => 'omni-moderation-latest',
                    'input' => $message,
                ]),
                'allowed_hosts' => ['api.openai.com'],
            ]);

            if (is_wp_error($resp)) {
                return $this->fail_open('request error: ' . $resp->get_error_message());
            }
            $code = wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) {
                return $this->fail_open('non-2xx response code=' . $code);
            }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (!is_array($body) || !isset($body['results'][0])) {
                return $this->fail_open('malformed response body');
            }
            $result = $body['results'][0];
            if (!empty($result['flagged'])) {
                $cats = array_keys(array_filter([
                    'hate'                    => $result['categories']['hate'] ?? false,
                    'hate/threatening'        => $result['categories']['hate/threatening'] ?? false,
                    'harassment'              => $result['categories']['harassment'] ?? false,
                    'harassment/threatening'  => $result['categories']['harassment/threatening'] ?? false,
                    'self-harm'               => $result['categories']['self-harm'] ?? false,
                    'self-harm/intent'        => $result['categories']['self-harm/intent'] ?? false,
                    'self-harm/instructions'  => $result['categories']['self-harm/instructions'] ?? false,
                    'sexual'                  => $result['categories']['sexual'] ?? false,
                    'sexual/minors'           => $result['categories']['sexual/minors'] ?? false,
                    'violence'                => $result['categories']['violence'] ?? false,
                    'violence/graphic'        => $result['categories']['violence/graphic'] ?? false,
                ]));
                $this->log_block('openai_moderation', 'flagged categories: ' . implode(',', $cats));
                return [
                    'ok'     => false,
                    'reason' => __('您的消息被审核系统标记,请重新表述。', 'linked3'),
                    'layer'  => 'openai_moderation',
                ];
            }
            return ['ok' => true, 'reason' => '', 'layer' => ''];
        } catch (\Exception $e) {
            return $this->fail_open('exception: ' . $e->getMessage());
        }
    }

    /**
     * Fail-open policy: log a warning and allow the message through.
     * Set `linked3/moderation_fail_closed` to true to flip to fail-closed
     * (block on any moderation-layer error).
     *
     * @param string $detail
     * @return array{ok:bool, reason:string, layer:string}
     */
    private function fail_open($detail)
    : array {
        $fail_closed = (bool) apply_filters('linked3/moderation_fail_closed', false);
        if ($fail_closed) {
            $this->log_block('openai_moderation', 'fail-closed: ' . $detail);
            return [
                'ok'     => false,
                'reason' => __('消息无法通过审核服务验证,请稍后重试。', 'linked3'),
                'layer'  => 'openai_moderation',
            ];
        }
        $this->log_block('openai_moderation', 'fail-open: ' . $detail);
        return ['ok' => true, 'reason' => '', 'layer' => ''];
    }

    /**
     * Resolve the OpenAI API key from the provider_keys option.
     *
     * @return string
     */
    private function get_openai_key() : mixed {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        return is_array($keys) && isset($keys['openai']) ? (string) $keys['openai'] : '';
    }

    /**
     * Parse a textarea into trimmed non-empty lines.
     *
     * @param string $raw
     * @return string[]
     */
    private function parse_lines($raw) : mixed     {
        $raw = (string) $raw;
        if ($raw === '') return [];
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }

    /**
     * Check whether $ip matches $rule (single IP or CIDR).
     * Supports IPv4 and IPv6.
     *
     * @param string $ip
     * @param string $rule
     * @return bool
     */
    private function ip_matches($ip, $rule)
    {
        // CIDR notation.
        if (strpos($rule, '/') !== false) {
            [$net, $mask] = explode('/', $rule, 2);
            $mask = (int) $mask;
            if ($mask <= 0) return false;
            $ip_bin = @inet_pton($ip);
            $net_bin = @inet_pton($net);
            if ($ip_bin === false || $net_bin === false) return false;
            $len_bits = strlen($ip_bin) * 8;
            if ($mask > $len_bits) return false;
            $full_bytes = (int) ($mask / 8);
            $remainder_bits = $mask % 8;
            for ($i = 0; $i < $full_bytes; $i++) {
                if ($ip_bin[$i] !== $net_bin[$i]) return false;
            }
            if ($remainder_bits > 0 && $full_bytes < strlen($ip_bin)) {
                $ip_byte = ord($ip_bin[$full_bytes]);
                $net_byte = ord($net_bin[$full_bytes]);
                $mask_byte = (0xFF << (8 - $remainder_bits)) & 0xFF;
                if (($ip_byte & $mask_byte) !== ($net_byte & $mask_byte)) return false;
            }
            return true;
        }
        // Exact match (single IP).
        return hash_equals($rule, $ip);
    }

    /**
     * @param string $layer
     * @param string $detail
     * @return void
     */
    private function log_block($layer, $detail)
    : void {
        if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
            Logger::instance()->warning('chat', 'Moderation block: ' . $layer . ' — ' . $detail);
        }
    }
}
