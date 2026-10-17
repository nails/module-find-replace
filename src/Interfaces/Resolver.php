<?php

namespace Nails\FindReplace\Interfaces;

use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;

interface Resolver
{
    /**
     * Tables this resolver can describe (main or localised).
     *
     * @return list<string>
     */
    public function tables(): array;

    /**
     * Batch-resolve matches from one table into hits, keyed by primary key.
     * Missing keys fall back to the default table.column resolver.
     *
     * @param list<Matched> $matches
     *
     * @return array<string, Hit>
     */
    public function resolve(string $table, array $matches): array;
}
