<?php

/**
 * Migration: 0
 * Started:   2026-10-16
 */

namespace Nails\FindReplace\Database\Migration;

use Nails\Common\Interfaces;
use Nails\Common\Traits;

/**
 * Audit rows for applied replacements. The package hides this table from search.
 */
class Migration0 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    public function execute(): void
    {
        $this->query(
            <<<'EOT'
            CREATE TABLE `{{NAILS_DB_PREFIX}}find_replace_change` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_id` CHAR(36) NOT NULL,
                `search_id` INT UNSIGNED DEFAULT NULL,
                `user_id` INT UNSIGNED DEFAULT NULL,
                `source` ENUM('ADMIN','CLI') NOT NULL,
                `table_name` VARCHAR(64) NOT NULL,
                `primary_key` VARCHAR(255) NOT NULL,
                `column_name` VARCHAR(64) NOT NULL,
                `encoding` VARCHAR(32) NOT NULL,
                `term` TEXT NOT NULL,
                `replacement` TEXT NOT NULL,
                `value_before` LONGTEXT NOT NULL,
                `value_after` LONGTEXT NOT NULL,
                `created` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `run_id` (`run_id`),
                KEY `created` (`created`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            EOT
        );
    }
}
