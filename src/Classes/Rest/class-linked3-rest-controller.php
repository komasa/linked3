<?php
/**
 * REST API — exposes Linked3 features to external apps / mobile / integrations.
 *
 * Endpoints (all under /wp-json/linked3/v1/):
 *   POST /chat          — send a chat message
 *   POST /generate      — generate content
 *   GET  /usage         — current usage stats
 *   GET  /tasks         — list AutoGPT tasks
 *   POST /tts           — synthesize speech
 *   GET  /stream/:key   — poll SSE stream chunks (v4.7.3)
 *
 * Auth: WordPress Application Password (Basic auth) OR JWT (v1.0.x).
 * Rate limit: 60 req/min/token (via Rate_Limiter).
 *
 * @package Linked3
 * @subpackage Classes\Rest
 */

namespace Linked3\Classes\Rest;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Rest_Controller
{
    // NOTE: cannot use `const NAMESPACE` — `namespace` is a PHP reserved
    // keyword and case-insensitive, so even uppercase `NAMESPACE` is a parse
    // error in PHP 7.4+. Renamed to REST_NAMESPACE (v1.0.0 FINAL-AUDIT fix).
    const REST_NAMESPACE = 'linked3/v1';

    public static function register() : mixed {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('rest_api_init', static function () {
            // Surface a discovery ping endpoint so mobile/integration clients
            // can confirm the plugin is active without auth (no sensitive data).
            register_rest_route(self::REST_NAMESPACE, '/ping', [
                'methods'  => 'GET',
                'callback' => static function () {
                    return rest_ensure_response(['ok' => true, 'plugin' => 'linked3', 'version' => LINKED3_VERSION]);
                },
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public static function register_routes()
    : void {
        register_rest_route(self::REST_NAMESPACE, '/chat', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'chat'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/generate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'generate'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/usage', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'usage'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/tasks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'tasks'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/tts', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'tts'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // v4.7.3: SSE stream polling endpoint.
        register_rest_route(self::REST_NAMESPACE, '/stream/(?P<cache_key>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'stream_poll'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // v4.9.1: Billing webhook receiver. No auth — verified by HMAC signature.
        // This is the push endpoint for Stripe / 支付宝 / 微信 webhook callbacks.
        register_rest_route(self::REST_NAMESPACE, '/webhook/billing', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'billing_webhook'],
            'permission_callback' => '__return_true', // verified by signature inside
        ]);
    }

    /**
     * @param \WP_REST_Request $req
     * @return true|\WP_Error
     */
    public static function check_access($req) : mixed     {
        // Require authentication (v2.9.0: 移除 plan gate,本地模式开放 REST API).
        if (!is_user_logged_in()) {
            return new \WP_Error('rest_unauthorized', __('需要认证。', 'linked3'), ['status' => 401]);
        }
        // Rate limit: 60 req/min/user.
        $user_id = get_current_user_id();
        $bucket = 'linked3_rest_rl_' . $user_id;
        $count = (int) get_transient($bucket);
        if ($count >= 60) {
            return new \WP_Error('rest_rate_limited', __('速率限制:每分钟 60 次请求。', 'linked3'), ['status' => 429]);
        }
        set_transient($bucket, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    public static function chat($req) : mixed {
        $message = sanitize_textarea_field($req->get_param('message') ?? '');
        $bot_id = (int) ($req->get_param('bot_id') ?? 0);
        $session_id = sanitize_text_field($req->get_param('session_id') ?? wp_generate_password(24, false));
        if (empty($message)) {
            return new \WP_Error('bad_request', __('需要消息内容。', 'linked3'), ['status' => 400]);
        }
        $bot_config = [
            'provider' => sanitize_text_field($req->get_param('provider') ?? 'openai'),
            'model' => sanitize_text_field($req->get_param('model') ?? 'gpt-4o-mini'),
            'system_prompt' => sanitize_textarea_field($req->get_param('system_prompt') ?? ''),
            'temperature' => (float) ($req->get_param('temperature') ?? 0.7),
            'max_tokens' => (int) ($req->get_param('max_tokens') ?? 1000),
        ];
        $result = \Linked3\Classes\Chat\Linked3_Chat_Manager::instance()->chat($session_id, $message, $bot_id, $bot_config);
        if (!$result['ok']) {
            return new \WP_Error('chat_failed', $result['message'], ['status' => 502]);
        }
        return rest_ensure_response($result);
    }

    public static function generate($req) : void     {
        $keyword = sanitize_text_field($req->get_param('keyword') ?? '');
        $title = sanitize_text_field($req->get_param('title') ?? '');
        if (empty($keyword) && empty($title)) {
            return new \WP_Error('bad_request', __('需要关键词或标题。', 'linked3'), ['status' => 400]);
        }
        // Delegate to Content Writer's AI dispatcher directly.
        $sys = (new \Linked3\Classes\ContentWriter\Prompt\Linked3_System_Instruction_Builder())->build([]);
        $user = (new \Linked3\Classes\ContentWriter\Prompt\Linked3_User_Prompt_Builder())->build(['keyword' => $keyword, 'title' => $title, 'word_count' => 1200]);
        try {
            $result = \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance()->chat(
                [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]],
                ['provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'), 'model' => 'gpt-4o-mini', 'module' => 'rest'],
                ['fallback_providers' => []]
            );
            return rest_ensure_response(['content' => $result['content'] ?? '', 'usage' => $result['usage']]);
        } catch (\Exception $e) {
            return new \WP_Error('gen_failed', $e->getMessage(), ['status' => 502]);
        }
    }

    public static function usage($req)
    {
        return rest_ensure_response((new \Linked3\Classes\Dashboard\Linked3_Dashboard())->overview());
    }

    public static function tasks($req)
    {
        $repo = new \Linked3\Classes\AutoGPT\Linked3_AutoGPT_Task_Repository();
        return rest_ensure_response(['tasks' => $repo->all(get_current_user_id())]);
    }

    public static function tts($req)
    {
        $text = sanitize_textarea_field($req->get_param('text') ?? '');
        if (empty($text)) {
            return new \WP_Error('bad_request', __('需要文本内容。', 'linked3'), ['status' => 400]);
        }
        $tts = new \Linked3\Classes\Speech\TtsManager();
        $result = $tts->synthesize($text, sanitize_text_field($req->get_param('voice') ?? 'alloy'), [
            'provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
            'api_key' => '',
            'model' => 'tts-1',
        ]);
        if (!$result['ok']) {
            return new \WP_Error('tts_failed', $result['message'], ['status' => 502]);
        }
        return rest_ensure_response($result);
    }

    /**
     * v4.7.3: Poll an SSE stream by cache_key.
     *
     * Reads the accumulated payload from the linked3_sse_message_cache
     * table and returns it. The frontend calls this repeatedly (every
     * 500ms-1s) until `status` is `done` or `error`.
     *
     * @param \WP_REST_Request $req
     * @return \WP_REST_Response|\WP_Error
     */
    public static function stream_poll($req)
    {
        global $wpdb;
        $cache_key = sanitize_text_field($req->get_param('cache_key') ?? '');

        // Validate the cache_key format (must start with linked3_stream_).
        if (strpos($cache_key, 'linked3_stream_') !== 0) {
            return new \WP_Error('bad_cache_key', __('无效的 stream key。', 'linked3'), ['status' => 400]);
        }

        $table = $wpdb->prefix . 'linked3_sse_message_cache';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT payload, created_at, expires_at FROM {$table} WHERE cache_key = %s LIMIT 1",
            $cache_key
        ), ARRAY_A);

        if (!$row) {
            return new \WP_Error('stream_not_found', __('Stream 不存在或已过期。', 'linked3'), ['status' => 404]);
        }

        // Check expiry.
        $expires_at = strtotime($row['expires_at']);
        if ($expires_at && $expires_at < time()) {
            $wpdb->delete($table, ['cache_key' => $cache_key], ['%s']);
            return new \WP_Error('stream_expired', __('Stream 已过期。', 'linked3'), ['status' => 410]);
        }

        $payload = json_decode($row['payload'], true);
        if (!is_array($payload)) {
            $payload = ['chunks' => [], 'status' => 'pending'];
        }

        return rest_ensure_response([
            'cache_key'  => $cache_key,
            'chunks'     => $payload['chunks'] ?? [],
            'status'     => $payload['status'] ?? 'pending',
            'content'    => $payload['content'] ?? '',
            'done'       => ($payload['status'] ?? '') === 'done',
            'error'      => $payload['error'] ?? null,
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
        ]);
    }

    /**
     * v4.9.1: Billing webhook receiver.
     *
     * Accepts POST requests from Stripe / 支付宝 / 微信 payment gateways.
     * Verifies the HMAC-SHA256 signature, logs the raw payload to the
     * linked3_billing_events table, then updates the user's plan.
     *
     * Expected headers:
     *   X-Linked3-Signature: t=<timestamp>,v1=<hmac>
     *   X-Linked3-Provider: stripe|alipay|wechat
     *
     * Expected JSON body (provider-agnostic):
     *   {
     *     "event_type": "subscription.created|payment.succeeded|...",
     *     "license_key": "LINKED3-PRO-XXXX",
     *     "plan": "pro|premium",
     *     "amount": 99.00,
     *     "currency": "USD",
     *     "user_id": 123
     *   }
     *
     * @param \WP_REST_Request $req
     * @return \WP_REST_Response
     */
    public static function billing_webhook($req)
    {
        // Get the raw body for signature verification.
        $raw_body = $req->get_body();
        $provider = sanitize_text_field($req->get_header('x_linked3_provider') ?? 'unknown');
        $sig_header = $req->get_header('x_linked3_signature') ?? '';

        // Verify HMAC signature.
        $secret = (string) apply_filters('linked3/billing_webhook_secret', '');
        if ($secret === '') {
            // No secret configured — reject all webhooks (safety default).
            return rest_ensure_response(['ok' => false, 'error' => 'webhook_secret_not_configured'], 503);
        }

        $verified = self::verify_webhook_signature($raw_body, $sig_header, $secret);
        if (!$verified) {
            return rest_ensure_response(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        // Parse the JSON payload.
        $payload = json_decode($raw_body, true);
        if (!is_array($payload)) {
            return rest_ensure_response(['ok' => false, 'error' => 'invalid_json'], 400);
        }

        // Log the event to the billing_events table.
        $repo = new \Linked3\Classes\Billing\Linked3_Billing_Event_Repository();
        $event_id = $repo->log_event([
            'event_type'  => $payload['event_type'] ?? '',
            'provider'    => $provider,
            'license_key' => $payload['license_key'] ?? '',
            'user_id'     => $payload['user_id'] ?? 0,
            'plan'        => $payload['plan'] ?? '',
            'amount'      => $payload['amount'] ?? 0,
            'currency'    => $payload['currency'] ?? 'USD',
            'status'      => 'success',
            'raw_payload' => $payload,
            'signature'   => $sig_header,
        ]);

        // Update the user's plan based on the webhook event.
        $event_type = $payload['event_type'] ?? '';
        $license_key = $payload['license_key'] ?? '';
        $plan = $payload['plan'] ?? '';

        if ($license_key && $plan) {
            $license_service = \Linked3\Classes\License\Linked3_License_Service::instance();

            if (in_array($event_type, ['subscription.created', 'payment.succeeded', 'subscription.renewed'], true)) {
                // Activate / upgrade the plan.
                $license_service->store_license_key($license_key);
                update_option(LINKED3_OPTION_PREFIX . 'last_known_plan', $plan);
                delete_transient(LINKED3_OPTION_PREFIX . 'license_status');
            } elseif ($event_type === 'subscription.cancelled' || $event_type === 'subscription.expired') {
                // Downgrade to free.
                $license_service->revoke();
            }
        }

        return rest_ensure_response(['ok' => true, 'event_id' => $event_id]);
    }

    /**
     * Verify the webhook HMAC-SHA256 signature.
     *
     * Signature format: t=<timestamp>,v1=<hmac>
     *
     * @param string $body      Raw request body.
     * @param string $sig_header The X-Linked3-Signature header.
     * @param string $secret    The shared secret.
     * @return bool True if the signature is valid and within the 5-min window.
     */
    private static function verify_webhook_signature(string $body, string $sig_header, string $secret): bool
    {
        if (empty($sig_header)) {
            return false;
        }

        // Parse the signature header: t=1234567890,v1=abc123...
        $parts = [];
        foreach (explode(',', $sig_header) as $kv) {
            $pair = explode('=', $kv, 2);
            if (count($pair) === 2) {
                $parts[trim($pair[0])] = trim($pair[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if (!$timestamp || !$signature) {
            return false;
        }

        // 5-minute replay window.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // Compute expected signature: HMAC-SHA256(secret, timestamp.body)
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        return hash_equals($expected, $signature);
    }
}
