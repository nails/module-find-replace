<?php

namespace Tests\FindReplace\Support;

use Nails\FindReplace\Interfaces\Resolver;
use Nails\FindReplace\Interfaces\Resolver\Badges;

class BadgeResolver implements Resolver, Badges
{
    /**
     * @param list<string> $badges
     */
    public function __construct(
        private readonly array $badges = [],
    ) {
    }

    public function tables(): array
    {
        return [];
    }

    public function resolve(string $table, array $matches): array
    {
        return [];
    }

    public function badges(): array
    {
        return $this->badges;
    }
}
