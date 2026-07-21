<?php

declare(strict_types=1);
namespace Linked3\Classes\ContentWriter\Input;
use Linked3\Includes\Http\SafeRemote;


if (!defined('ABSPATH')) exit;
/**
 * Rss input source.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.ContentWriter.Input
 * @since      27.1.0
 */

final class RssInputSource implements InputSourceInterface
{
    public function slug() : string { return 'rss'; }
    public function label() : mixed { return __('RSS 订阅源', 'linked3'); }

    public function fetch(array $config, $limit = 10) : mixed {
        $feed_url = $config['feed_url'] ?? '';
        if (empty($feed_url)) {
            return [];
        }
        $response = SafeRemote::get($feed_url, [
            'timeout' => 20,
            'allowed_hosts' => [wp_parse_url($feed_url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        if (!class_exists('SimplePie')) {
            require_once ABSPATH . WPINC . '/class-simplepie.php';
        }
        $simple = new \SimplePie();
        $simple->set_raw_data($body);
        $simple->enable_cache(false);
        $simple->init();
        $items = [];
        $count = 0;
        foreach ($simple->get_items() as $item) {
            if ($count >= $limit) break;
            $items[] = [
                'title'   => $item->get_title(),
                'content' => $item->get_content(),
                'url'     => $item->get_permalink(),
                'guid'    => $item->get_id(),
            ];
            $count++;
        }
        return $items;
    }
}
