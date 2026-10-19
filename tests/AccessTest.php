<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/AdminDatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Service\Access;

class AccessTest extends AdminDatabaseTestCase
{
    public function test_ownership_and_facets_limit_every_action(): void
    {
        $owner = 11;
        $other = 22;
        $ownedId = $this->searches()->open($owner, 'alpha', 'beta', true, [], []);
        $otherId = $this->searches()->open($other, 'alpha', 'beta', true, [], []);
        $page = $this->hit('1', ['resolver' => 'Page', 'kind' => 'page']);
        $news = $this->hit('2', ['resolver' => 'News', 'kind' => 'news']);
        $this->results()->store($ownedId, [$page, $news], true);
        $foreign = $this->hit('9', ['resolver' => 'Page', 'kind' => 'page']);
        $this->results()->store($otherId, [$foreign], true);

        $access = $this->access();
        $pageId = $this->resultId($ownedId, '1');
        $newsId = $this->resultId($ownedId, '2');
        $foreignId = $this->resultId($otherId, '9');

        $this->assertNotNull($access->search($ownedId, $owner));
        $this->assertNull($access->search($ownedId, $other));
        $this->assertSame([$pageId], $access->resultIds($ownedId, $owner, ['kind' => 'page'], null));
        $this->assertSame([$pageId], $access->resultIds($ownedId, $owner, ['kind' => 'page'], [$pageId, $newsId]));
        $this->assertNull($access->resultIds($ownedId, $owner, [], [$pageId, $foreignId]));
        $this->assertNull($access->resultIds($otherId, $owner, [], null));

        $adminRun = Run::admin($owner, $ownedId);
        $cliRun = Run::cli();
        $this->changes()->record(
            $this->pdo(),
            $adminRun,
            'fixture',
            '1',
            'body',
            'plaintext',
            'alpha',
            'beta',
            'alpha',
            'beta',
            $ownedId,
        );
        $this->changes()->record(
            $this->pdo(),
            $cliRun,
            'fixture',
            '1',
            'body',
            'plaintext',
            'alpha',
            'beta',
            'alpha',
            'beta',
        );

        $adminChange = $this->changeId($adminRun->getId());
        $cliChange = $this->changeId($cliRun->getId());

        $this->assertNotNull($access->change($adminChange, $owner));
        $this->assertNull($access->change($adminChange, $other));
        $this->assertNull($access->change($cliChange, $owner));
        $this->assertCount(1, $access->run($adminRun->getId(), $owner));
        $this->assertSame([], $access->run($adminRun->getId(), $other));
        $this->assertSame([], $access->run($cliRun->getId(), $owner));
        $this->assertCount(1, $this->changes()->forUser($this->pdo(), $owner));
        $this->assertSame([], $this->changes()->forUser($this->pdo(), $other));
    }

    public function test_closed_facet_values_are_not_stored(): void
    {
        $id = $this->searches()->open(11, 'alpha', 'beta', true, [], []);
        $this->results()->store($id, [
            $this->hit('1', [
                'resolver' => 'Page',
                'status' => 'ARCHIVED',
                'language' => 'FR',
                'kind' => 'page',
            ]),
        ], true);

        $counts = $this->results()->countsByFacet($id);

        $this->assertSame(['Page' => 1], $counts['resolver'] ?? null);
        $this->assertSame(['page' => 1], $counts['kind'] ?? null);
        $this->assertArrayNotHasKey('status', $counts);
        $this->assertArrayNotHasKey('language', $counts);
    }

    /**
     * @param array<string, string> $facets
     */
    private function hit(string $id, array $facets): Hit
    {
        return new Hit(
            'fixture',
            $id,
            facets: $facets,
            matched: new Matched('fixture', 'body', 'plaintext', 'alpha', $id, 'alpha beta'),
        );
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

    private function changes(): Change
    {
        $changes = Factory::model('Change', Constants::MODULE_SLUG);

        if (!$changes instanceof Change) {
            throw new \RuntimeException('Find-replace change model is not available');
        }

        return $changes;
    }

    private function access(): Access
    {
        $access = Factory::service('Access', Constants::MODULE_SLUG);

        if (!$access instanceof Access) {
            throw new \RuntimeException('Find-replace access checks are not available');
        }

        return $access;
    }

    private function resultId(int $searchId, string $primaryKey): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT `id` FROM `nails_find_replace_result` WHERE `search_id` = :search_id AND `primary_key` = :primary_key'
        );
        $statement->execute([
            'search_id' => $searchId,
            'primary_key' => $primaryKey,
        ]);
        $id = $statement->fetchColumn();

        if (!is_numeric($id)) {
            $this->fail('Stored result was not found');
        }

        return (int) $id;
    }

    private function changeId(string $runId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT `id` FROM `nails_find_replace_change` WHERE `run_id` = :run_id'
        );
        $statement->execute(['run_id' => $runId]);
        $id = $statement->fetchColumn();

        if (!is_numeric($id)) {
            $this->fail('Change row was not found');
        }

        return (int) $id;
    }
}
