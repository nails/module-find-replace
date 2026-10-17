<?php

namespace Nails\FindReplace\Exclusion;

class Pattern
{
    /**
     * Throws when a `/pattern/flags` entry is not valid PCRE.
     * Literal table and column entries are not compiled.
     */
    public static function assertValid(string $entry): void
    {
        if (!self::isRegex($entry)) {
            return;
        }

        self::matches($entry, '');
    }

    /**
     * Match one exclusion entry against `table.column`.
     */
    public static function covers(string $entry, string $table, string $column): bool
    {
        $target = $table . '.' . $column;

        if (self::isRegex($entry)) {
            return self::matches($entry, $target) === 1;
        }

        return $entry === $table
            || $entry === $table . '.*'
            || $entry === $target;
    }

    private static function isRegex(string $entry): bool
    {
        return str_starts_with($entry, '/');
    }

    private static function matches(string $pattern, string $subject): int
    {
        set_error_handler(static fn(int $severity, string $message): bool => true);

        try {
            $result = preg_match($pattern, $subject);
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new \InvalidArgumentException(
                'Invalid find-replace exclusion regex: ' . $pattern
            );
        }

        return $result;
    }
}
