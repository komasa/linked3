<?php

declare(strict_types=1);
/**
 * Base Repository — abstract data-access layer for Linked3.
 *
 * Introduced in v4.4.3 to standardise the 4 existing repository classes
 * (AutoGPT_Task, Collect_Source, Publish_Target, Push_Log) and eliminate
 * the remaining bare-$wpdb queries scattered across the codebase.
 *
 * Concrete repositories extend this class and declare:
 *   - table_name()         — the WP-prefixed table name (without prefix)
 *   - primary_key()        — the PK column name (default 'id')
 *   - fillable()           — columns allowed for mass insert/update
 *
 * The base class provides:
 *   - all($limit, $offset)              — paginated SELECT *
 *   - find($id)                         — SELECT by PK
 *   - find_where(array $conds)          — SELECT WHERE col = val AND …
 *   - insert(array $data)               — INSERT with sanitization
 *   - update($id, array $data)          — UPDATE by PK
 *   - delete($id)                       — DELETE by PK
 *   - count()                           — COUNT(*)
 *   - exists($id)                       — SELECT EXISTS
 *   - query()                           — return a Query_Builder for complex queries
 *
 * Every method uses $wpdb->prepare() — no raw SQL ever leaves a repository.
 *
 * @package Linked3
 * @subpackage Includes\DB
 */

namespace Linked3\Includes\DB;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseRepository
{
    /** @var \wpdb|null Cached WP database handle. */
    protected $wpdb;

    /**
     * The table name WITHOUT the wp_ prefix. The prefix is applied at
     * runtime so the repository works correctly on multisite installs
     * where each blog has its own prefix.
     *
     * @return string
     */
    abstract protected function table_name(): string;

    /**
     * The primary-key column name. Defaults to 'id'.
     *
     * @return string
     */
    protected function primary_key(): string
    {
        return 'id';
    }

    /**
     * Columns that may be mass-assigned via insert() / update().
     * Subclasses should override this to whitelist their fillable fields.
     *
     * @return string[]
     */
    protected function fillable(): array
    {
        return [];
    }

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the fully-qualified table name (prefix + table_name()).
     *
     * Named `get_table()` (not `table()`) to avoid PHP 8 LSP conflicts with
     * child repositories that define a static `table()` method (e.g.
     * Push_Log_Repository::table()).
     *
     * @return string
     */
    final protected function get_table(): string
    {
        return $this->wpdb->prefix . $this->table_name();
    }

    /**
     * Start a Query_Builder chain for this repository's table.
     *
     * Named `builder()` (not `query()`) to avoid PHP 8 LSP conflicts with
     * child repositories that define a static `query(array $filters)` method
     * (e.g. Push_Log_Repository).
     *
     * @return QueryBuilder
     */
    public function builder(): QueryBuilder
    {
        return new QueryBuilder($this->get_table(), $this->wpdb);
    }

    /**
     * Fetch all rows, paginated.
     *
     * Named `find_all()` (not `all()`) to avoid PHP 8 LSP conflicts with
     * child repositories that define `all($user_id)` with a different
     * signature (e.g. AutoGPT_Task_Repository).
     *
     * @param int $limit   Max rows to return (default 100).
     * @param int $offset  Skip this many rows (default 0).
     * @return array
     */
    public function find_all(int $limit = 100, int $offset = 0): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->get_table()} ORDER BY {$this->primary_key()} DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );
        return (array) $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Find a single row by primary key.
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->get_table()} WHERE {$this->primary_key()} = %d LIMIT 1",
            $id
        );
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * Find rows matching a set of equality conditions.
     *
     * @param array $conds  ['col' => value, ...] — all AND'd together.
     * @param int   $limit
     * @return array
     */
    public function find_where(array $conds, int $limit = 100): array
    {
        if (empty($conds)) {
            return $this->find_all($limit);
        }
        $where = [];
        $values = [];
        foreach ($conds as $col => $val) {
            // Sanitize column name — only [a-z_] allowed.
            if (!preg_match('/^[a-z_]+$/', (string) $col)) {
                continue;
            }
            if (is_int($val)) {
                $where[] = "{$col} = %d";
            } elseif (is_float($val)) {
                $where[] = "{$col} = %f";
            } else {
                $where[] = "{$col} = %s";
            }
            $values[] = $val;
        }
        $where_clause = implode(' AND ', $where);
        $values[] = $limit;
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->get_table()} WHERE {$where_clause} ORDER BY {$this->primary_key()} DESC LIMIT %d",
            $values
        );
        return (array) $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Insert a new row. Only columns listed in fillable() are written.
     *
     * Named `insert_row()` (not `insert()`) to avoid PHP 8 LSP conflicts
     * with child repositories that define a static `insert(array $data)`
     * method (e.g. Push_Log_Repository).
     *
     * @param array $data
     * @return int|null The inserted row's ID, or null on failure.
     */
    public function insert_row(array $data): ?int
    {
        $fillable = $this->fillable();
        if (empty($fillable)) {
            return null;
        }
        $clean = [];
        foreach ($fillable as $col) {
            if (array_key_exists($col, $data)) {
                $clean[$col] = $data[$col];
            }
        }
        if (empty($clean)) {
            return null;
        }

        $ok = $this->wpdb->insert($this->get_table(), $clean);
        if ($ok === false) {
            return null;
        }
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update a row by primary key. Only columns listed in fillable() are written.
     *
     * Named `update_by_pk()` (not `update()`) to avoid PHP 8 LSP conflicts
     * with child repositories that define `update($id, $user_id, $data)`
     * with a different signature (e.g. Collect_Source_Repository,
     * Publish_Target_Repository).
     *
     * @param int   $id
     * @param array $data
     * @return bool True on success (row updated), false otherwise.
     */
    public function update_by_pk(int $id, array $data): bool
    {
        $fillable = $this->fillable();
        if (empty($fillable)) {
            return false;
        }
        $clean = [];
        foreach ($fillable as $col) {
            if (array_key_exists($col, $data)) {
                $clean[$col] = $data[$col];
            }
        }
        if (empty($clean)) {
            return false;
        }

        $ok = $this->wpdb->update(
            $this->get_table(),
            $clean,
            [$this->primary_key() => $id]
        );
        return $ok !== false;
    }

    /**
     * Delete a row by primary key.
     *
     * Named `delete_by_pk()` (not `delete()`) to avoid PHP 8 LSP conflicts
     * with child repositories that define `delete($id, $user_id)` with a
     * different signature.
     *
     * @param int $id
     * @return bool
     */
    public function delete_by_pk(int $id): bool
    {
        $ok = $this->wpdb->delete($this->get_table(), [$this->primary_key() => $id]);
        return $ok !== false;
    }

    /**
     * Count all rows in the table.
     *
     * @return int
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->get_table()}";
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Check whether a row exists with the given primary key.
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->get_table()} WHERE {$this->primary_key()} = %d LIMIT 1",
            $id
        );
        return $this->wpdb->get_var($sql) !== null;
    }
}
