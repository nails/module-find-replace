<?php

namespace Nails\FindReplace\Output;

use Nails\FindReplace\Factory\Action;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Reason;
use Nails\FindReplace\Serialised;
use Nails\FindReplace\Service\Schema;

class Document
{
    /**
     * @param list<array{
     *     table: string,
     *     primary_key: string,
     *     column: string,
     *     encoding: string,
     *     term: string,
     *     content: string,
     *     title: string|null,
     *     entity_label: string|null,
     *     entity_id: string|null,
     *     facets: array<string, string>,
     *     actions: list<array{label: string, url: string, css_class: string|null, target: string|null}>
     * }> $hits
     */
    public function __construct(
        private readonly string $term,
        private readonly bool $caseSensitive,
        private readonly int $contextLength,
        private readonly array $hits,
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

    public function getContextLength(): int
    {
        return $this->contextLength;
    }

    /**
     * Read-only state is taken from the current schema, not the file.
     *
     * @return list<Hit>
     */
    public function toHits(Schema $schema): array
    {
        $hits = [];

        foreach ($this->hits as $row) {
            $hits[] = $this->hit($schema, $row);
        }

        return $hits;
    }

    /**
     * @param array{
     *     table: string,
     *     primary_key: string,
     *     column: string,
     *     encoding: string,
     *     term: string,
     *     content: string,
     *     title: string|null,
     *     entity_label: string|null,
     *     entity_id: string|null,
     *     facets: array<string, string>,
     *     actions: list<array{label: string, url: string, css_class: string|null, target: string|null}>
     * } $row
     */
    private function hit(Schema $schema, array $row): Hit
    {
        $location = $schema->location($row['table'], $row['column']);
        $serialised = Serialised::is($row['content']);
        $readOnly = false;
        $reason = null;

        if ($location === null) {
            $readOnly = true;
            $reason = Reason::EXCLUDED;
        } elseif ($serialised) {
            $readOnly = true;
            $reason = Reason::SERIALISED;
        } elseif ($location->isReadOnly()) {
            $readOnly = true;
            $reason = $location->getReadOnlyReason() ?? Reason::READ_ONLY;
        }

        $actions = [];

        foreach ($row['actions'] as $action) {
            $actions[] = new Action(
                $action['label'],
                $action['url'],
                $action['css_class'],
                $action['target'],
            );
        }

        $title = $row['title'] ?? ($row['table'] . '.' . $row['column'] . ' #' . $row['primary_key']);
        $matched = new Matched(
            $row['table'],
            $row['column'],
            $row['encoding'],
            $row['term'],
            $row['primary_key'],
            $row['content'],
            $location,
            $serialised,
            $this->contextLength,
        );

        return (new Hit(
            $row['table'],
            $row['primary_key'],
            $row['entity_label'],
            $row['entity_id'],
            $title,
            $actions,
            $row['facets'],
        ))->withMatch($matched, $readOnly, $reason);
    }
}
