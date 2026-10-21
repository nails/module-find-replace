<?php

namespace Nails\FindReplace\Model;

use Nails\Common\Model\Base;
use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Action;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\StoredResult;
use Nails\FindReplace\Reason;
use Nails\FindReplace\Service\Registry;

class Result extends Base
{
    const TABLE = NAILS_DB_PREFIX . 'find_replace_result';

    const FACET_TABLE = NAILS_DB_PREFIX . 'find_replace_result_facet';

    const DESTRUCTIVE_DELETE = true;

    const AUTO_SET_USER = false;

    const AUTO_SET_TIMESTAMP = false;

    /**
     * @var string|null
     */
    protected $tableLabelColumn = null;

    /**
     * @param list<Hit> $hits
     */
    public function store(int $searchId, array $hits, bool $caseSensitive): void
    {
        $database = $this->database();
        $transaction = $database->transaction();
        $transaction->start();

        try {
            $this->clear($searchId);

            foreach ($hits as $hit) {
                $this->insertHit($searchId, $hit, $caseSensitive);
            }

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->status()) {
                $transaction->rollback();
            }

            throw $exception;
        }
    }

    public function clear(int $searchId): void
    {
        $statement = $this->database()->prepare(
            'DELETE FROM ' . $this->quote(self::TABLE) . ' WHERE `search_id` = :search_id'
        );
        $statement->execute(['search_id' => $searchId]);
    }

    /**
     * @param array<string, string> $facets
     *
     * @return list<StoredResult>
     */
    public function page(
        int $searchId,
        array $facets,
        int $page,
        int $perPage,
        int $contextLength,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        [$facetSql, $parameters] = $this->facetSql($facets);
        $parameters['search_id'] = $searchId;
        $statement = $this->database()->prepare(
            'SELECT result.*
            FROM ' . $this->quote(self::TABLE) . ' result
            WHERE result.search_id = :search_id' . $this->openSql() . $facetSql . '
            ORDER BY result.id ASC
            LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($parameters);

        return $this->hydrate($this->rows($statement), $contextLength);
    }

    /**
     * @param array<string, string> $facets
     */
    public function count(int $searchId, array $facets): int
    {
        [$facetSql, $parameters] = $this->facetSql($facets);
        $parameters['search_id'] = $searchId;
        $statement = $this->database()->prepare(
            'SELECT COUNT(*)
            FROM ' . $this->quote(self::TABLE) . ' result
            WHERE result.search_id = :search_id' . $this->openSql() . $facetSql
        );
        $statement->execute($parameters);
        $count = $statement->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    public function outstanding(int $searchId): int
    {
        return $this->count($searchId, []);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function countsByFacet(int $searchId): array
    {
        $statement = $this->database()->prepare(
            'SELECT facet.facet, facet.value, COUNT(*) AS total
            FROM ' . $this->quote(self::FACET_TABLE) . ' facet
            INNER JOIN ' . $this->quote(self::TABLE) . ' result ON result.id = facet.result_id
            WHERE result.search_id = :search_id' . $this->openSql() . '
            GROUP BY facet.facet, facet.value
            ORDER BY facet.facet ASC, facet.value ASC'
        );
        $statement->execute(['search_id' => $searchId]);
        $counts = [];

        foreach ($this->rows($statement) as $row) {
            $facet = $row['facet'] ?? null;
            $value = $row['value'] ?? null;
            $total = $row['total'] ?? null;

            if (!is_string($facet) || !is_string($value) || !is_numeric($total)) {
                continue;
            }

            $counts[$facet][$value] = (int) $total;
        }

        return $counts;
    }

    /**
     * Ids for this search. When $onlyIds is set, an id from another search returns null.
     * Ids that miss the facet filter are dropped.
     *
     * @param array<string, string> $facets
     * @param list<int>|null $onlyIds
     *
     * @return list<int>|null
     */
    public function ids(int $searchId, array $facets, ?array $onlyIds): ?array
    {
        if ($onlyIds !== null) {
            $onlyIds = array_values(array_unique($onlyIds));

            if ($onlyIds === []) {
                return [];
            }

            if (!$this->belongs($searchId, $onlyIds)) {
                return null;
            }
        }

        [$facetSql, $parameters] = $this->facetSql($facets);
        $parameters['search_id'] = $searchId;
        $limit = '';

        if ($onlyIds !== null) {
            $placeholders = [];

            foreach ($onlyIds as $index => $id) {
                $key = 'only_' . $index;
                $placeholders[] = ':' . $key;
                $parameters[$key] = $id;
            }

            $limit = ' AND result.id IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = $this->database()->prepare(
            'SELECT result.id
            FROM ' . $this->quote(self::TABLE) . ' result
            WHERE result.search_id = :search_id' . $this->openSql() . $facetSql . $limit . '
            ORDER BY result.id ASC'
        );
        $statement->execute($parameters);
        $ids = [];

        foreach ($this->rows($statement) as $row) {
            $id = $row['id'] ?? null;

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<StoredResult>
     */
    public function forIds(int $searchId, array $ids, int $contextLength): array
    {
        if ($ids === []) {
            return [];
        }

        $parameters = ['search_id' => $searchId];
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $id;
        }

        $statement = $this->database()->prepare(
            'SELECT result.*
            FROM ' . $this->quote(self::TABLE) . ' result
            WHERE result.search_id = :search_id
            AND result.id IN (' . implode(', ', $placeholders) . ')
            ORDER BY result.id ASC'
        );
        $statement->execute($parameters);

        return $this->hydrate($this->rows($statement), $contextLength);
    }

    /**
     * @param list<int> $ids
     */
    private function belongs(int $searchId, array $ids): bool
    {
        $parameters = ['search_id' => $searchId];
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $id;
        }

        $statement = $this->database()->prepare(
            'SELECT COUNT(*)
            FROM ' . $this->quote(self::TABLE) . '
            WHERE `search_id` = :search_id
            AND `id` IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
        $count = $statement->fetchColumn();

        return is_numeric($count) && (int) $count === count($ids);
    }

    /**
     * @param array<string, string> $facets
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function facetSql(array $facets): array
    {
        $sql = '';
        $parameters = [];
        $index = 0;

        foreach ($facets as $facet => $value) {
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM ' . $this->quote(self::FACET_TABLE) . ' facet_filter
                WHERE facet_filter.result_id = result.id
                AND facet_filter.facet = :facet_' . $index . '
                AND facet_filter.value = :value_' . $index . '
            )';
            $parameters['facet_' . $index] = $facet;
            $parameters['value_' . $index] = $value;
            $index++;
        }

        return [$sql, $parameters];
    }

    private function openSql(): string
    {
        return ' AND result.applied = 0';
    }

    private function insertHit(int $searchId, Hit $hit, bool $caseSensitive): void
    {
        $matched = $hit->getMatched();

        if ($matched === null) {
            return;
        }

        $statement = $this->database()->prepare(
            'INSERT INTO ' . $this->quote(self::TABLE) . ' (
                `search_id`,
                `table_name`,
                `column_name`,
                `primary_key`,
                `encoding`,
                `term`,
                `snippet`,
                `title`,
                `entity_label`,
                `entity_id`,
                `read_only`,
                `read_only_reason`,
                `actions`
            ) VALUES (
                :search_id,
                :table_name,
                :column_name,
                :primary_key,
                :encoding,
                :term,
                :snippet,
                :title,
                :entity_label,
                :entity_id,
                :read_only,
                :read_only_reason,
                :actions
            )'
        );
        $statement->execute([
            'search_id' => $searchId,
            'table_name' => $hit->getTable(),
            'column_name' => $matched->getColumn(),
            'primary_key' => $hit->getId(),
            'encoding' => $matched->getEncoding(),
            'term' => $matched->getTerm(),
            'snippet' => $matched->getContentWithFixes('', '', $caseSensitive),
            'title' => $hit->getTitle(),
            'entity_label' => $hit->getEntityLabel(),
            'entity_id' => $hit->getEntityId(),
            'read_only' => $hit->isReadOnly() ? 1 : 0,
            'read_only_reason' => $hit->getReadOnlyReason(),
            'actions' => $this->encodeActions($hit),
        ]);
        $resultId = (int) $this->database()->lastInsertId();

        foreach ($hit->getFacets() as $facet => $value) {
            if ($facet === '' || $value === '' || !$this->registry()->accepts($facet, $value)) {
                continue;
            }

            $facetStatement = $this->database()->prepare(
                'INSERT INTO ' . $this->quote(self::FACET_TABLE) . ' (
                    `result_id`,
                    `facet`,
                    `value`
                ) VALUES (
                    :result_id,
                    :facet,
                    :value
                )'
            );
            $facetStatement->execute([
                'result_id' => $resultId,
                'facet' => mb_substr($facet, 0, 64),
                'value' => mb_substr($value, 0, 255),
            ]);
        }
    }

    private function encodeActions(Hit $hit): string
    {
        $actions = [];

        foreach ($hit->getActions() as $action) {
            $actions[] = [
                'label' => $action->getLabel(),
                'url' => $action->getUrl(),
                'class' => $action->getCssClass(),
                'target' => $action->getTarget(),
            ];
        }

        return json_encode($actions, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<StoredResult>
     */
    private function hydrate(array $rows, int $contextLength): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        $facets = $this->facetsFor($ids);
        $stored = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;

            if (!is_numeric($id)) {
                continue;
            }

            $stored[] = new StoredResult((int) $id, $this->hit($row, $facets[(int) $id] ?? [], $contextLength));
        }

        return $stored;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, string>>
     */
    private function facetsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $parameters = [];
        $placeholders = [];

        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $id;
        }

        $statement = $this->database()->prepare(
            'SELECT `result_id`, `facet`, `value`
            FROM ' . $this->quote(self::FACET_TABLE) . '
            WHERE `result_id` IN (' . implode(', ', $placeholders) . ')
            ORDER BY `id` ASC'
        );
        $statement->execute($parameters);
        $facets = [];

        foreach ($this->rows($statement) as $row) {
            $resultId = $row['result_id'] ?? null;
            $facet = $row['facet'] ?? null;
            $value = $row['value'] ?? null;

            if (!is_numeric($resultId) || !is_string($facet) || !is_string($value)) {
                continue;
            }

            $facets[(int) $resultId][$facet] = $value;
        }

        return $facets;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $facets
     */
    private function hit(array $row, array $facets, int $contextLength): Hit
    {
        $table = $this->string($row, 'table_name');
        $column = $this->string($row, 'column_name');
        $primaryKey = $this->string($row, 'primary_key');
        $reason = $this->nullableString($row, 'read_only_reason');
        $matched = new Matched(
            $table,
            $column,
            $this->string($row, 'encoding'),
            $this->string($row, 'term'),
            $primaryKey,
            $this->string($row, 'snippet'),
            null,
            $reason === Reason::SERIALISED,
            $contextLength,
        );

        return new Hit(
            $table,
            $primaryKey,
            $this->nullableString($row, 'entity_label'),
            $this->nullableString($row, 'entity_id'),
            $this->nullableString($row, 'title'),
            $this->actions($row),
            $facets,
            $matched,
            $this->flag($row['read_only'] ?? null),
            $reason,
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<Action>
     */
    private function actions(array $row): array
    {
        $json = $row['actions'] ?? null;

        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $actions = [];

        foreach ($decoded as $action) {
            if (!is_array($action)) {
                continue;
            }

            $label = $action['label'] ?? null;
            $url = $action['url'] ?? null;

            if (!is_string($label) || !is_string($url) || $label === '' || $url === '') {
                continue;
            }

            $class = $action['class'] ?? null;
            $target = $action['target'] ?? null;
            $actions[] = new Action(
                $label,
                $url,
                is_string($class) ? $class : null,
                is_string($target) ? $target : null,
            );
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(\PDOStatement $statement): array
    {
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

            $rows[] = $normalised;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return $this->string($row, $key);
    }

    private function flag(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function registry(): Registry
    {
        $registry = Factory::service('Registry', Constants::MODULE_SLUG);

        if (!$registry instanceof Registry) {
            throw new \RuntimeException('Find-replace registry is not available');
        }

        return $registry;
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
