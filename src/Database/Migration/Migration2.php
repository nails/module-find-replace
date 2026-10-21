<?php

/**
 * Migration: 2
 * Started:   2026-10-21
 */

namespace Nails\FindReplace\Database\Migration;

use Nails\Common\Interfaces;
use Nails\Common\Traits;

/**
 * An applied result stays stored and leaves the outstanding list.
 * Revert clears the flag when the cell contains the term again.
 */
class Migration2 implements Interfaces\Database\Migration
{
    use Traits\Database\Migration;

    public function execute(): void
    {
        $this->query(
            <<<'EOT'
            ALTER TABLE `{{NAILS_DB_PREFIX}}find_replace_result`
                ADD `applied` TINYINT(1) NOT NULL DEFAULT 0 AFTER `actions`;
            EOT
        );
    }
}
