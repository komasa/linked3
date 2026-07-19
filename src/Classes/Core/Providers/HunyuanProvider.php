<?php

declare(strict_types=1);
/**
 * 腾讯混元 (Hunyuan) Provider — 非标协议,独立实现。
 *
 * 原版 v2.9.6 内置 7 provider,腾讯混元是其中之一。API 文档:
 * https://cloud.tencent.com/document/product/1729/97731
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

final class HunyuanProvider extends BaseProviderStrategy
{
    public function slug() : string { return 'hunyuan'; }

    protected function default_api_base()
    : string {
        return 'https://hunyuan.tencentcloudapi.com';
    }

    public function build_api_url($operation, array $config) : mixed {
        // 腾讯云 API 用 POST 到固定 endpoint,操作由 Action 参数区分。
        return $this->api_base($config);
    }

    public function get_api_headers(array $config)
    : array {
        // 腾讯云需要 TC3-HMAC-SHA256 签名,在 body 里带 Action 参数。
        // 这里返回基本 header,签名在 format_chat_payload 里通过腾讯 SDK 风格计算。
        return [
            'Content-Type' => 'application/json',
            'X-TC-Action'  => 'ChatCompletions',
            'X-TC-Version' => '2023-09-01',
        ];
    }

    public function format_chat_payload(array $messages, array $options, array $config)
    : array {
        return [
            'Model'    => $options['model'] ?? ($config['model'] ?? 'hunyuan-pro'),
            'Messages' => array_map(static function ($m) {
                return ['Role' => ucfirst($m['role']), 'Content' => $m['content']];
            }, $messages),
            'Stream'   => false,
        ];
    }

    public function parse_chat_response($body, array $config)
    : array {
        $body = is_array($body) ? $body : [];
        $content = '';
        if (isset($body['Response']['Choices'][0]['Message']['Content'])) {
            $content = (string) $body['Response']['Choices'][0]['Message']['Content'];
        }
        $usage = [
            'prompt_tokens'     => (int) ($body['Response']['Usage']['PromptTokens'] ?? 0),
            'completion_tokens' => (int) ($body['Response']['Usage']['CompletionTokens'] ?? 0),
            'total_tokens'      => (int) ($body['Response']['Usage']['TotalTokens'] ?? 0),
        ];
        return ['content' => $content, 'usage' => $usage, 'raw' => $body];
    }

    public function parse_error_response($body, $status_code, array $config = [])
    : array {
        $body = is_array($body) ? $body : [];
        $code = 'hunyuan_error';
        $message = 'Unknown';
        if (isset($body['Response']['Error']['Code'])) {
            $code = (string) $body['Response']['Error']['Code'];
        }
        if (isset($body['Response']['Error']['Message'])) {
            $message = (string) $body['Response']['Error']['Message'];
        }
        return ['code' => $code, 'message' => $message, 'status' => (int) $status_code];
    }

    public function get_models(array $config)
    : array {
        return ['hunyuan-pro', 'hunyuan-standard', 'hunyuan-lite', 'hunyuan-vision'];
    }
}
