<?php

declare(strict_types=1);
/**
 * Migration runner — versioned, idempotent, self-healing.
 *
 * Design (mirrors aipower `check_for_updates`):
 *   - On admin_init, compare stored DB_VERSION_OPTION vs code LINKED3_DB_VERSION.
 *   - If older (or missing), run pending migrations + create_all().
 *   - Always probe `are_tables_missing()` and self-heal even when versions
 *     match — protects against partially-restored backups.
 *
 * @package Linked3
 * @subpackage DB
 */

namespace Linked3\Includes\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrationRunner
{
    /**
     * Versioned migration callbacks. Key = version string; value = callable.
     * Add a new entry when bumping LINKED3_DB_VERSION in linked3.php.
     *
     * @return array<string, callable>
     */
    public static function migrations()
    : array {
        return [
            '0.1.1' => [__CLASS__, 'migrate_to_0_1_1'],
            '0.4.1' => [__CLASS__, 'migrate_to_0_4_1'],
            '0.5.1' => [__CLASS__, 'migrate_to_0_5_1'],
        ];
    }

    /**
     * @return void
     */
    public static function run_pending()
    : void {
        $stored = get_option(LINKED3_DB_VERSION_OPTION, '0');

        // 1) If stored version is 0 (fresh install) or older than code,
        //    create all tables + stamp the version.
        if (version_compare($stored, LINKED3_DB_VERSION, '<')) {
            Schema::create_all();

            foreach (self::migrations() as $version => $cb) {
                if (version_compare($stored, $version, '<')) {
                    call_user_func($cb);
                }
            }

            update_option(LINKED3_DB_VERSION_OPTION, LINKED3_DB_VERSION);
        }

        // 2) Self-heal: even if versions match, verify tables exist.
        //    Cheap probe — checks INFORMATION_SCHEMA for one table.
        if (self::are_tables_missing()) {
            Schema::create_all();
        }
    }

    /**
     * @return bool
     */
    public static function are_tables_missing(): bool
    {
        global $wpdb;
        $names = Schema::qualified_names();
        if (empty($names)) {
            return false;
        }

        // ── FIX v16.0.1: SQLite/Playground fallback ──────────────────────
        // WordPress Playground runs SQLite via a MySQL compatibility layer.
        // INFORMATION_SCHEMA.TABLES is NOT available in SQLite. When the
        // query fails, $wpdb->get_col() returns false, and count(false)
        // triggers a PHP 8.x deprecation warning. We use SHOW TABLES LIKE
        // as a cross-dialect fallback that works in both MySQL and SQLite
        // (via the Playground compatibility shim).
        $missing = false;
        foreach ($names as $table_name) {
            // SHOW TABLES LIKE works in MySQL, MariaDB, and Playground's
            // SQLite shim (which translates it to sqlite_master).
            $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if ($found !== $table_name) {
                $missing = true;
                break;
            }
        }
        return $missing;
    }

    /**
     * v0.1.1 — initial schema creation (no-op since create_all() ran above,
     * but reserved for any post-create data seeding).
     *
     * @return void
     */
    public static function migrate_to_0_1_1()
    : void {
        // Seed default interlink strategy option if absent.
        if (get_option(LINKED3_OPTION_PREFIX . 'interlink_config') === false) {
            update_option(LINKED3_OPTION_PREFIX . 'interlink_config', [
                'max_links'     => 5,
                'min_length'    => 200,
                'nofollow_ext'  => 1,
                'priority'      => 'frequent',
            ]);
        }
    }

    /**
     * v0.4.1 — adds the linked3_push_logs table (handled by Schema::create_all
     * via dbDelta). This migration is reserved for any post-create data
     * seeding for the SEO push subsystem.
     *
     * - Seed default push-engine config options if absent.
     * - Schedule the daily push-log prune cron if not already scheduled.
     *
     * @return void
     */
    public static function migrate_to_0_4_1()
    : void {
        // Seed push-engine default options (empty creds = engine disabled
        // until admin configures them).
        if (get_option(LINKED3_OPTION_PREFIX . 'push_baidu') === false) {
            update_option(LINKED3_OPTION_PREFIX . 'push_baidu', ['site' => '', 'token' => '']);
        }
        if (get_option(LINKED3_OPTION_PREFIX . 'push_toutiao') === false) {
            update_option(LINKED3_OPTION_PREFIX . 'push_toutiao', [
                'site' => '', 'user_name' => '', 'resource_name' => '',
            ]);
        }
        if (get_option(LINKED3_OPTION_PREFIX . 'push_google') === false) {
            update_option(LINKED3_OPTION_PREFIX . 'push_google', [
                'client_email' => '', 'private_key' => '',
            ]);
        }

        // Schedule daily push-log prune (30-day retention).
        if (!wp_next_scheduled('linked3_push_log_prune')) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'linked3_push_log_prune');
        }
    }

    /**
     * v0.5.1 — adds linked3_publish_targets / linked3_publish_logs /
     * linked3_collect_sources tables (handled by Schema::create_all via dbDelta).
     * Seeds a 'local' default publish target for the current site.
     *
     * @return void
     */
    public static function migrate_to_0_5_1()
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_publish_targets';

        // Seed a 'local' default target if none exists.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE type = %s AND is_default = 1", "local")); // phpcs:ignore
        if (!$exists) {
        $wpdb->query($wpdb->prepare(
                "INSERT INTO {$table} (user_id, name, type, config, is_default, status) VALUES (%d, %s, %s, %s, %d, %s)",
                0, __('本地站点', 'linked3'), 'local', wp_json_encode([]), 1, 'active'
            ));
        }
    }
}
