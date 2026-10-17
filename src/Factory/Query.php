<?php

namespace Nails\FindReplace\Factory;

class Query
{
    /**
     * @param list<string> $tables
     * @param list<string> $excludeTables
     */
    public function __construct(
        private readonly string $term,
        private readonly bool $caseSensitive = true,
        private readonly array $tables = [],
        private readonly array $excludeTables = [],
        private readonly ?int $limit = null,
        private readonly int $contextLength = 80,
    ) {
    }

    public function getTerm(): string
    {
        return $this->term;
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
}
