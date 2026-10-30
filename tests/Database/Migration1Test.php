<?php

namespace Tests\FindReplace\Database;

require_once dirname(__DIR__) . '/DatabaseTestCase.php';

use Nails\Common\Interfaces\Database\Migration\Repeatable;
use Nails\FindReplace\Admin\Permission;
use Nails\FindReplace\Database\Migration\Migration1;
use Tests\FindReplace\DatabaseTestCase;

class Migration1Test extends DatabaseTestCase
{
    public function test_is_repeatable(): void
    {
        $this->assertInstanceOf(Repeatable::class, new Migration1($this->pdo()));
    }

    public function test_maps_pre_new_admin_strings_to_permission_classes(): void
    {
        $this->userGroups();
        $this->insert('nails_user_group', [
            'id' => 1,
            'acl' => json_encode([
                'admin:findreplace:findreplace:search',
                'admin:findreplace:findreplace:replace',
                'admin:cms:pages:browse',
            ]),
        ]);

        (new Migration1($this->pdo()))->execute();

        $this->assertSame(
            [Permission\Search::class, Permission\Replace::class, 'admin:cms:pages:browse'],
            json_decode((string) $this->cell('nails_user_group', 'acl', '1'), true),
        );
    }

    public function test_running_again_changes_nothing(): void
    {
        $this->userGroups();
        $this->insert('nails_user_group', [
            'id' => 1,
            'acl' => json_encode(['admin:findreplace:findreplace:search']),
        ]);

        (new Migration1($this->pdo()))->execute();
        (new Migration1($this->pdo()))->execute();

        $this->assertSame(
            [Permission\Search::class],
            json_decode((string) $this->cell('nails_user_group', 'acl', '1'), true),
        );
    }

    public function test_skips_when_there_are_no_user_groups(): void
    {
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_user_group`');

        (new Migration1($this->pdo()))->execute();

        $this->addToAssertionCount(1);
    }

    private function userGroups(): void
    {
        $this->createTable('nails_user_group', '
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `acl` TEXT NULL,
            PRIMARY KEY (`id`)
        ');
    }
}
