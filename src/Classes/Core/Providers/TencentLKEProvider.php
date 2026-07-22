<?php

declare(strict_types=1);
/**
 * 腾讯 LKE 智能体 Provider — 调用腾讯 LKE (Link Knowledge Engine) 自定义 bot API。
 *
 * 原版 v2.9.6 支持 custom Tencent LKE bot API (SSE 流式)。本 provider 迁移该能力。
 * 用户在腾讯 LKE 平台创建智能体,获取 BotAppKey,在此配置。
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

final class TencentLKEProvider extends BaseProviderStrategy
{
    public function slug() : string { return 'tencent_lke'; }

    protected function default_api_base()
    : string {
        return 'https://lke.cloud.tencent.com/v1';
    }

    public function build_api_url(string $operation, array $config) : string {
        $base = $this->api_base($config);
        // LKE 用 /bot/chat/completions, BotAppKey 在 header 里。
        return $base . '/bot/chat/completions';
    }

    public function get_api_headers(array $config)
    : array {
        return [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Bot-App-Key'  => $config['api_key'] ?? '',
        ];
    }

    public function format_chat_payload(array $messages, array $options, array $config)
    : array {
        // LKE 用 content 字段,history 通过 messages 传递。
        $last_user = '';
        $history = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'user') {
                if ($last_user !== '') {
                    $history[] = ['role' => 'user', 'content' => $last_user];
                }
                $last_user = $m['content'];
            } elseif ($m['role'] === 'assistant' && $last_user !== '') {
                $history[] = ['role' => 'assistant', 'content' => $m['content']];
                $last_user = '';
            }
        }
        return [
            'content'  => $last_user,
            'history'  => $history,
            'stream'   => false,
        ];
    }

    public function parse_chat_response($body, array $config)
    : array {
        $body = is_array($body) ? $body : [];
        $content = '';
        if (isset($body['choices'][0]['message']['content'])) {
            $content = (string) $body['choices'][0]['message']['content'];
        } elseif (isset($body['content'])) {
            $content = (string) $body['content'];
        }
        return [
            'content' => $content,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'raw' => $body,
        ];
    }

    public function parse_error_response($body, int $status_code)
    {
        $body = is_array($body) ? $body : [];
        return [
            'code' => (string) ($body['code'] ?? 'lke_error'),
            'message' => (string) ($body['message'] ?? $body['msg'] ?? 'Unknown'),
            'status' => $status_code,
        ];
    }

    public function get_models(array $config)
    : array {
        return ['lke-bot'];
    }
}
