<?php
/**
 * Speech (TTS) — text-to-speech via multiple providers.
 *
 * Providers: OpenAI TTS / Azure / 阿里通义 / 讯飞.
 * Shortcode: [linked3_tts text="Hello" voice="alloy"]
 *
 * @package Linked3
 * @subpackage Classes\Speech
 */

declare(strict_types=1);

namespace Linked3\Classes\Speech;

use Linked3\Includes\Http\Linked3_Safe_Remote;

if (!defined('ABSPATH')) {
    exit;
}

final class TtsManager
{
    /**
     * Synthesize speech.
     *
     * @param string $text   Text to synthesize.
     * @param string $voice  Voice profile (e.g. 'alloy', 'nova').
     * @param array  $config {provider, api_key, model}.
     * @return array{ok: bool, audio_url: string, message: string}
     */
    public function synthesize(string $text, string $voice = 'alloy', array $config = []): array
    {
        $provider = $config['provider'] ?? 'openai';
        switch ($provider) {
            case 'openai':
                return $this->openaiTts($text, $voice, $config);
            default:
                return [
                    'ok' => false,
                    'audio_url' => '',
                    'message' => sprintf(__('Provider %s 未实现。', 'linked3'), $provider),
                ];
        }
    }

    /**
     * Synthesize via OpenAI TTS API.
     *
     * @param string $text   Text to synthesize.
     * @param string $voice  Voice profile.
     * @param array  $config {api_key, model}.
     * @return array{ok: bool, audio_url: string, message: string}
     */
    private function openaiTts(string $text, string $voice, array $config): array
    {
        $key = $config['api_key'] ?? '';
        if (!$key) {
            return ['ok' => false, 'audio_url' => '', 'message' => __('缺少 API Key。', 'linked3')];
        }

        $url = 'https://api.openai.com/v1/audio/speech';
        $body = wp_json_encode([
            'model' => $config['model'] ?? 'tts-1',
            'voice' => $voice,
            'input' => mb_substr($text, 0, 4096),
        ]);

        $resp = Linked3_Safe_Remote::post($url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'allowed_hosts' => ['api.openai.com'],
        ]);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'audio_url' => '', 'message' => $resp->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $errorBody = wp_remote_retrieve_body($resp);
            return [
                'ok' => false,
                'audio_url' => '',
                'message' => sprintf(__('HTTP %d:%s', 'linked3'), $code, substr($errorBody, 0, 200)),
            ];
        }

        // Save audio to media library.
        $audio = wp_remote_retrieve_body($resp);
        $uploads = wp_upload_dir();
        $filename = 'linked3-tts-' . wp_generate_password(8, false) . '.mp3';
        $path = trailingslashit($uploads['path']) . $filename;
        file_put_contents($path, $audio);

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $attachmentId = wp_insert_attachment([
            'post_title' => 'Linked3 TTS ' . date('Y-m-d H:i'),
            'post_mime_type' => 'audio/mpeg',
            'post_content' => '',
            'post_status' => 'inherit',
        ], $path, 0);

        $audioUrl = wp_get_attachment_url($attachmentId);
        return ['ok' => true, 'audio_url' => $audioUrl, 'message' => 'ok'];
    }

    /**
     * Register the [linked3_tts] shortcode.
     */
    public static function registerShortcode(): void
    {
        add_shortcode('linked3_tts', [__CLASS__, 'renderShortcode']);
    }

    /**
     * Render the TTS shortcode.
     *
     * @param array       $atts    Shortcode attributes.
     * @param string|null $content Shortcode content.
     * @return string HTML output.
     */
    public static function renderShortcode(array $atts, ?string $content = ''): string
    {
        $atts = shortcode_atts(['voice' => 'alloy', 'text' => ''], $atts, 'linked3_tts');
        $text = $atts['text'] ?: $content;
        if (!$text) {
            return '';
        }

        $nonce = wp_create_nonce('linked3_tts');
        $ajaxUrl = admin_url('admin-ajax.php');
        $id = 'linked3-tts-' . wp_generate_password(6, false);

        return sprintf(
            '<span class="linked3-tts" id="%s" data-nonce="%s" data-ajax="%s" data-voice="%s" data-text="%s" style="cursor:pointer;" title="%s">'
            . '<span class="dashicons dashicons-controls-volumeon"></span></span>',
            esc_attr($id),
            esc_attr($nonce),
            esc_attr($ajaxUrl),
            esc_attr($atts['voice']),
            esc_attr($text),
            esc_attr__('朗读', 'linked3')
        );
    }
}
