<?php

declare(strict_types=1);
/**
 * Push log repository — CRUD on linked3_push_logs.
 *
 * v4.5.4: now extends Linked3_Base_Repository for standard CRUD.
 * Backward-compatible static wrappers are preserved so existing call
 * sites (e.g. `PushLogRepository::insert(...)`) keep working.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Push
 */

namespace Linked3\Classes\SEO\Push;

use Linked3\Includes\DB\Linked3_Base_Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class PushLogRepository extends Linked3_Base_Repository
{
    /** @var self|null Singleton for backward-compat static methods. */
    private static $instance;

    /**
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    protected function table_name(): string
    {
        return 'linked3_push_logs';
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
        return ['engine', 'url', 'status', 'response_code', 'response_body', 'message', 'retries'];
    }

    // -------------------------------------------------------------------------
    // Backward-compatible static wrappers.
    // Each delegates to the singleton instance so existing call sites keep
    // working without modification.
    // -------------------------------------------------------------------------

    /** @return string */
    public static function table() : mixed     {
        global $wpdb;
        return $wpdb->prefix . 'linked3_push_logs';
    }

    /**
     * Insert a log row (sanitizes input before delegating to Base_Repository).
     *
     * @param array $data
     * @return int Inserted row ID (0 on failure).
     */
    public static function insert(array $data) : mixed {
        $clean = [
            'engine'        => sanitize_key((string) ($data['engine'] ?? '')),
            'url'           => esc_url_raw((string) ($data['url'] ?? '')),
            'status'        => sanitize_key((string) ($data['status'] ?? 'pending')),
            'response_code' => (int) ($data['response_code'] ?? 0),
            'response_body' => substr((string) ($data['response_body'] ?? ''), 0, 4096),
            'message'       => sanitize_text_field((string) ($data['message'] ?? '')),
        ];
        $id = self::instance()->insert_row($clean);
        return $id ?? 0;
    }

    /**
     * Query logs with optional filters.
     *
     * @param array $filters
     * @return array
     */
    public static function query(array $filters = []) : mixed     {
        global $wpdb;
        $table = self::table();
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['engine'])) {
            $where[] = 'engine = %s';
            $params[] = sanitize_key((string) $filters['engine']);
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_key((string) $filters['status']);
        }
        if (!empty($filters['url'])) {
            $where[] = 'url = %s';
            $params[] = esc_url_raw((string) $filters['url']);
        }
        $limit = (int) ($filters['limit'] ?? 50);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * @param string $url
     * @param string $engine
     * @param int    $since_seconds
     * @return int
     */
    public static function count_recent_success($url, $engine, $since_seconds) : mixed {
        global $wpdb;
        $table = self::table();
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - (int) $since_seconds);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE url = %s AND engine = %s AND status = 'success'
               AND created_at >= %s",
            esc_url_raw($url),
            sanitize_key($engine),
            $cutoff
        ));
    }

    /**
     * Mark a row as retry-pending (or update its status / response).
     *
     * @param int    $id
     * @param array  $fields
     * @return bool
     */
    public static function update($id, array $fields) : mixed     {
        $allowed = ['status', 'response_code', 'response_body', 'message', 'retries'];
        $clean = [];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $clean[$k] = $v;
            }
        }
        if (empty($clean)) {
            return false;
        }
        return self::instance()->update_by_pk((int) $id, $clean);
    }

    /**
     * @return int Total row count.
     */
    public static function count_all()
    {
        return self::instance()->count();
    }

    /**
     * Prune logs older than N days. Called by cron.
     *
     * @param int $days
     * @return int Rows deleted.
     */
    public static function prune_older_than($days)
    {
        global $wpdb;
        $table = self::table();
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS);
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff
        ));
    }
}
