<?php

namespace Nails\FindReplace\Model;

use Nails\Common\Model\Base;
use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Resource;

class Search extends Base
{
    const TABLE = NAILS_DB_PREFIX . 'find_replace_search';

    const RESOURCE_NAME = 'Search';

    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;

    const DESTRUCTIVE_DELETE = true;

    const AUTO_SET_USER = false;

    const STATUS_PENDING = 'PENDING';

    const STATUS_RUNNING = 'RUNNING';

    const STATUS_COMPLETE = 'COMPLETE';

    const STATUS_FAILED = 'FAILED';

    /**
     * @var bool|null
     */
    protected $tableAutoSetTimestamps = false;

    /**
     * @var string|null
     */
    protected $tableLabelColumn = null;

    /**
     * @param list<string> $includeTables
     * @param list<string> $excludeTables
     */
    public function open(
        int $userId,
        string $term,
        string $replacement,
        bool $caseSensitive,
        array $includeTables,
        array $excludeTables,
        int $contextLength = 80,
    ): int {
        $now = $this->now();
        $statement = $this->database()->prepare(
            'INSERT INTO ' . $this->quote(self::TABLE) . ' (
                `user_id`,
                `term`,
                `replacement`,
                `case_sensitive`,
                `include_tables`,
                `exclude_tables`,
                `context_length`,
                `status`,
                `tables_scanned`,
                `tables_total`,
                `result_count`,
                `persistent`,
                `last_run`,
                `created`
            ) VALUES (
                :user_id,
                :term,
                :replacement,
                :case_sensitive,
                :include_tables,
                :exclude_tables,
                :context_length,
                :status,
                0,
                0,
                0,
                0,
                :last_run,
                :created
            )'
        );
        $statement->execute([
            'user_id' => $userId,
            'term' => $term,
            'replacement' => $replacement,
            'case_sensitive' => $caseSensitive ? 1 : 0,
            'include_tables' => $this->encode($includeTables),
            'exclude_tables' => $this->encode($excludeTables),
            'context_length' => $contextLength,
            'status' => self::STATUS_PENDING,
            'last_run' => $now,
            'created' => $now,
        ]);

        return (int) $this->database()->lastInsertId();
    }

    public function owned(int $id, int $userId): ?Resource\Search
    {
        $rows = $this->getAll(null, null, [
            'where' => [
                ['id', $id],
                ['user_id', $userId],
            ],
        ]);
        $row = $rows[0] ?? null;

        return $row instanceof Resource\Search ? $row : null;
    }

    /**
     * @return list<Resource\Search>
     */
    public function forUser(int $userId): array
    {
        $rows = $this->getAll(null, null, [
            'where' => [
                ['user_id', $userId],
            ],
            'sort' => [['id', 'desc']],
        ]);
        $owned = [];

        foreach ($rows as $row) {
            if ($row instanceof Resource\Search) {
                $owned[] = $row;
            }
        }

        return $owned;
    }

    public function markRunning(int $id): void
    {
        $this->write($id, [
            'status' => self::STATUS_RUNNING,
            'error' => null,
            'tables_scanned' => 0,
            'last_run' => $this->now(),
        ]);
    }

    public function progress(int $id, int $scanned, int $total): void
    {
        $this->write($id, [
            'tables_scanned' => $scanned,
            'tables_total' => $total,
        ]);
    }

    public function markComplete(int $id, int $resultCount): void
    {
        $this->write($id, [
            'status' => self::STATUS_COMPLETE,
            'result_count' => $resultCount,
            'error' => null,
            'last_run' => $this->now(),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->write($id, [
            'status' => self::STATUS_FAILED,
            'error' => $error,
            'last_run' => $this->now(),
        ]);
    }

    public function markPending(int $id): void
    {
        $this->write($id, [
            'status' => self::STATUS_PENDING,
            'tables_scanned' => 0,
            'tables_total' => 0,
            'result_count' => 0,
            'error' => null,
            'last_run' => $this->now(),
        ]);
    }

    public function markPersistent(int $id, bool $persistent): void
    {
        $this->write($id, [
            'persistent' => $persistent ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, int|string|null> $values
     */
    private function write(int $id, array $values): void
    {
        $assignments = [];
        $parameters = ['id' => $id];

        foreach ($values as $column => $value) {
            $assignments[] = $this->quote($column) . ' = :' . $column;
            $parameters[$column] = $value;
        }

        $statement = $this->database()->prepare(
            'UPDATE ' . $this->quote(self::TABLE)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE `id` = :id'
        );
        $statement->execute($parameters);
    }

    /**
     * @param list<string> $entries
     */
    private function encode(array $entries): string
    {
        $json = json_encode($entries, JSON_THROW_ON_ERROR);

        return $json;
    }

    private function now(): string
    {
        $now = Factory::factory('DateTime');

        if (!$now instanceof \DateTimeInterface) {
            throw new \RuntimeException('DateTime is not available');
        }

        return $now->format('Y-m-d H:i:s');
    }

    private function database(): PDODatabase
    {
        $database = Factory::service('PDODatabase');

        if (!$database instanceof PDODatabase) {
            throw new \RuntimeException('PDODatabase is not available');
        }

        return $database;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
