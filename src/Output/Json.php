<?php

namespace Nails\FindReplace\Output;

use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Term;

class Json
{
    const VERSION = 1;

    /**
     * @param list<Hit> $hits
     */
    public function write(
        string $path,
        string $term,
        bool $caseSensitive,
        int $contextLength,
        array $hits,
    ): void {
        $encoded = json_encode(
            [
                'version' => self::VERSION,
                'term' => $term,
                'case_sensitive' => $caseSensitive,
                'context_length' => $contextLength,
                'hits' => array_map($this->fromHit(...), $hits),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->put($path, $encoded . "\n");
    }

    public function read(string $path): Document
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Results file is not readable: ' . $path);
        }

        $raw = file_get_contents($path);

        if (!is_string($raw)) {
            throw new \InvalidArgumentException('Results file is not readable: ' . $path);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Results file is not valid JSON: ' . $exception->getMessage());
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Results file must be a JSON object');
        }

        $version = $decoded['version'] ?? null;

        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException('Results file version is not supported');
        }

        $term = $decoded['term'] ?? null;
        $caseSensitive = $decoded['case_sensitive'] ?? null;
        $contextLength = $decoded['context_length'] ?? null;
        $hits = $decoded['hits'] ?? null;

        if (!is_string($term) || !is_bool($caseSensitive) || !is_int($contextLength) || $contextLength < 0) {
            throw new \InvalidArgumentException('Results file is missing term, case_sensitive, or context_length');
        }

        if (!is_array($hits) || !array_is_list($hits)) {
            throw new \InvalidArgumentException('Results file hits must be a list');
        }

        $normalised = [];

        foreach ($hits as $index => $hit) {
            $normalised[] = $this->normaliseHit($hit, $index);
        }

        return new Document($term, $caseSensitive, $contextLength, $normalised);
    }

    /**
     * @return array{
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
     *     read_only: bool,
     *     read_only_reason: string|null,
     *     actions: list<array{label: string, url: string, css_class: string|null, target: string|null}>
     * }
     */
    private function fromHit(Hit $hit): array
    {
        $matched = $hit->getMatched();

        if ($matched === null) {
            throw new \InvalidArgumentException('A hit is missing its match');
        }

        $actions = [];

        foreach ($hit->getActions() as $action) {
            $actions[] = [
                'label' => $action->getLabel(),
                'url' => $action->getUrl(),
                'css_class' => $action->getCssClass(),
                'target' => $action->getTarget(),
            ];
        }

        return [
            'table' => $hit->getTable(),
            'primary_key' => $hit->getId(),
            'column' => $matched->getColumn(),
            'encoding' => $matched->getEncoding(),
            'term' => $matched->getTerm(),
            'content' => $matched->getContent(),
            'title' => $hit->getTitle(),
            'entity_label' => $hit->getEntityLabel(),
            'entity_id' => $hit->getEntityId(),
            'facets' => $hit->getFacets(),
            'read_only' => $hit->isReadOnly(),
            'read_only_reason' => $hit->getReadOnlyReason(),
            'actions' => $actions,
        ];
    }

    /**
     * @return array{
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
     * }
     */
    private function normaliseHit(mixed $hit, int $index): array
    {
        if (!is_array($hit)) {
            throw new \InvalidArgumentException('hits[' . $index . '] must be an object');
        }

        $encoding = $this->requiredString($hit, 'encoding', $index);
        $known = array_keys((new Term(''))->getVariants());

        if (!in_array($encoding, $known, true)) {
            throw new \InvalidArgumentException('hits[' . $index . '].encoding is invalid');
        }

        return [
            'table' => $this->requiredString($hit, 'table', $index),
            'primary_key' => $this->requiredScalar($hit, 'primary_key', $index),
            'column' => $this->requiredString($hit, 'column', $index),
            'encoding' => $encoding,
            'term' => $this->requiredString($hit, 'term', $index, true),
            'content' => $this->optionalString($hit, 'content', $index),
            'title' => $this->nullableString($hit, 'title', $index),
            'entity_label' => $this->nullableString($hit, 'entity_label', $index),
            'entity_id' => $this->nullableScalar($hit, 'entity_id', $index),
            'facets' => $this->stringMap($hit, 'facets', $index),
            'actions' => $this->actions($hit, $index),
        ];
    }

    /**
     * @param array<mixed> $hit
     */
    private function requiredString(array $hit, string $key, int $index, bool $allowEmpty = false): string
    {
        $value = $hit[$key] ?? null;

        if (!is_string($value) || (!$allowEmpty && $value === '')) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        return $value;
    }

    /**
     * @param array<mixed> $hit
     */
    private function requiredScalar(array $hit, string $key, int $index): string
    {
        $value = $hit[$key] ?? null;

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        $value = (string) $value;

        if ($value === '') {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        return $value;
    }

    /**
     * @param array<mixed> $hit
     */
    private function optionalString(array $hit, string $key, int $index): string
    {
        if (!array_key_exists($key, $hit) || $hit[$key] === null) {
            return '';
        }

        if (!is_string($hit[$key])) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        return $hit[$key];
    }

    /**
     * @param array<mixed> $hit
     */
    private function nullableString(array $hit, string $key, int $index): ?string
    {
        if (!array_key_exists($key, $hit) || $hit[$key] === null) {
            return null;
        }

        if (!is_string($hit[$key])) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        return $hit[$key];
    }

    /**
     * @param array<mixed> $hit
     */
    private function nullableScalar(array $hit, string $key, int $index): ?string
    {
        if (!array_key_exists($key, $hit) || $hit[$key] === null) {
            return null;
        }

        $value = $hit[$key];

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be a string');
        }

        return (string) $value;
    }

    /**
     * @param array<mixed> $hit
     *
     * @return array<string, string>
     */
    private function stringMap(array $hit, string $key, int $index): array
    {
        if (!array_key_exists($key, $hit) || $hit[$key] === null) {
            return [];
        }

        if (!is_array($hit[$key])) {
            throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' must be an object');
        }

        $map = [];

        foreach ($hit[$key] as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value)) {
                throw new \InvalidArgumentException('hits[' . $index . '].' . $key . ' values must be strings');
            }

            $map[$name] = $value;
        }

        return $map;
    }

    /**
     * @param array<mixed> $hit
     *
     * @return list<array{label: string, url: string, css_class: string|null, target: string|null}>
     */
    private function actions(array $hit, int $index): array
    {
        if (!array_key_exists('actions', $hit) || $hit['actions'] === null) {
            return [];
        }

        if (!is_array($hit['actions']) || !array_is_list($hit['actions'])) {
            throw new \InvalidArgumentException('hits[' . $index . '].actions must be a list');
        }

        $actions = [];

        foreach ($hit['actions'] as $actionIndex => $action) {
            if (!is_array($action)) {
                throw new \InvalidArgumentException(
                    'hits[' . $index . '].actions[' . $actionIndex . '] must be an object',
                );
            }

            $label = $action['label'] ?? null;
            $url = $action['url'] ?? null;

            if (!is_string($label) || $label === '' || !is_string($url) || $url === '') {
                throw new \InvalidArgumentException(
                    'hits[' . $index . '].actions[' . $actionIndex . '] needs a label and url',
                );
            }

            $actions[] = [
                'label' => $label,
                'url' => $url,
                'css_class' => $this->nullableString($action, 'css_class', $index),
                'target' => $this->nullableString($action, 'target', $index),
            ];
        }

        return $actions;
    }

    private function put(string $path, string $contents): void
    {
        $directory = dirname($path);

        if ($directory !== '' && !is_dir($directory)) {
            throw new \RuntimeException('Output directory does not exist: ' . $directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write ' . $path);
        }
    }
}
