<?php
/**
 * Collect Source Repository — CRUD on linked3_collect_sources.
 *
 * v4.5.4: now extends Linked3_Base_Repository. The custom `all_for_user` /
 * `get` / `mark_fetched` methods are kept (they have user-scoping logic that
 * Base_Repository does not). The `create` / `update` / `delete` methods
 * delegate to the base class for $wpdb->insert/update/delete with prepare.
 *
 * @package Linked3
 * @subpackage Classes\Collect
 */

namespace Linked3\Classes\Collect;

use Linked3\Includes\DB\Linked3_Base_Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Collect_Source_Repository extends Linked3_Base_Repository
{
    /**
     * {@inheritdoc}
     */
    protected function table_name(): string
    {
        return 'linked3_collect_sources';
    }

    /**
     * {@inheritdoc}
     */
    protected function primary_key(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    protected function fillable(): array
    {
        return ['user_id', 'type', 'name', 'config', 'schedule',
                'keywords_include', 'keywords_exclude', 'status', 'last_fetched'];
    }

    public function all_for_user($user_id) : mixed {
        global $wpdb;
        $table = $this->get_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY id DESC",
            $user_id
        ), ARRAY_A);
        if (!$rows) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['config'] = json_decode($r['config'], true) ?: [];
        }
        return $rows;
    }

    public function get($id, $user_id) : mixed     {
        global $wpdb;
        $table = $this->get_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
            $id, $user_id
        ), ARRAY_A);
        if (!$row) {
            return null;
        }
        $row['config'] = json_decode($row['config'], true) ?: [];
        return $row;
    }

    public function create(array $data) : mixed {
        $clean = [
            'user_id'           => (int) ($data['user_id'] ?? get_current_user_id()),
            'type'              => sanitize_text_field($data['type'] ?? 'rss'),
            'name'              => sanitize_text_field($data['name'] ?? ''),
            'config'            => wp_json_encode($this->sanitize_config($data['config'] ?? [])),
            'schedule'          => sanitize_text_field($data['schedule'] ?? 'daily'),
            'keywords_include'  => sanitize_text_field($data['keywords_include'] ?? ''),
            'keywords_exclude'  => sanitize_text_field($data['keywords_exclude'] ?? ''),
            'status'            => 'active',
        ];
        $id = parent::insert_row($clean);
        if ($id === null) {
            global $wpdb;
            return new \WP_Error('db', $wpdb->last_error);
        }
        return $id;
    }

    public function update($id, $user_id, array $data)
    : bool {
        $clean = [];
        if (isset($data['name'])) {
            $clean['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['config'])) {
            $clean['config'] = wp_json_encode($this->sanitize_config($data['config']));
        }
        if (isset($data['schedule'])) {
            $clean['schedule'] = sanitize_text_field($data['schedule']);
        }
        if (isset($data['keywords_include'])) {
            $clean['keywords_include'] = sanitize_text_field($data['keywords_include']);
        }
        if (isset($data['keywords_exclude'])) {
            $clean['keywords_exclude'] = sanitize_text_field($data['keywords_exclude']);
        }
        if (empty($clean)) {
            return true;
        }
        // User-scoped update: we can't use Base_Repository::update() because
        // it only filters by primary key. Use $wpdb directly with prepare.
        global $wpdb;
        $table = $this->get_table();
        $fmt = array_fill(0, count($clean), '%s');
        $wpdb->update($table, $clean, ['id' => $id, 'user_id' => $user_id], $fmt, ['%d', '%d']);
        return true;
    }

    public function delete($id, $user_id) : mixed     {
        // Soft delete: set status='deleted' rather than removing the row.
        global $wpdb;
        $table = $this->get_table();
        return (bool) $wpdb->update(
            $table,
            ['status' => 'deleted'],
            ['id' => $id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );
    }

    public function mark_fetched($id)
    {
        return parent::update_by_pk($id, ['last_fetched' => current_time('mysql')]);
    }

    private function sanitize_config(array $cfg)
    {
        $out = [];
        if (isset($cfg['feed_url'])) {
            $out['feed_url'] = esc_url_raw($cfg['feed_url']);
        }
        if (isset($cfg['urls'])) {
            $out['urls'] = array_values(array_filter(array_map('esc_url_raw', (array) $cfg['urls'])));
        }
        if (isset($cfg['file_path'])) {
            $out['file_path'] = sanitize_text_field($cfg['file_path']);
        }
        return $out;
    }
}
