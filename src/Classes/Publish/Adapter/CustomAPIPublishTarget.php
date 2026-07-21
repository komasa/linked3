<?php

declare(strict_types=1);
/**
 * Custom API publish target — POSTs to an arbitrary webhook endpoint.
 *
 * Hardening over v2.9.6's publish_to_custom_api:
 *   - HMAC-SHA256 signature header (X-Linked3-Signature)
 *   - Timestamp + nonce anti-replay (X-Linked3-Timestamp / X-Linked3-Nonce)
 *   - Retry with exponential backoff (configurable via Publish_Config)
 *   - Domain whitelist via Safe_Remote allowed_hosts
 *
 * @package Linked3
 * @subpackage Classes\Publish\Adapter
 */

namespace Linked3\Classes\Publish\Adapter;

use Linked3\Classes\Publish\PublishTargetInterface;
use Linked3\Classes\Publish\PublishConfig;
use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class CustomAPIPublishTarget implements PublishTargetInterface
{
    public function type() : string { return 'custom_api'; }
    public function label() : string { return __('自定义 API / Webhook', 'linked3'); }

    public function publish(array $post, array $config)
    : array {
        $url = $config['webhook_url'] ?? '';
        $secret = $config['webhook_secret'] ?? '';
        if (!$url) {
            return ['ok' => false, 'remote_id' => '', 'message' => __('缺少 Webhook URL。', 'linked3'), 'response_code' => 400];
        }

        $payload = wp_json_encode([
            'action' => 'publish',
            'post'   => [
                'title'   => $post['post_title'] ?? '',
                'content' => $post['post_content'] ?? '',
                'excerpt' => $post['post_excerpt'] ?? '',
                'status'  => $post['post_status'] ?? 'publish',
            ],
            'site'   => site_url(),
            'ts'     => time(),
        ]);

        $headers = ['Content-Type' => 'application/json'];
        if ($secret) {
            $ts = time();
            $nonce = wp_generate_password(16, false);
            $sig = hash_hmac('sha256', $payload . $ts . $nonce, $secret);
            $headers['X-Linked3-Signature'] = $sig;
            $headers['X-Linked3-Timestamp'] = (string) $ts;
            $headers['X-Linked3-Nonce'] = $nonce;
        }

        $max_attempts = (int) PublishConfig::get('retry.max_attempts', 3);
        $backoff = (int) PublishConfig::get('retry.backoff_base', 60);
        $log = Logger::instance();

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $resp = SafeRemote::post($url, [
                'timeout' => 30,
                'headers' => $headers,
                'body'    => $payload,
                'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
            ]);
            if (is_wp_error($resp)) {
                $log->warning('publish', "Custom API attempt {$attempt} transport error: " . $resp->get_error_message());
                if ($attempt < $max_attempts) {
                    sleep(min($backoff * $attempt, 300));
                    continue;
                }
                return ['ok' => false, 'remote_id' => '', 'message' => $resp->get_error_message(), 'response_code' => 0];
            }
            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code >= 200 && $code < 300) {
                $json = json_decode(wp_remote_retrieve_body($resp), true);
                $remote_id = (string) ($json['remote_id'] ?? $json['id'] ?? '');
                return ['ok' => true, 'remote_id' => $remote_id, 'message' => 'ok', 'response_code' => $code];
            }
            // 4xx = permanent, don't retry.
            if ($code >= 400 && $code < 500) {
                $body = wp_remote_retrieve_body($resp);
                return ['ok' => false, 'remote_id' => '', 'message' => sprintf(__('HTTP %d:%s', 'linked3'), $code, substr($body, 0, 200)), 'response_code' => $code];
            }
            // 5xx = retryable.
            if ($attempt < $max_attempts) {
                $log->warning('publish', "Custom API attempt {$attempt} got HTTP {$code}, retrying");
                sleep(min($backoff * $attempt, 300));
                continue;
            }
            return ['ok' => false, 'remote_id' => '', 'message' => sprintf(__('%d 次尝试后仍返回 HTTP %d。', 'linked3'), $code, $attempt), 'response_code' => $code];
        }
        return ['ok' => false, 'remote_id' => '', 'message' => __('重试次数已用完。', 'linked3'), 'response_code' => 0];
    }

    public function test(array $config) : mixed {
        $url = $config['webhook_url'] ?? '';
        if (!$url) {
            return ['ok' => false, 'message' => __('缺少 Webhook URL。', 'linked3')];
        }
        // v0.6.0 hardening: sign the ping request too so the receiver can
        // verify authenticity with the same HMAC contract used for publish.
        // Without this, the test() call would be the only unsigned request
        // and a receiver enforcing signatures on all events would reject it.
        $secret = $config['webhook_secret'] ?? '';
        $payload = wp_json_encode(['action' => 'ping', 'site' => site_url(), 'ts' => time()]);
        $headers = ['Content-Type' => 'application/json'];
        if ($secret) {
            $ts = time();
            $nonce = wp_generate_password(16, false);
            $sig = hash_hmac('sha256', $payload . $ts . $nonce, $secret);
            $headers['X-Linked3-Signature'] = $sig;
            $headers['X-Linked3-Timestamp'] = (string) $ts;
            $headers['X-Linked3-Nonce'] = $nonce;
        }
        // Send a ping event — receiver should respond 200 with {"ok":true}.
        $resp = SafeRemote::post($url, [
            'timeout' => 15,
            'headers' => $headers,
            'body' => $payload,
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code >= 200 && $code < 300
            ? ['ok' => true, 'message' => sprintf(__('Webhook 可访问(HTTP %d)。', 'linked3'), $code)]
            : ['ok' => false, 'message' => sprintf(__('Webhook 返回 HTTP %d。', 'linked3'), $code)];
    }
}
