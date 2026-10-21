<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/AdminDatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search;

class ResultListTest extends AdminDatabaseTestCase
{
    public function test_an_applied_row_leaves_the_list_and_comes_back_on_revert(): void
    {
        $table = 'fr_applied';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $searchId = $this->searches()->open(7, 'alpha', 'gamma', true, [], []);
        $engine = $this->engine();
        $hits = $engine->search(new Query('alpha', tables: [$table]));
        $this->results()->store($searchId, $hits, true);
        $this->assertSame(1, $this->results()->outstanding($searchId));

        $run = Run::admin(7, $searchId);
        $outcomes = $engine->replace($hits, 'gamma', true, $this->changes(), $run);

        $this->assertSame(Outcome::APPLIED, $outcomes[0]->getStatus());
        $this->assertSame('gamma', $this->cell($table, 'body', '1'));
        $this->assertSame(0, $this->results()->outstanding($searchId));
        $this->assertSame([], $this->results()->page($searchId, [], 1, 50, 80));
        $this->assertSame([], $this->results()->ids($searchId, [], null));

        $reverted = $engine->revert($run->getId(), $this->changes(), Run::admin(7, $searchId));

        $this->assertSame(Outcome::APPLIED, $reverted[0]->getStatus());
        $this->assertSame('alpha', $this->cell($table, 'body', '1'));
        $this->assertSame(1, $this->results()->outstanding($searchId));
    }

    public function test_a_replacement_that_still_contains_the_term_stays_hidden(): void
    {
        $table = 'fr_applied_term';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $searchId = $this->searches()->open(7, 'alpha', 'alpha beta', true, [], []);
        $engine = $this->engine();
        $hits = $engine->search(new Query('alpha', tables: [$table]));
        $this->results()->store($searchId, $hits, true);
        $outcomes = $engine->replace(
            $hits,
            'alpha beta',
            true,
            $this->changes(),
            Run::admin(7, $searchId),
        );

        $this->assertSame(Outcome::APPLIED, $outcomes[0]->getStatus());
        $this->assertSame(0, $this->results()->outstanding($searchId));
    }

    public function test_a_skip_and_a_cli_replace_leave_the_row_listed(): void
    {
        $table = 'fr_applied_skip';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);
        $this->insert($table, ['id' => 2, 'body' => 'alpha']);

        $searchId = $this->searches()->open(7, 'alpha', 'gamma', true, [], []);
        $engine = $this->engine();
        $hits = [];

        foreach ($engine->search(new Query('alpha', tables: [$table])) as $hit) {
            $hits[$hit->getId()] = $hit;
        }

        $this->results()->store($searchId, array_values($hits), true);
        $this->pdo()->query('UPDATE `' . $table . '` SET `body` = \'beta\' WHERE `id` = 1');

        $skipped = $engine->replace([$hits['1']], 'gamma', true, $this->changes(), Run::admin(7, $searchId));
        $cli = $engine->replace([$hits['2']], 'gamma', true, $this->changes(), Run::cli());

        $this->assertSame(Outcome::SKIPPED, $skipped[0]->getStatus());
        $this->assertSame(Outcome::APPLIED, $cli[0]->getStatus());
        $this->assertSame(2, $this->results()->outstanding($searchId));
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
}
