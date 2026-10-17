<?php

namespace Nails\FindReplace\Resource;

use Nails\Common\Resource\DateTime;
use Nails\Common\Resource\Entity;

class Search extends Entity
{
    public int|string|null $user_id = null;

    public ?string $term = null;

    public ?string $replacement = null;

    public bool|int|string|null $case_sensitive = null;

    public ?string $include_tables = null;

    public ?string $exclude_tables = null;

    public int|string|null $context_length = null;

    public ?string $status = null;

    public int|string|null $tables_scanned = null;

    public int|string|null $tables_total = null;

    public int|string|null $result_count = null;

    public bool|int|string|null $persistent = null;

    public ?string $error = null;

    public mixed $last_run = null;

    public function userId(): int
    {
        return $this->integer($this->user_id);
    }

    public function term(): string
    {
        return $this->term ?? '';
    }

    public function replacement(): string
    {
        return $this->replacement ?? '';
    }

    public function isCaseSensitive(): bool
    {
        return $this->flag($this->case_sensitive, true);
    }

    /**
     * @return list<string>
     */
    public function includeTables(): array
    {
        return $this->entries($this->include_tables);
    }

    /**
     * @return list<string>
     */
    public function excludeTables(): array
    {
        return $this->entries($this->exclude_tables);
    }

    public function contextLength(): int
    {
        $length = $this->integer($this->context_length);

        return $length > 0 ? $length : 80;
    }

    public function status(): string
    {
        return $this->status ?? '';
    }

    public function tablesScanned(): int
    {
        return $this->integer($this->tables_scanned);
    }

    public function tablesTotal(): int
    {
        return $this->integer($this->tables_total);
    }

    public function resultCount(): int
    {
        return $this->integer($this->result_count);
    }

    public function isPersistent(): bool
    {
        return $this->flag($this->persistent, false);
    }

    public function errorMessage(): string
    {
        return $this->error ?? '';
    }

    public function lastRunLabel(): string
    {
        $lastRun = $this->last_run;

        if ($lastRun instanceof DateTime) {
            return $lastRun->formatted;
        }

        return is_string($lastRun) ? $lastRun : '';
    }

    public function isOpen(): bool
    {
        return $this->status() === \Nails\FindReplace\Model\Search::STATUS_PENDING
            || $this->status() === \Nails\FindReplace\Model\Search::STATUS_RUNNING;
    }

    /**
     * @return list<string>
     */
    private function entries(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $entry) {
            if (is_string($entry) && $entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function flag(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return $value === '1';
        }

        return $default;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
