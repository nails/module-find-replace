<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/DatabaseTestCase.php';
require_once __DIR__ . '/Support/ListExclusion.php';

use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Reason;
use Nails\FindReplace\Service\Registry;
use Nails\FindReplace\Service\Schema;
use Tests\FindReplace\Support\ListExclusion;

class SchemaTest extends DatabaseTestCase
{
    public function test_discovery_keeps_text_types_and_drops_the_rest(): void
    {
        $table = 'fr_types';
        $this->createTable($table, "
            `id` INT NOT NULL AUTO_INCREMENT,
            `c_char` CHAR(10) NULL,
            `c_varchar` VARCHAR(10) NULL,
            `c_tinytext` TINYTEXT NULL,
            `c_text` TEXT NULL,
            `c_mediumtext` MEDIUMTEXT NULL,
            `c_longtext` LONGTEXT NULL,
            `c_json` JSON NULL,
            `c_int` INT NULL,
            `c_decimal` DECIMAL(8, 2) NULL,
            `c_datetime` DATETIME NULL,
            `c_enum` ENUM('a', 'b') NULL,
            `c_set` SET('a', 'b') NULL,
            `c_blob` BLOB NULL,
            `c_binary` BINARY(4) NULL,
            PRIMARY KEY (`id`)
        ");

        $columns = $this->columns($table);

        $this->assertSame(
            [
                'c_char',
                'c_varchar',
                'c_tinytext',
                'c_text',
                'c_mediumtext',
                'c_longtext',
                'c_json',
            ],
            array_keys($columns)
        );

        foreach ($columns as $location) {
            $this->assertTrue($location->isSearchable());
            $this->assertSame('id', $location->getPrimaryKey());
        }
    }

    public function test_tables_without_exactly_one_primary_key_are_read_only(): void
    {
        $this->createTable('fr_pk_one', '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(20) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->createTable('fr_pk_none', '
            `body` VARCHAR(20) NULL
        ');
        $this->createTable('fr_pk_composite', '
            `id1` INT NOT NULL,
            `id2` INT NOT NULL,
            `body` VARCHAR(20) NULL,
            PRIMARY KEY (`id1`, `id2`)
        ');

        $single = $this->columns('fr_pk_one')['body'];
        $none = $this->columns('fr_pk_none')['body'];
        $composite = $this->columns('fr_pk_composite')['body'];

        $this->assertTrue($single->isSearchable());
        $this->assertSame('id', $single->getPrimaryKey());

        $this->assertTrue($none->isReadOnly());
        $this->assertNull($none->getPrimaryKey());
        $this->assertSame(Reason::NO_PRIMARY_KEY, $none->getReadOnlyReason());

        $this->assertTrue($composite->isReadOnly());
        $this->assertNull($composite->getPrimaryKey());
        $this->assertSame(Reason::NO_PRIMARY_KEY, $composite->getReadOnlyReason());
    }

    public function test_exclusions_are_applied_during_discovery(): void
    {
        $table = 'fr_rules';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(20) NULL,
            `slug` VARCHAR(20) NULL,
            `token` VARCHAR(20) NULL,
            `ref` VARCHAR(20) NULL,
            `claim_token` VARCHAR(20) NULL,
            `draft_slug` VARCHAR(20) NULL,
            `slug_from` VARCHAR(20) NULL,
            `notes` VARCHAR(20) NULL,
            PRIMARY KEY (`id`)
        ');

        $schema = new Schema($this->pdo(), new Registry([
            new ListExclusion(
                hidden: [$table . '.notes'],
                readOnly: [$table . '.notes'],
            ),
        ]));
        $columns = [];

        foreach ($schema->locations() as $location) {
            if ($location->getTable() === $table) {
                $columns[$location->getColumn()] = $location;
            }
        }

        $this->assertSame(
            ['body', 'slug', 'claim_token', 'draft_slug', 'slug_from'],
            array_keys($columns)
        );
        $this->assertTrue($columns['body']->isSearchable());
        $this->assertTrue($columns['slug']->isReadOnly());
        $this->assertSame(Reason::READ_ONLY, $columns['slug']->getReadOnlyReason());
        $this->assertTrue($columns['claim_token']->isSearchable());
        $this->assertTrue($columns['draft_slug']->isSearchable());
        $this->assertTrue($columns['slug_from']->isSearchable());
        $this->assertArrayNotHasKey('notes', $columns);
        $this->assertArrayNotHasKey('token', $columns);
        $this->assertArrayNotHasKey('ref', $columns);
    }

    /**
     * @return array<string, Location>
     */
    private function columns(string $table): array
    {
        $schema = new Schema($this->pdo(), new Registry([]));
        $columns = [];

        foreach ($schema->locations() as $location) {
            if ($location->getTable() === $table) {
                $columns[$location->getColumn()] = $location;
            }
        }

        return $columns;
    }
}
