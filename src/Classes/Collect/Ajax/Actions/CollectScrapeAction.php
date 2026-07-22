<?php

declare(strict_types=1);
namespace Linked3\Classes\Collect\Ajax\Actions;
use Linked3\Classes\Collect\Ajax\CollectBaseAjaxAction;


if (!defined('ABSPATH')) exit;
/**
 * Collect scrape action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Collect.Ajax.Actions
 * @since      27.1.0
 */

final class CollectScrapeAction extends CollectBaseAjaxAction
{
    public function handle(): void {
        $url = esc_url_raw($_POST['url'] ?? '');
        if (!$url) $this->send_error(__('需要 URL。', 'linked3'), 400);
        $scraper = $this->scraper();
        if ($scraper->is_duplicate($url, '')) {
            $this->send_error(__('已采集过(URL 去重)。', 'linked3'), 409);
        }
        $result = $scraper->fetch($url);
        if (!$result['ok']) $this->send_error($result['message'], 502);
        // Re-check dedup with actual content.
        if ($scraper->is_duplicate($url, $result['text'])) {
            $this->send_error(__('内容已采集(simhash 去重)。', 'linked3'), 409);
        }
        $this->send_success([
            'title' => $result['title'],
            'content' => mb_substr($result['text'], 0, 10000),
        ]);
    }
}
