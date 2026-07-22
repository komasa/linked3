<?php

declare(strict_types=1);
/**
 * Image injector — fetches and embeds images into generated content.
 *
 * Providers: Pexels / Pixabay / Unsplash / AI-generated (DALL-E / SDXL via AI Dispatcher).
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;

use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class ImageInjector
{
    /**
     * Insert N images into the article at natural breakpoints (after H2).
     *
     * @param string $content  Markdown/HTML body.
     * @param string $keyword  Search keyword.
     * @param int    $count    Number of images to insert.
     * @param array  $config   {provider, api_key}
     * @return string Modified content.
     */
    public function inject(string $content, string $keyword, int $count = 2, array $config = []) : mixed {
        $provider = $config['provider'] ?? 'pexels';
        $images = $this->fetch_images($provider, $keyword, $count, $config);
        if (empty($images)) {
            return $content;
        }

        // Split content at H2 headings; insert one image before each H2.
        $parts = preg_split('/(?=^##\s)/m', $content);
        $result = '';
        $idx = 0;
        foreach ($parts as $i => $part) {
            if ($i > 0 && $idx < count($images)) {
                $img = $images[$idx];
                $result .= $this->render_image($img) . "\n\n";
                $idx++;
            }
            $result .= $part;
        }
        // Append remaining images at the end.
        while ($idx < count($images)) {
            $result .= "\n\n" . $this->render_image($images[$idx]);
            $idx++;
        }
        return $result;
    }

    /**
     * @param string $provider
     * @param string $keyword
     * @param int    $count
     * @param array  $config
     * @return array<int,array{url:string, alt:string, credit:string}>
     */
    private function fetch_images(string $provider, string $keyword, int $count, array $config) : mixed     {
        switch ($provider) {
            case 'pexels':
                return $this->fetch_pexels($keyword, $count, $config);
            case 'pixabay':
                return $this->fetch_pixabay($keyword, $count, $config);
            case 'unsplash':
                return $this->fetch_unsplash($keyword, $count, $config);
            case 'ai':
                // Defer to AI Dispatcher (DALL-E/SDXL) in v0.8.2.
                return [];
        }
        return [];
    }

    private function fetch_pexels(string $keyword, int $count, array $config) : mixed {
        $key = $config['api_key'] ?? '';
        if (!$key) return [];
        $url = 'https://api.pexels.com/v1/search?query=' . urlencode($keyword) . '&per_page=' . (int) $count;
        $resp = SafeRemote::get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => $key],
            'allowed_hosts' => ['api.pexels.com'],
        ]);
        if (is_wp_error($resp)) return [];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $out = [];
        if (!empty($body['photos'])) {
            foreach (array_slice($body['photos'], 0, $count) as $p) {
                $out[] = [
                    'url' => $p['src']['large'] ?? '',
                    'alt' => $p['alt'] ?: $keyword,
                    'credit' => sprintf('Photo by %s on Pexels', $p['photographer'] ?? 'Unknown'),
                ];
            }
        }
        return $out;
    }

    private function fetch_pixabay($keyword, $count, $config) : mixed     {
        $key = $config['api_key'] ?? '';
        if (!$key) return [];
        $url = 'https://pixabay.com/api/?key=' . $key . '&q=' . urlencode($keyword) . '&per_page=' . (int) $count . '&image_type=photo';
        $resp = SafeRemote::get($url, ['timeout' => 15, 'allowed_hosts' => ['pixabay.com']]);
        if (is_wp_error($resp)) return [];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $out = [];
        if (!empty($body['hits'])) {
            foreach (array_slice($body['hits'], 0, $count) as $h) {
                $out[] = ['url' => $h['largeImageURL'] ?? '', 'alt' => $keyword, 'credit' => 'Pixabay'];
            }
        }
        return $out;
    }

    private function fetch_unsplash($keyword, $count, $config)
    {
        $key = $config['api_key'] ?? '';
        if (!$key) return [];
        $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($keyword) . '&per_page=' . (int) $count;
        $resp = SafeRemote::get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Client-ID ' . $key],
            'allowed_hosts' => ['api.unsplash.com'],
        ]);
        if (is_wp_error($resp)) return [];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $out = [];
        if (!empty($body['results'])) {
            foreach (array_slice($body['results'], 0, $count) as $r) {
                $out[] = [
                    'url' => $r['urls']['regular'] ?? '',
                    'alt' => $r['alt_description'] ?: $keyword,
                    'credit' => sprintf('Photo by %s on Unsplash', $r['user']['name'] ?? 'Unknown'),
                ];
            }
        }
        return $out;
    }

    /** Image-host whitelist for sideload (defence-in-depth against a
     *  compromised provider response sneaking in an internal URL).
     *  Download_url() itself uses wp_remote_get without Safe_Remote's
     *  SSRF guard, so we validate the host BEFORE handing it off. */
    private static $sideload_hosts = [
        'images.pexels.com',
        'api.pexels.com',
        'pixabay.com',
        'cdn.pixabay.com',
        'images.unsplash.com',
        'api.unsplash.com',
        'images.unsplash.com',
    ];

    /**
     * Download a remote image into the WP media library (sideload).
     *
     * @param string $url
     * @param string $alt
     * @return int|\WP_Error Attachment ID.
     */
    public function sideload(string $url, string $alt = ''): int|WP_Error
    {
        $url = esc_url_raw($url);
        if (empty($url)) {
            return new \WP_Error('linked3_bad_image_url', __('无效的图片 URL。', 'linked3'));
        }

        // v0.4.0: host allow-list — block any URL that didn't come from a
        // known image provider. This closes the gap where download_url()
        // bypasses Safe_Remote's SSRF guard.
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $allowed = (array) apply_filters('linked3/sideload_allowed_hosts', self::$sideload_hosts);
        $ok = false;
        foreach ($allowed as $h) {
            $h = strtolower($h);
            if ($host === $h || substr($host, -strlen('.' . $h)) === '.' . $h) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return new \WP_Error(
                'linked3_image_host_blocked',
                sprintf(/* translators: %s: host name. */ __('图片主机 %s 不在 sideload 白名单中。', 'linked3'), $host)
            );
        }

        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return $tmp;
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)) ?: 'linked3-image.jpg',
            'tmp_name' => $tmp,
        ];
        $id = media_handle_sideload($file_array, 0, $alt);
        if (is_wp_error($id)) {
            @unlink($tmp); // phpcs:ignore
        } else {
            update_post_meta($id, '_wp_attachment_image_alt', $alt);
        }
        return $id;
    }

    /**
     * @param array $img
     * @return string
     */
    private function render_image(array $img): string {
        $url = esc_url($img['url'] ?? '');
        $alt = esc_attr($img['alt'] ?? '');
        $credit = esc_html($img['credit'] ?? '');
        if (!$url) return '';
        return "![{$alt}]({$url})\n\n*{$credit}*";
    }
}
