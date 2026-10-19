<?php

namespace Nails\FindReplace\Factory;

class Hit
{
    /**
     * @param list<Action> $actions
     * @param array<string, string> $facets
     */
    public function __construct(
        private readonly string $table,
        private readonly string $id,
        private readonly ?string $entityLabel = null,
        private readonly ?string $entityId = null,
        private readonly ?string $title = null,
        private readonly array $actions = [],
        private readonly array $facets = [],
        private readonly ?Matched $matched = null,
        private readonly bool $readOnly = false,
        private readonly ?string $readOnlyReason = null,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return list<Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return array<string, string>
     */
    public function getFacets(): array
    {
        return $this->facets;
    }

    public function getMatched(): ?Matched
    {
        return $this->matched;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function getReadOnlyReason(): ?string
    {
        return $this->readOnlyReason;
    }

    public function withMatch(Matched $matched, bool $readOnly, ?string $readOnlyReason): self
    {
        $facets = $this->facets;
        $facets['resolver'] = $this->entityLabel ?? 'Unresolved';

        return new self(
            $this->table,
            $this->id,
            $this->entityLabel,
            $this->entityId,
            $this->title,
            $this->actions,
            $facets,
            $matched,
            $readOnly,
            $readOnlyReason,
        );
    }

    /**
     * @param array<string, string> $facets
     */
    public function withFacets(array $facets): self
    {
        return new self(
            $this->table,
            $this->id,
            $this->entityLabel,
            $this->entityId,
            $this->title,
            $this->actions,
            $facets,
            $this->matched,
            $this->readOnly,
            $this->readOnlyReason,
        );
    }
}
