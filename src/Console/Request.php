<?php

namespace Nails\FindReplace\Console;

class Request
{
    /**
     * @param list<string> $tables
     * @param list<string> $excludeTables
     * @param list<array{0: string, 1: string}> $facets
     */
    public function __construct(
        private readonly bool $caseSensitive,
        private readonly array $tables,
        private readonly array $excludeTables,
        private readonly ?int $limit,
        private readonly int $contextLength,
        private readonly bool $resolvedOnly,
        private readonly array $facets,
    ) {
    }

    public function isCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }

    /**
     * @return list<string>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return list<string>
     */
    public function getExcludeTables(): array
    {
        return $this->excludeTables;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getContextLength(): int
    {
        return $this->contextLength;
    }

    public function isResolvedOnly(): bool
    {
        return $this->resolvedOnly;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function getFacets(): array
    {
        return $this->facets;
    }
}
