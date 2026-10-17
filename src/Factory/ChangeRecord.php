<?php

namespace Nails\FindReplace\Factory;

class ChangeRecord
{
    public function __construct(
        private readonly int $id,
        private readonly string $runId,
        private readonly ?int $searchId,
        private readonly ?int $userId,
        private readonly string $source,
        private readonly string $table,
        private readonly string $primaryKey,
        private readonly string $column,
        private readonly string $encoding,
        private readonly string $term,
        private readonly string $replacement,
        private readonly string $valueBefore,
        private readonly string $valueAfter,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getSearchId(): ?int
    {
        return $this->searchId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function getValueBefore(): string
    {
        return $this->valueBefore;
    }

    public function getValueAfter(): string
    {
        return $this->valueAfter;
    }
}
