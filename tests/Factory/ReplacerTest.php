<?php

namespace Tests\FindReplace\Factory;

require_once dirname(__DIR__) . '/DatabaseTestCase.php';

use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Service\Engine;
use Tests\FindReplace\DatabaseTestCase;

class ReplacerTest extends DatabaseTestCase
{
    const TABLE_NAME = 'fr_replacer';

    const ID_NAME = 'id';

    const COLUMN_NAME = 'text';

    const TERM = 'This is a '
        . Engine::OPERATOR_CONTROL_CHARACTER
        . '"_test'
        . Engine::OPERATOR_CONTROL_CHARACTER
        . '\" %query';

    const NOT_MATCH_ID = '1';

    const MATCH_ID = '2';

    const MATCH_CONTENT = 'abcdefghijklmnopqrstuvwxyz'
        . self::TERM
        . '"abcdefghijklmnopqrstuvwxyz'
        . self::TERM;

    const REPLACEMENT_TERM = 'success';

    const REPLACEMENT_CONTENT = 'abcdefghijklmnopqrstuvwxyz'
        . self::REPLACEMENT_TERM
        . '"abcdefghijklmnopqrstuvwxyz'
        . self::REPLACEMENT_TERM;

    public function setUp(): void
    {
        parent::setUp();

        $this->createTable(
            self::TABLE_NAME,
            '`' . self::ID_NAME . '` TINYINT(3) NOT NULL AUTO_INCREMENT,
            `' . self::COLUMN_NAME . '` VARCHAR(1000) DEFAULT NULL,
            PRIMARY KEY (`id`)'
        );

        $this->insert(self::TABLE_NAME, [
            self::ID_NAME => self::NOT_MATCH_ID,
            self::COLUMN_NAME => 'abcdefghijklmnopqrstuvwxyz',
        ]);
        $this->insert(self::TABLE_NAME, [
            self::ID_NAME => self::MATCH_ID,
            self::COLUMN_NAME => self::MATCH_CONTENT,
        ]);
    }

    public function test_replacement_is_applied(): void
    {
        $hits = $this->engine()->search(new Query(self::TERM, tables: [self::TABLE_NAME]));
        $outcomes = $this->engine()->replace($hits, self::REPLACEMENT_TERM);

        $this->assertCount(1, $outcomes);
        $this->assertSame(Outcome::APPLIED, $outcomes[0]->getStatus());
        $this->assertSame(self::REPLACEMENT_CONTENT, $this->cell(self::TABLE_NAME, self::COLUMN_NAME, self::MATCH_ID));
        $this->assertSame(
            'abcdefghijklmnopqrstuvwxyz',
            $this->cell(self::TABLE_NAME, self::COLUMN_NAME, self::NOT_MATCH_ID)
        );
    }
}
