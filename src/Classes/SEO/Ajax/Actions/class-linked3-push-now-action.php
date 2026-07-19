<?php
/**
 * Push Now Action — manually push a URL (or current post permalink) to
 * one or all configured engines.
 *
 * Used by:
 *   - Admin "Push now" button on the SEO Dashboard
 *   - Admin "Push" link in the Push Logs table
 *
 * Plan gating: Free 100/day per engine, Pro unlimited (require_push_quota).
 *
 * @package Linked3
 * @subpackage Classes\SEO\Ajax\Actions
 */

namespace Linked3\Classes\SEO\Ajax\Actions;

use Linked3\Classes\SEO\Ajax\Linked3_SEO_Base_Ajax_Action;
use Linked3\Classes\SEO\Push\Linked3_Push_Manager;
use Linked3\Classes\SEO\Push\Linked3_Push_Engine_Factory;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Push_Now_Action extends Linked3_SEO_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_seo';
    const CAPABILITY = 'edit_posts';

    public function handle()
    : void {
        $url = esc_url_raw(sanitize_text_field($_POST['url'] ?? ''));
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $engine = sanitize_key((string) ($_POST['engine'] ?? ''));

        if ($url === '' && $post_id > 0) {
            $url = esc_url_raw((string) get_permalink($post_id));
        }
        if ($url === '') {
            $this->send_error(__('需要有效的 URL 或文章 ID。', 'linked3'), 400);
        }

        // Resolve target engines.
        if ($engine !== '') {
            if (!Linked3_Push_Engine_Factory::get($engine)) {
                $this->send_error(sprintf(__('未知引擎:%s', 'linked3'), $engine), 400);
            }
            $this->require_push_quota($engine);
        } else {
            // All configured engines — gate each before pushing.
            foreach (Linked3_Push_Engine_Factory::configured_slugs() as $slug) {
                $this->require_push_quota($slug);
            }
        }

        $manager = Linked3_Push_Manager::instance();
        $results = $manager->push_url($url, $engine ?: null);
        $this->send_success([
            'url'     => $url,
            'results' => $results,
        ]);
    }
}
