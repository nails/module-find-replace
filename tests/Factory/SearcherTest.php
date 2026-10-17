<?php

namespace Tests\FindReplace\Factory;

require_once dirname(__DIR__) . '/DatabaseTestCase.php';

use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Term\Variant\Plaintext;
use Nails\FindReplace\Service\Engine;
use Tests\FindReplace\DatabaseTestCase;

class SearcherTest extends DatabaseTestCase
{
    const TABLE_NAME = 'fr_searcher';

    const ID_NAME = 'id';

    const COLUMN_NAME = 'text';

    const TERM = 'This is a '
        . Engine::OPERATOR_CONTROL_CHARACTER
        . '"_test'
        . Engine::OPERATOR_CONTROL_CHARACTER
        . '\" %query';

    const NOT_MATCH_ID = 1;

    const MATCH_ID = 2;

    const MATCH_CONTENT = 'abcdefghijklmnopqrstuvwxyz'
        . self::TERM
        . '"abcdefghijklmnopqrstuvwxyz'
        . self::TERM;

    public function setUp(): void
    {
        parent::setUp();

        $this->createTable(
            self::TABLE_NAME,
            '`' . self::ID_NAME . '` TINYINT(3) NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(1000) DEFAULT NULL,
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

    public function test_term_is_found(): void
    {
        $this->assertCount(1, $this->hits());
    }

    public function test_term_matches_has_metadata(): void
    {
        $hits = $this->hits();
        $hit = $hits[0];
        $match = $hit->getMatched();

        $this->assertNotNull($match);
        $this->assertEquals(self::TABLE_NAME, $match->getTable());
        $this->assertEquals(self::COLUMN_NAME, $match->getColumn());
        $this->assertEquals(self::MATCH_ID, $match->getId());
        $this->assertEquals(Plaintext::ENCODING, $match->getEncoding());
        $this->assertEquals(self::TERM, $match->getTerm());
        $this->assertEquals(self::MATCH_CONTENT, $match->getContent());
        $this->assertSame(
            self::TABLE_NAME . '.' . self::COLUMN_NAME . ' #' . self::MATCH_ID,
            $hit->getTitle()
        );
        $this->assertSame('Unresolved', $hit->getFacets()['resolver'] ?? null);
        $this->assertFalse($hit->isReadOnly());
    }

    /**
     * @return list<\Nails\FindReplace\Factory\Hit>
     */
    private function hits(): array
    {
        return $this->engine()->search(new Query(self::TERM, tables: [self::TABLE_NAME]));
    }
}
