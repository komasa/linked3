<?php
/**
 * STT (ASR) — speech-to-text via multiple providers.
 *
 * Providers: OpenAI Whisper / 阿里通义 / 讯飞.
 *
 * @package Linked3
 * @subpackage Classes\STT
 */

declare(strict_types=1);

namespace Linked3\Classes\STT;

use Linked3\Includes\Http\SafeRemote;

if (!defined('ABSPATH')) {
    exit;
}

final class SttManager
{
    /**
     * Transcribe an audio file.
     *
     * @param string $file_path Local path to audio file (mp3/wav/m4a).
     * @param array  $config    {provider, api_key, model}.
     * @return array{ok: bool, text: string, message: string}
     */
    public function transcribe(string $file_path, array $config = []): array
    {
        if (!file_exists($file_path)) {
            return ['ok' => false, 'text' => '', 'message' => __('音频文件未找到。', 'linked3')];
        }
        $provider = $config['provider'] ?? 'openai';
        switch ($provider) {
            case 'openai':
                return $this->openaiWhisper($file_path, $config);
            default:
                return ['ok' => false, 'text' => '', 'message' => sprintf(__('Provider %s 未实现。', 'linked3'), $provider)];
        }
    }

    /**
     * Transcribe via OpenAI Whisper API.
     *
     * @param string $file_path Local path to audio file.
     * @param array  $config    {api_key, model}.
     * @return array{ok: bool, text: string, message: string}
     */
    private function openaiWhisper(string $file_path, array $config): array
    {
        $key = $config['api_key'] ?? '';
        if (!$key) {
            return ['ok' => false, 'text' => '', 'message' => __('缺少 API Key。', 'linked3')];
        }
        $url = 'https://api.openai.com/v1/audio/transcriptions';
        $boundary = wp_generate_password(24, false);
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            return ['ok' => false, 'text' => '', 'message' => __('无法读取音频文件。', 'linked3')];
        }
        $filename = basename($file_path);
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n" . ($config['model'] ?? 'whisper-1') . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: " . $this->mimeType($file_path) . "\r\n\r\n" . $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $resp = SafeRemote::post($url, [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
            'allowed_hosts' => ['api.openai.com'],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'text' => '', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200) {
            return ['ok' => false, 'text' => '', 'message' => sprintf(__('HTTP %d', 'linked3'), $code)];
        }
        return ['ok' => true, 'text' => (string) ($json['text'] ?? ''), 'message' => 'ok'];
    }

    /**
     * Get MIME type for audio file extension.
     *
     * @param string $path File path.
     * @return string
     */
    private function mimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
