<?php

namespace Tests\FindReplace\Support;

use Nails\FindReplace\Interfaces\Resolver;
use Nails\FindReplace\Interfaces\Resolver\FacetValues;
use Nails\FindReplace\Interfaces\Resolver\Facets;

class StatusResolver implements Resolver, Facets, FacetValues
{
    /**
     * @param array<string, string> $facets
     * @param array<string, list<string>> $values
     * @param list<string> $tables
     */
    public function __construct(
        private readonly array $facets = [
            'status' => 'Status',
            'language' => 'Language',
        ],
        private readonly array $values = [
            'status' => ['PUBLISHED', 'DRAFT'],
        ],
        private readonly array $tables = ['app_article'],
    ) {
    }

    public function tables(): array
    {
        return $this->tables;
    }

    public function resolve(string $table, array $matches): array
    {
        return [];
    }

    public function facets(): array
    {
        return $this->facets;
    }

    public function values(): array
    {
        return $this->values;
    }
}
