<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/DatabaseTestCase.php';
require_once __DIR__ . '/Support/ListExclusion.php';

use Nails\Common\Service\PDODatabase;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Reason;
use Nails\FindReplace\Service\Registry;
use Tests\FindReplace\Support\ListExclusion;

class EngineTest extends DatabaseTestCase
{
    public function test_search_is_case_sensitive_by_default(): void
    {
        $table = 'fr_case';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'Hello']);

        $engine = $this->engine();

        $this->assertTrue((new Query('hello'))->isCaseSensitive());
        $this->assertCount(0, $engine->search(new Query('hello', tables: [$table])));
        $this->assertCount(1, $engine->search(new Query('Hello', tables: [$table])));
        $this->assertCount(1, $engine->search(new Query('hello', false, [$table])));
    }

    public function test_replace_skips_a_cell_that_no_longer_contains_the_term(): void
    {
        $table = 'fr_stale';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $engine = $this->engine();
        $hits = $engine->search(new Query('alpha', tables: [$table]));
        $this->pdo()->query('UPDATE `' . $table . '` SET `body` = \'beta\' WHERE `id` = 1');

        $outcomes = $engine->replace($hits, 'gamma');

        $this->assertSame(Outcome::SKIPPED, $outcomes[0]->getStatus());
        $this->assertSame(Reason::STALE, $outcomes[0]->getReason());
        $this->assertSame('beta', $this->cell($table, 'body', '1'));
    }

    public function test_replace_skips_a_column_that_is_now_excluded(): void
    {
        $table = 'fr_excluded';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $exclusion = new ListExclusion();
        $engine = $this->engine(new Registry([$exclusion]));
        $hits = $engine->search(new Query('alpha', tables: [$table]));

        $exclusion->setHidden([$table . '.body']);
        $hidden = $engine->replace($hits, 'gamma');

        $this->assertSame(Outcome::SKIPPED, $hidden[0]->getStatus());
        $this->assertSame(Reason::EXCLUDED, $hidden[0]->getReason());
        $this->assertSame('alpha', $this->cell($table, 'body', '1'));

        $exclusion->setHidden([]);
        $exclusion->setReadOnly([$table . '.body']);
        $readOnly = $engine->replace($hits, 'gamma');

        $this->assertSame(Outcome::SKIPPED, $readOnly[0]->getStatus());
        $this->assertSame(Reason::READ_ONLY, $readOnly[0]->getReason());
        $this->assertSame('alpha', $this->cell($table, 'body', '1'));
    }

    public function test_serialised_cells_are_read_only_and_replace_rechecks_them(): void
    {
        $table = 'fr_serial';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` TEXT NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 's:4:"test";']);
        $this->insert($table, ['id' => 2, 'body' => 'token-word']);
        $this->insert($table, ['id' => 3, 'body' => 'a: not serialised']);

        $engine = $this->engine();
        $serialised = $engine->search(new Query('test', tables: [$table]));
        $plain = $engine->search(new Query('token-word', tables: [$table]));
        $marker = $engine->search(new Query('not serialised', tables: [$table]));

        $this->assertCount(1, $serialised);
        $this->assertTrue($serialised[0]->isReadOnly());
        $this->assertSame(Reason::SERIALISED, $serialised[0]->getReadOnlyReason());

        $refused = $engine->replace($serialised, 'exam');
        $this->assertSame(Outcome::SKIPPED, $refused[0]->getStatus());
        $this->assertSame(Reason::SERIALISED, $refused[0]->getReason());
        $this->assertSame('s:4:"test";', $this->cell($table, 'body', '1'));

        $this->assertCount(1, $plain);
        $this->assertFalse($plain[0]->isReadOnly());
        $this->pdo()->query('UPDATE `' . $table . '` SET `body` = \'s:10:"token-word";\' WHERE `id` = 2');

        $became = $engine->replace($plain, 'other');
        $this->assertSame(Outcome::SKIPPED, $became[0]->getStatus());
        $this->assertSame(Reason::SERIALISED, $became[0]->getReason());
        $this->assertSame('s:10:"token-word";', $this->cell($table, 'body', '2'));

        $this->assertCount(1, $marker);
        $this->assertFalse($marker[0]->isReadOnly());
        $applied = $engine->replace($marker, 'plain text');
        $this->assertSame(Outcome::APPLIED, $applied[0]->getStatus());
        $this->assertSame('a: plain text', $this->cell($table, 'body', '3'));
    }

    public function test_a_failed_change_row_rolls_the_update_back(): void
    {
        $table = 'fr_audit';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(50) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $changes = new class extends Change {
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
                throw new \RuntimeException('change insert failed');
            }
        };

        $outcomes = $this->engine()->replace(
            $this->engine()->search(new Query('alpha', tables: [$table])),
            'gamma',
            true,
            $changes,
            Run::cli(),
        );

        $this->assertSame(Outcome::FAILED, $outcomes[0]->getStatus());
        $this->assertSame('change insert failed', $outcomes[0]->getReason());
        $this->assertSame('alpha', $this->cell($table, 'body', '1'));
    }
}
