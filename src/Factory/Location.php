<?php

namespace Nails\FindReplace\Factory;

class Location
{
    const SEARCHABLE = 'searchable';

    const READ_ONLY = 'read-only';

    const HIDDEN = 'hidden';

    public function __construct(
        private readonly string $table,
        private readonly ?string $primaryKey,
        private readonly string $column,
        private readonly string $access,
        private readonly ?string $readOnlyReason = null,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getAccess(): string
    {
        return $this->access;
    }

    public function isSearchable(): bool
    {
        return $this->access === self::SEARCHABLE;
    }

    public function isReadOnly(): bool
    {
        return $this->access === self::READ_ONLY;
    }

    public function getReadOnlyReason(): ?string
    {
        return $this->readOnlyReason;
    }
}
