<?php

namespace Nails\FindReplace\Model;

use Nails\Common\Model\Base;
use Nails\Common\Service\PDODatabase;
use Nails\FindReplace\Factory\ChangeRecord;
use Nails\FindReplace\Factory\Run;

class Change extends Base
{
    const TABLE = NAILS_DB_PREFIX . 'find_replace_change';

    const RESOURCE_NAME = 'Change';

    const RESOURCE_PROVIDER = \Nails\FindReplace\Constants::MODULE_SLUG;

    const DESTRUCTIVE_DELETE = true;

    /**
     * Created is written on the PDO connection inside the update transaction.
     */
    const AUTO_SET_TIMESTAMP = false;

    /**
     * @var string|null
     */
    protected $tableLabelColumn = null;

    /**
     * Rows are inserted on the PDO connection that is already inside the update transaction.
     */
    public function record(
        PDODatabase $database,
        Run $run,
        string $table,
        string $primaryKey,
        string $column,
        string $encoding,
        string $term,
        string $replacement,
        string $valueBefore,
        string $valueAfter,
        ?int $searchId = null,
    ): void {
        $now = \Nails\Factory::factory('DateTime');

        if (!$now instanceof \DateTimeInterface) {
            throw new \RuntimeException('DateTime is not available');
        }

        $statement = $database->prepare(
            'INSERT INTO ' . $this->quote($this->getTableName()) . ' (
                `run_id`,
                `search_id`,
                `user_id`,
                `source`,
                `table_name`,
                `primary_key`,
                `column_name`,
                `encoding`,
                `term`,
                `replacement`,
                `value_before`,
                `value_after`,
                `created`
            ) VALUES (
                :run_id,
                :search_id,
                :user_id,
                :source,
                :table_name,
                :primary_key,
                :column_name,
                :encoding,
                :term,
                :replacement,
                :value_before,
                :value_after,
                :created
            )'
        );
        $statement->execute([
            'run_id' => $run->getId(),
            'search_id' => $searchId ?? $run->getSearchId(),
            'user_id' => $run->getUserId(),
            'source' => $run->getSource(),
            'table_name' => $table,
            'primary_key' => $primaryKey,
            'column_name' => $column,
            'encoding' => $encoding,
            'term' => $term,
            'replacement' => $replacement,
            'value_before' => $valueBefore,
            'value_after' => $valueAfter,
            'created' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<ChangeRecord>
     */
    public function forRun(PDODatabase $database, string $runId): array
    {
        $statement = $database->prepare(
            'SELECT
                `id`,
                `run_id`,
                `search_id`,
                `user_id`,
                `source`,
                `table_name`,
                `primary_key`,
                `column_name`,
                `encoding`,
                `term`,
                `replacement`,
                `value_before`,
                `value_after`,
                `created`
            FROM ' . $this->quote($this->getTableName()) . '
            WHERE `run_id` = :run_id
            ORDER BY `id` ASC'
        );
        $statement->execute(['run_id' => $runId]);
        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalised = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalised[$key] = $value;
                }
            }

            $rows[] = $this->recordFrom($normalised);
        }

        return $rows;
    }

    public function findForUser(PDODatabase $database, int $id, int $userId): ?ChangeRecord
    {
        $rows = $this->selectWhere(
            $database,
            '`id` = :id AND `user_id` = :user_id AND `source` = :source',
            [
                'id' => $id,
                'user_id' => $userId,
                'source' => Run::SOURCE_ADMIN,
            ],
        );

        return $rows[0] ?? null;
    }

    /**
     * Admin changes for one user, newest first. CLI changes are omitted.
     *
     * @return list<ChangeRecord>
     */
    public function forUser(PDODatabase $database, int $userId): array
    {
        return $this->selectWhere(
            $database,
            '`user_id` = :user_id AND `source` = :source ORDER BY `id` DESC',
            [
                'user_id' => $userId,
                'source' => Run::SOURCE_ADMIN,
            ],
        );
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return list<ChangeRecord>
     */
    private function selectWhere(PDODatabase $database, string $where, array $parameters): array
    {
        $statement = $database->prepare(
            'SELECT
                `id`,
                `run_id`,
                `search_id`,
                `user_id`,
                `source`,
                `table_name`,
                `primary_key`,
                `column_name`,
                `encoding`,
                `term`,
                `replacement`,
                `value_before`,
                `value_after`,
                `created`
            FROM ' . $this->quote($this->getTableName()) . '
            WHERE ' . $where
        );
        $statement->execute($parameters);
        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalised = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalised[$key] = $value;
                }
            }

            $rows[] = $this->recordFrom($normalised);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function recordFrom(array $row): ChangeRecord
    {
        return new ChangeRecord(
            $this->intValue($row, 'id'),
            $this->stringValue($row, 'run_id'),
            $this->nullableInt($row, 'search_id'),
            $this->nullableInt($row, 'user_id'),
            $this->stringValue($row, 'source'),
            $this->stringValue($row, 'table_name'),
            $this->stringValue($row, 'primary_key'),
            $this->stringValue($row, 'column_name'),
            $this->stringValue($row, 'encoding'),
            $this->stringValue($row, 'term'),
            $this->stringValue($row, 'replacement'),
            $this->stringValue($row, 'value_before'),
            $this->stringValue($row, 'value_after'),
            $this->optionalString($row, 'created'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \RuntimeException('Change row is missing ' . $key);
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \RuntimeException('Change row is missing ' . $key);
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableInt(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \RuntimeException('Change row has an invalid ' . $key);
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function optionalString(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        $value = $row[$key];

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        return (string) $value;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
