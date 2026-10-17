<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/AdminDatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Housekeeping\Searches;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Service\Logger;

class SearchesTest extends AdminDatabaseTestCase
{
    public function test_it_deletes_a_search_and_its_rows_together_after_90_days(): void
    {
        $routine = new Searches();
        $old = $this->agedSearch(Searches::THRESHOLD + 1, false);
        $keptPersistent = $this->agedSearch(Searches::THRESHOLD + 1, true);
        $recent = $this->agedSearch(0, false);
        $oldFacets = $this->facetIds($old);
        $keptFacets = $this->facetIds($keptPersistent);

        $this->assertSame(90, Searches::THRESHOLD);
        $this->assertSame('@daily', $routine->getCronExpression());
        $this->assertSame(
            'Deletes non-persistent find-replace searches, and their results, 90 days after they were last run',
            $routine->getDescription(),
        );

        $result = $routine->execute(new Context(false, $this->createStub(Logger::class), Searches::class));

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertSame(1, $result->getProcessed());
        Factory::destroyModel('Search', Constants::MODULE_SLUG);
        Factory::destroyModel('Result', Constants::MODULE_SLUG);
        Factory::destroyModel('ResultFacet', Constants::MODULE_SLUG);
        $this->assertNull($this->searches()->getById($old));
        $this->assertSame(0, $this->visibleResults($old));
        $this->assertSame(0, $this->visibleIds($oldFacets));
        $this->assertNotNull($this->searches()->getById($keptPersistent));
        $this->assertSame(1, $this->visibleResults($keptPersistent));
        $this->assertSame(1, $this->visibleIds($keptFacets));
        $this->assertNotNull($this->searches()->getById($recent));
        $this->assertSame(1, $this->visibleResults($recent));
    }

    public function test_dry_run_does_not_delete_the_search_group(): void
    {
        $old = $this->agedSearch(Searches::THRESHOLD + 1, false);
        $oldFacets = $this->facetIds($old);
        $result = (new Searches())->execute(new Context(true, $this->createStub(Logger::class), Searches::class));

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertSame(1, $result->getProcessed());
        Factory::destroyModel('Search', Constants::MODULE_SLUG);
        Factory::destroyModel('Result', Constants::MODULE_SLUG);
        Factory::destroyModel('ResultFacet', Constants::MODULE_SLUG);
        $this->assertNotNull($this->searches()->getById($old));
        $this->assertSame(1, $this->visibleResults($old));
        $this->assertSame(1, $this->visibleIds($oldFacets));
    }

    private function agedSearch(int $daysAgo, bool $persistent): int
    {
        $id = $this->searches()->open(7, 'alpha', 'beta', true, [], []);
        $created = Factory::factory('DateTime');

        if (!$created instanceof \DateTime) {
            throw new \RuntimeException('DateTime is not available');
        }

        $created->modify('-' . $daysAgo . ' days');
        $statement = $this->pdo()->prepare(
            'UPDATE `nails_find_replace_search`
            SET `last_run` = :last_run, `persistent` = :persistent
            WHERE `id` = :id'
        );
        $statement->execute([
            'last_run' => $created->format('Y-m-d H:i:s'),
            'persistent' => $persistent ? 1 : 0,
            'id' => $id,
        ]);
        $this->results()->store($id, [
            new Hit(
                'fixture',
                '1',
                facets: ['resolver' => 'Unresolved'],
                matched: new Matched('fixture', 'body', 'plaintext', 'alpha', '1', 'alpha'),
            ),
        ], true);

        return $id;
    }

    private function searches(): Search
    {
        $searches = Factory::model('Search', Constants::MODULE_SLUG);

        if (!$searches instanceof Search) {
            throw new \RuntimeException('Find-replace search model is not available');
        }

        return $searches;
    }

    private function results(): Result
    {
        $results = Factory::model('Result', Constants::MODULE_SLUG);

        if (!$results instanceof Result) {
            throw new \RuntimeException('Find-replace result model is not available');
        }

        return $results;
    }

    private function visibleResults(int $searchId): int
    {
        return count($this->results()->getAll(null, null, [
            'where' => [
                ['search_id', $searchId],
            ],
        ]));
    }

    /**
     * @return list<int>
     */
    private function facetIds(int $searchId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT facet.id
            FROM `nails_find_replace_result_facet` facet
            INNER JOIN `nails_find_replace_result` result ON result.id = facet.result_id
            WHERE result.search_id = :search_id'
        );
        $statement->execute(['search_id' => $searchId]);
        $ids = [];

        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     */
    private function visibleIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $facets = Factory::model('ResultFacet', Constants::MODULE_SLUG);

        if (!$facets instanceof \Nails\FindReplace\Model\Result\Facet) {
            throw new \RuntimeException('Find-replace facet model is not available');
        }

        return count($facets->getAll(null, null, [
            'where_in' => [
                ['id', $ids],
            ],
        ]));
    }
}
