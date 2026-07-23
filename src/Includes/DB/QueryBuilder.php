<?php

declare(strict_types=1);
/**
 * Query Builder — fluent chain for $wpdb SELECT queries.
 *
 * Introduced in v4.4.3 alongside Base_Repository to give repositories a
 * type-safe, SQL-injection-proof way to express complex queries without
 * writing raw SQL strings.
 *
 * Example:
 *   $rows = $repo->query()
 *       ->select(['id', 'name', 'status'])
 *       ->where('status', '=', 'active')
 *       ->where('user_id', '>', 0)
 *       ->order_by('created_at', 'DESC')
 *       ->limit(50)
 *       ->offset(0)
 *       ->get();
 *
 * Every value passed to where() is $wpdb->prepare'd. Column names are
 * validated against ^[a-z_]+$ to prevent SQL injection through the
 * column position.
 *
 * @package Linked3
 * @subpackage Includes\DB
 */

namespace Linked3\Includes\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class QueryBuilder
{
    /** @var string */
    private $table;

    /** @var \wpdb */
    private $wpdb;

    /** @var string[] Columns to SELECT (empty = SELECT *). */
    private $select_cols = [];

    /** @var array{col:string, op:string, val:mixed}[] */
    private $wheres = [];

    /** @var array{col:string, dir:string}[] */
    private $orders = [];

    /** @var int|null */
    private $limit = null;

    /** @var int */
    private $offset = 0;

    /** @var array{col:string, val:mixed}[] GROUP BY columns. */
    private $groups = [];

    public function __construct(string $table, \wpdb $wpdb) {
        $this->table = $table;
        $this->wpdb = $wpdb;
    }

    /**
     * Set the columns to SELECT. Defaults to *.
     *
     * @param string[] $cols
     * @return self
     */
    public function select(array $cols = []): self
    {
        // Validate column names.
        foreach ($cols as $c) {
            if (!preg_match('/^[a-z_]+$/', (string) $c)) {
                throw new \InvalidArgumentException(sprintf('Invalid column name "%s".', $c));
            }
        }
        $this->select_cols = $cols;
        return $this;
    }

    /**
     * Add a WHERE clause. Operator is whitelisted.
     *
     * @param string $col
     * @param string $op  One of =, !=, >, >=, <, <=, LIKE, NOT LIKE, IN.
     * @param mixed  $val
     * @return self
     */
    public function where(string $col, string $op, $val): self
    {
        if (!preg_match('/^[a-z_]+$/', $col)) {
            throw new \InvalidArgumentException(sprintf('Invalid column name "%s".', $col));
        }
        $allowed = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
        if (!in_array(strtoupper($op), $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported operator "%s".', $op));
        }
        $this->wheres[] = ['col' => $col, 'op' => strtoupper($op), 'val' => $val];
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $col
     * @param string $dir 'ASC' or 'DESC'.
     * @return self
     */
    public function order_by(string $col, string $dir = 'ASC'): self
    {
        if (!preg_match('/^[a-z_]+$/', $col)) {
            throw new \InvalidArgumentException(sprintf('Invalid column name "%s".', $col));
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = ['col' => $col, 'dir' => $dir];
        return $this;
    }

    /**
     * Set the LIMIT.
     *
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    /**
     * Set the OFFSET.
     *
     * @param int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * Execute the query and return all matching rows.
     *
     * @return array
     */
    public function get(): array
    {
        return (array) $this->wpdb->get_results($this->build_sql(), ARRAY_A);
    }

    /**
     * Execute the query and return the first row only.
     *
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit = 1;
        $row = $this->wpdb->get_row($this->build_sql(), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Execute a COUNT(*) query with the current WHERE conditions.
     *
     * @return int
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}" . $this->build_where_clause();
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Build the full SQL string with prepare'd values inlined.
     *
     * @return string
     */
    private function build_sql(): string
    {
        $cols = empty($this->select_cols) ? '*' : implode(', ', $this->select_cols);
        $sql = "SELECT {$cols} FROM {$this->table}";
        $sql .= $this->build_where_clause();

        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(static fn($g) => $g['col'], $this->groups));
        }

        if (!empty($this->orders)) {
            $parts = array_map(static fn($o) => "{$o['col']} {$o['dir']}", $this->orders);
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= $this->wpdb->prepare(' LIMIT %d', $this->limit);
            if ($this->offset > 0) {
                $sql .= $this->wpdb->prepare(' OFFSET %d', $this->offset);
            }
        }

        return $sql;
    }

    /**
     * Build the WHERE clause as a prepare'd SQL fragment.
     *
     * @return string
     */
    private function build_where_clause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $clauses = [];
        $values = [];
        foreach ($this->wheres as $w) {
            $col = $w['col'];
            $op = $w['op'];
            $val = $w['val'];

            if ($op === 'IN' || $op === 'NOT IN') {
                if (!is_array($val) || empty($val)) {
                    // Empty IN () is a SQL syntax error — short-circuit to a
                    // tautology that matches nothing.
                    $clauses[] = '1=0';
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($val), is_int(reset($val)) ? '%d' : '%s'));
                $clauses[] = "{$col} {$op} ({$placeholders})";
                foreach ($val as $v) {
                    $values[] = $v;
                }
            } elseif (is_int($val)) {
                $clauses[] = "{$col} {$op} %d";
                $values[] = $val;
            } elseif (is_float($val)) {
                $clauses[] = "{$col} {$op} %f";
                $values[] = $val;
            } else {
                $clauses[] = "{$col} {$op} %s";
                $values[] = $val;
            }
        }
        $where = implode(' AND ', $clauses);
        if (empty($values)) {
            return " WHERE {$where}";
        }
        return $this->wpdb->prepare(" WHERE {$where}", $values);
    }
}
