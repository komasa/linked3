<?php
/**
 * Push Retry Action — re-push failed URLs.
 *
 * Plan gating: Free users can retry 100/day (per-engine quota enforced
 * via require_push_quota); Pro/Premium unlimited. Only posts/pages the
 * current user can edit are eligible.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Ajax\Actions
 */

namespace Linked3\Classes\SEO\Ajax\Actions;

use Linked3\Classes\SEO\Ajax\Linked3_SEO_Base_Ajax_Action;
use Linked3\Classes\SEO\Push\Linked3_Push_Manager;
use Linked3\Classes\SEO\Push\Linked3_Push_Log_Repository;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Push_Retry_Action extends Linked3_SEO_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_seo';
    const CAPABILITY = 'edit_posts';

    public function handle()
    : void {
        $log_ids = isset($_POST['log_ids']) ? (array) wp_unslash($_POST['log_ids']) : [];
        $log_ids = array_map('absint', $log_ids);
        $log_ids = array_filter($log_ids);
        if (empty($log_ids)) {
            $this->send_error(__('未选择要重试的日志。', 'linked3'), 400);
        }

        // Pull the failed rows from the DB (only status=fail).
        global $wpdb;
        $table = Linked3_Push_Log_Repository::table();
        $in = implode(',', array_fill(0, count($log_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, engine, url FROM {$table} WHERE id IN ($in) AND status = 'fail'",
            $log_ids
        ), ARRAY_A);

        if (empty($rows)) {
            $this->send_error(__('未找到可重试的失败日志。', 'linked3'), 404);
        }

        // Group by engine so we can call push_batch once per engine (and
        // only consume one quota slot per engine per call).
        $by_engine = [];
        foreach ($rows as $r) {
            $by_engine[$r['engine']][] = $r;
        }

        $manager = Linked3_Push_Manager::instance();
        $results = [];
        foreach ($by_engine as $engine => $group) {
            // Quota gate (one slot per engine per retry batch).
            $this->require_push_quota($engine);
            $urls = array_unique(array_column($group, 'url'));
            $engine_result = $manager->push_batch($urls, $engine);
            $results[$engine] = $engine_result[$engine] ?? ['ok' => false, 'message' => __('未知引擎。', 'linked3')];
            // Mark each retried log row.
            foreach ($group as $r) {
                Linked3_Push_Log_Repository::update((int) $r['id'], [
                    'status'  => $engine_result[$engine]['ok'] ?? false ? 'success' : 'fail',
                    'message' => (string) ($engine_result[$engine]['message'] ?? ''),
                ]);
            }
        }
        $this->send_success([
            'results' => $results,
            'count'   => count($rows),
        ]);
    }
}
