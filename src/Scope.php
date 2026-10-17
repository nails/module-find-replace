<?php

namespace Nails\FindReplace;

use Nails\FindReplace\Exclusion\Pattern;

class Scope
{
    /**
     * An empty include list matches every column. Excludes are applied after includes.
     *
     * @param list<string> $tables
     * @param list<string> $excludeTables
     */
    public static function includes(string $table, string $column, array $tables, array $excludeTables): bool
    {
        $included = $tables === [];

        foreach ($tables as $entry) {
            if (Pattern::covers($entry, $table, $column)) {
                $included = true;
                break;
            }
        }

        if (!$included) {
            return false;
        }

        foreach ($excludeTables as $entry) {
            if (Pattern::covers($entry, $table, $column)) {
                return false;
            }
        }

        return true;
    }
}
