<?php

namespace Tests\FindReplace\Queue;

require_once __DIR__ . '/../AdminDatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Queue\Task\Search as SearchTask;
use Nails\FindReplace\Service\Access;
use Nails\Queue\Factory\Data;
use Tests\FindReplace\AdminDatabaseTestCase;

class SearchTaskTest extends AdminDatabaseTestCase
{
    public function test_run_stores_results_and_progress_for_the_owner(): void
    {
        $table = 'fr_queue';
        $term = 'fr2677queuealpha';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'before ' . $term . ' after']);
        $this->freshEngine();

        $id = $this->searches()->open(41, $term, 'beta', true, [$table], []);
        (new SearchTask())->run(new Data($id));

        $row = $this->searchRow($id);
        $total = $this->number($row, 'tables_total');
        $scanned = $this->number($row, 'tables_scanned');
        $this->assertSame(Search::STATUS_COMPLETE, $row['status']);
        $this->assertGreaterThan(0, $total);
        $this->assertSame($total, $scanned);
        $this->assertSame(1, $this->number($row, 'result_count'));
        $this->assertSame(1, $this->countRows('nails_find_replace_result', $id));
        $this->assertSame(
            'Unresolved',
            $this->pdo()->query(
                'SELECT facet.value
                FROM `nails_find_replace_result_facet` facet
                INNER JOIN `nails_find_replace_result` result ON result.id = facet.result_id
                WHERE result.search_id = ' . $id . ' AND facet.facet = \'resolver\''
            )->fetchColumn(),
        );

        $access = $this->access();
        $this->assertNotNull($access->search($id, 41));
        $this->assertNull($access->search($id, 42));
    }

    public function test_invalid_scope_marks_the_search_failed(): void
    {
        $id = $this->searches()->open(41, 'alpha', 'beta', true, ['/(/'], []);

        try {
            (new SearchTask())->run(new Data($id));
            $this->fail('An invalid table pattern must fail the search');
        } catch (\InvalidArgumentException) {
        }

        $row = $this->searchRow($id);
        $error = $row['error'] ?? null;
        $this->assertSame(Search::STATUS_FAILED, $row['status']);
        $this->assertIsString($error);
        $this->assertNotSame('', $error);
    }

    private function freshEngine(): void
    {
        Factory::destroyService('Engine', Constants::MODULE_SLUG);
        Factory::destroyService('Schema', Constants::MODULE_SLUG);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function number(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        $this->fail($key . ' was not numeric');
    }

    /**
     * @return array<string, mixed>
     */
    private function searchRow(int $id): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT `status`, `tables_scanned`, `tables_total`, `result_count`, `error`
            FROM `nails_find_replace_search` WHERE `id` = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $this->fail('Saved search was not returned');
        }

        return $row;
    }

    private function searches(): Search
    {
        $searches = Factory::model('Search', Constants::MODULE_SLUG);

        if (!$searches instanceof Search) {
            throw new \RuntimeException('Find-replace search model is not available');
        }

        return $searches;
    }

    private function access(): Access
    {
        $access = Factory::service('Access', Constants::MODULE_SLUG);

        if (!$access instanceof Access) {
            throw new \RuntimeException('Find-replace access checks are not available');
        }

        return $access;
    }

    private function countRows(string $table, int $searchId): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `search_id` = :search_id');
        $statement->execute(['search_id' => $searchId]);
        $count = $statement->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }
}
