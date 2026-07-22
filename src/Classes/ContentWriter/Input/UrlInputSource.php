<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Input;
use Linked3\Includes\Http\SafeRemote;


if (!defined('ABSPATH')) exit;
/**
 * Url input source.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Input
 * @since      27.1.0
 */

final class UrlInputSource implements InputSourceInterface
{
    public function slug() : string { return 'url'; }
    public function label() : string { return __('URL 采集', 'linked3'); }

    public function fetch(array $config, int $limit = 10) : array {
        $urls = $config['urls'] ?? [];
        if (!is_array($urls) || empty($urls)) return [];
        $items = [];
        $count = 0;
        foreach ($urls as $url) {
            if ($count >= $limit) break;
            $url = esc_url_raw($url);
            if (empty($url)) continue;
            $resp = SafeRemote::get($url, [
                'timeout' => 20,
                'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
            ]);
            if (is_wp_error($resp)) continue;
            $html = wp_remote_retrieve_body($resp);
            // Extract <title> + body text.
            $title = '';
            if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
                $title = trim(wp_strip_all_tags($m[1]));
            }
            // Crude body extraction: strip scripts/styles, then tags.
            $body = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
            $body = preg_replace('#<style[^>]*>.*?</style>#is', '', $body);
            $body = wp_strip_all_tags($body);
            $body = trim(preg_replace('/\s+/', ' ', $body));
            $items[] = [
                'title'   => $title,
                'content' => mb_substr($body, 0, 5000),
                'url'     => $url,
                'guid'    => md5($url),
            ];
            $count++;
        }
        return $items;
    }
}
