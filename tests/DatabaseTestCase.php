<?php

namespace Tests\FindReplace;

use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Registry;
use Nails\FindReplace\Service\Schema;

abstract class DatabaseTestCase extends \Nails\Common\Tests\TestCase
{
    /** @var list<string> */
    private array $tables = [];

    protected function registerTable(string $name): void
    {
        $this->tables[] = $name;
    }

    protected function pdo(): PDODatabase
    {
        $database = Factory::service('PDODatabase');

        if (!$database instanceof PDODatabase) {
            throw new \RuntimeException('PDODatabase is not available');
        }

        return $database;
    }

    protected function engine(?Registry $registry = null): Engine
    {
        $registry ??= new Registry([]);

        return new Engine(new Schema($this->pdo(), $registry), $this->pdo());
    }

    protected function createTable(string $name, string $definition): void
    {
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        $this->pdo()->query('DROP TABLE IF EXISTS ' . $quoted);
        $this->pdo()->query(
            'CREATE TABLE ' . $quoted . ' (' . $definition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->tables[] = $name;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    protected function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $quoted = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
        $statement = $this->pdo()->prepare(
            'INSERT INTO `' . $table . '` (' . implode(', ', $quoted) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($row);
    }

    protected function cell(string $table, string $column, string $id): ?string
    {
        $statement = $this->pdo()->prepare(
            'SELECT `' . $column . '` FROM `' . $table . '` WHERE `id` = :id'
        );
        $statement->execute(['id' => $id]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tables as $name) {
            $this->pdo()->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $name) . '`');
        }
    }
}
