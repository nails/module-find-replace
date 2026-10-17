<?php

/**
 * Migration: 1
 * Started:   2026-10-16
 */

namespace Nails\FindReplace\Database\Migration;

use Nails\Common\Interfaces;
use Nails\Common\Traits;

/**
 * Stored admin searches. A search, its results, and their facets are one group:
 * deleting the search removes the rows.
 */
class Migration1 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    public function execute(): void
    {
        $this->query(
            <<<'EOT'
            CREATE TABLE `{{NAILS_DB_PREFIX}}find_replace_search` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `term` TEXT NOT NULL,
                `replacement` TEXT NOT NULL,
                `case_sensitive` TINYINT(1) NOT NULL DEFAULT 1,
                `include_tables` TEXT NOT NULL,
                `exclude_tables` TEXT NOT NULL,
                `context_length` INT UNSIGNED NOT NULL DEFAULT 80,
                `status` ENUM('PENDING','RUNNING','COMPLETE','FAILED') NOT NULL DEFAULT 'PENDING',
                `tables_scanned` INT UNSIGNED NOT NULL DEFAULT 0,
                `tables_total` INT UNSIGNED NOT NULL DEFAULT 0,
                `result_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `persistent` TINYINT(1) NOT NULL DEFAULT 0,
                `error` TEXT DEFAULT NULL,
                `last_run` DATETIME NOT NULL,
                `created` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `persistent_last_run` (`persistent`, `last_run`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            EOT
        );
        $this->query(
            <<<'EOT'
            CREATE TABLE `{{NAILS_DB_PREFIX}}find_replace_result` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `search_id` INT UNSIGNED NOT NULL,
                `table_name` VARCHAR(64) NOT NULL,
                `column_name` VARCHAR(64) NOT NULL,
                `primary_key` VARCHAR(255) NOT NULL,
                `encoding` VARCHAR(32) NOT NULL,
                `term` TEXT NOT NULL,
                `snippet` MEDIUMTEXT NOT NULL,
                `title` TEXT DEFAULT NULL,
                `entity_label` VARCHAR(255) DEFAULT NULL,
                `entity_id` VARCHAR(255) DEFAULT NULL,
                `read_only` TINYINT(1) NOT NULL DEFAULT 0,
                `read_only_reason` VARCHAR(255) DEFAULT NULL,
                `actions` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                KEY `search_id` (`search_id`),
                CONSTRAINT `fk_find_replace_result_search`
                    FOREIGN KEY (`search_id`)
                    REFERENCES `{{NAILS_DB_PREFIX}}find_replace_search` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            EOT
        );
        $this->query(
            <<<'EOT'
            CREATE TABLE `{{NAILS_DB_PREFIX}}find_replace_result_facet` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `result_id` INT UNSIGNED NOT NULL,
                `facet` VARCHAR(64) NOT NULL,
                `value` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `result_facet_value` (`result_id`, `facet`, `value`),
                KEY `facet_value` (`facet`, `value`),
                CONSTRAINT `fk_find_replace_facet_result`
                    FOREIGN KEY (`result_id`)
                    REFERENCES `{{NAILS_DB_PREFIX}}find_replace_result` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            EOT
        );
    }
}
