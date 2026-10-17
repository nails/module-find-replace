<?php

namespace Nails\FindReplace\Service;

use Nails\Common\Service\PDODatabase;
use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Reason;

class Schema
{
    private const TYPES = [
        'char',
        'varchar',
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'json',
    ];

    /** @var list<array{table: string, column: string}>|null */
    private ?array $textColumns = null;

    /** @var array<string, list<string>>|null */
    private ?array $primaryKeys = null;

    public function __construct(
        private readonly PDODatabase $database,
        private readonly Registry $registry,
    ) {
    }

    public function registry(): Registry
    {
        return $this->registry;
    }

    /**
     * Searchable columns, with hidden columns removed and read-only columns flagged.
     * A table without exactly one primary-key column is read-only.
     *
     * @return list<Location>
     */
    public function locations(): array
    {
        $primaryKeys = $this->primaryKeys();
        $locations = [];

        foreach ($this->textColumns() as $column) {
            $table = $column['table'];
            $name = $column['column'];
            $access = $this->registry->access($table, $name);

            if ($access === Location::HIDDEN) {
                continue;
            }

            $keys = $primaryKeys[$table] ?? [];
            $primaryKey = count($keys) === 1 ? $keys[0] : null;
            $reason = null;

            if ($access === Location::READ_ONLY) {
                $reason = Reason::READ_ONLY;
            } elseif ($primaryKey === null) {
                $access = Location::READ_ONLY;
                $reason = Reason::NO_PRIMARY_KEY;
            }

            $locations[] = new Location($table, $primaryKey, $name, $access, $reason);
        }

        return $locations;
    }

    public function location(string $table, string $column): ?Location
    {
        foreach ($this->locations() as $location) {
            if ($location->getTable() === $table && $location->getColumn() === $column) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Base tables only. Views are not updatable, so they are not searchable.
     *
     * @return list<array{table: string, column: string}>
     */
    private function textColumns(): array
    {
        if ($this->textColumns !== null) {
            return $this->textColumns;
        }

        $types = "'" . implode("', '", self::TYPES) . "'";
        $statement = $this->database->query(
            'SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name
            FROM INFORMATION_SCHEMA.COLUMNS c
            INNER JOIN INFORMATION_SCHEMA.TABLES t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
                AND t.TABLE_NAME = c.TABLE_NAME
            WHERE c.TABLE_SCHEMA = DATABASE()
                AND t.TABLE_TYPE = \'BASE TABLE\'
                AND c.DATA_TYPE IN (' . $types . ')
            ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION'
        );

        $columns = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $table = $row['table_name'] ?? null;
            $column = $row['column_name'] ?? null;

            if (!is_string($table) || !is_string($column)) {
                continue;
            }

            $columns[] = [
                'table' => $table,
                'column' => $column,
            ];
        }

        $this->textColumns = $columns;

        return $this->textColumns;
    }

    /**
     * @return array<string, list<string>>
     */
    private function primaryKeys(): array
    {
        if ($this->primaryKeys !== null) {
            return $this->primaryKeys;
        }

        $statement = $this->database->query(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND CONSTRAINT_NAME = \'PRIMARY\'
            ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );

        $keys = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $table = $row['table_name'] ?? null;
            $column = $row['column_name'] ?? null;

            if (!is_string($table) || !is_string($column)) {
                continue;
            }

            $keys[$table][] = $column;
        }

        $this->primaryKeys = $keys;

        return $this->primaryKeys;
    }
}
