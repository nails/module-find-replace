<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/DatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Database\Migration\Migration0;
use Nails\FindReplace\Database\Migration\Migration1;
use Nails\FindReplace\Database\Migration\Migration2;

abstract class AdminDatabaseTestCase extends DatabaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->pdo()->query('SET FOREIGN_KEY_CHECKS=0');
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_result_facet`');
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_result`');
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_search`');
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_change`');
        $this->pdo()->query('SET FOREIGN_KEY_CHECKS=1');
        (new Migration0($this->pdo()))->execute();
        (new Migration1($this->pdo()))->execute();
        (new Migration2($this->pdo()))->execute();
        $this->registerTable('nails_find_replace_result_facet');
        $this->registerTable('nails_find_replace_result');
        $this->registerTable('nails_find_replace_search');
        $this->registerTable('nails_find_replace_change');
    }

    public function tearDown(): void
    {
        Factory::destroyService('Access', Constants::MODULE_SLUG);
        Factory::destroyService('Engine', Constants::MODULE_SLUG);
        Factory::destroyService('Schema', Constants::MODULE_SLUG);
        Factory::destroyModel('Search', Constants::MODULE_SLUG);
        Factory::destroyModel('Result', Constants::MODULE_SLUG);
        Factory::destroyModel('ResultFacet', Constants::MODULE_SLUG);
        Factory::destroyModel('Change', Constants::MODULE_SLUG);
        parent::tearDown();
    }
}
