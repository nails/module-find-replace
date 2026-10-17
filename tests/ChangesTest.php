<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/DatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Database\Migration\Migration0;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Housekeeping\Changes;
use Nails\FindReplace\Model\Change;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Service\Logger;

class ChangesTest extends DatabaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_change`');
        (new Migration0($this->pdo()))->execute();
        $this->registerTable('nails_find_replace_change');
    }

    public function test_migration_creates_the_change_table(): void
    {
        $columns = [];

        foreach ($this->pdo()->query('SHOW COLUMNS FROM `nails_find_replace_change`')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $field = $row['Field'] ?? null;

            if (!is_string($field)) {
                continue;
            }

            $columns[$field] = $row;
        }

        $this->assertSame(
            [
                'id',
                'run_id',
                'search_id',
                'user_id',
                'source',
                'table_name',
                'primary_key',
                'column_name',
                'encoding',
                'term',
                'replacement',
                'value_before',
                'value_after',
                'created',
            ],
            array_keys($columns),
        );
        $searchNull = $columns['search_id']['Null'] ?? null;
        $userNull = $columns['user_id']['Null'] ?? null;
        $sourceType = $columns['source']['Type'] ?? null;

        if (!is_string($searchNull) || !is_string($userNull) || !is_string($sourceType)) {
            $this->fail('Change table column metadata is missing');
        }

        $this->assertSame('YES', $searchNull);
        $this->assertSame('YES', $userNull);
        $this->assertStringContainsString('ADMIN', $sourceType);
        $this->assertStringContainsString('CLI', $sourceType);
    }

    public function test_it_deletes_change_rows_older_than_90_days(): void
    {
        $routine = new Changes();
        $old = $this->insertAged(Changes::THRESHOLD + 1);
        $kept = $this->insertAged(Changes::THRESHOLD - 1);
        $recent = $this->insertAged(0);

        $this->assertSame(90, Changes::THRESHOLD);
        $this->assertSame('@daily', $routine->getCronExpression());
        $this->assertSame('Deletes find-replace change rows older than 90 days', $routine->getDescription());

        $result = $routine->execute(new Context(false, $this->createStub(Logger::class), Changes::class));

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertSame(1, $result->getProcessed());
        Factory::destroyModel('Change', Constants::MODULE_SLUG);
        $this->assertNull($this->model()->getById($old));
        $this->assertNotNull($this->model()->getById($kept));
        $this->assertNotNull($this->model()->getById($recent));
    }

    public function test_dry_run_does_not_delete_change_rows(): void
    {
        $old = $this->insertAged(Changes::THRESHOLD + 1);
        $result = (new Changes())->execute(new Context(true, $this->createStub(Logger::class), Changes::class));

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertSame(1, $result->getProcessed());
        Factory::destroyModel('Change', Constants::MODULE_SLUG);
        $this->assertNotNull($this->model()->getById($old));
    }

    private function model(): Change
    {
        $model = Factory::model('Change', Constants::MODULE_SLUG);

        if (!$model instanceof Change) {
            throw new \RuntimeException('Find-replace change model is not available');
        }

        return $model;
    }

    private function insertAged(int $daysAgo): int
    {
        $created = Factory::factory('DateTime');

        if (!$created instanceof \DateTime) {
            throw new \RuntimeException('DateTime is not available');
        }

        $created->modify('-' . $daysAgo . ' days');
        $statement = $this->pdo()->prepare(
            'INSERT INTO `nails_find_replace_change` (
                `run_id`, `source`, `table_name`, `primary_key`, `column_name`,
                `encoding`, `term`, `replacement`, `value_before`, `value_after`, `created`
            ) VALUES (
                :run_id, :source, :table_name, :primary_key, :column_name,
                :encoding, :term, :replacement, :value_before, :value_after, :created
            )'
        );
        $statement->execute([
            'run_id' => Run::id(),
            'source' => Run::SOURCE_CLI,
            'table_name' => 'fixture',
            'primary_key' => '1',
            'column_name' => 'body',
            'encoding' => 'plaintext',
            'term' => 'alpha',
            'replacement' => 'beta',
            'value_before' => 'alpha',
            'value_after' => 'beta',
            'created' => $created->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }
}
