<?php

namespace Nails\FindReplace\Resolver;

use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Interfaces\Resolver;

class TableColumn implements Resolver
{
    public function tables(): array
    {
        return [];
    }

    public function resolve(string $table, array $matches): array
    {
        $hits = [];

        foreach ($matches as $match) {
            $id = $match->getId();

            if (isset($hits[$id])) {
                continue;
            }

            $hits[$id] = new Hit(
                $table,
                $id,
                title: $table . '.' . $match->getColumn() . ' #' . $id,
            );
        }

        return $hits;
    }
}
