<?php

namespace Nails\FindReplace;

/**
 * Filters results by whether find-replace can change them.
 */
class Editability
{
    const ANY = '';

    const REPLACEABLE = 'replaceable';

    const READ_ONLY = 'read-only';

    const LABELS = [
        self::REPLACEABLE => 'Replaceable',
        self::READ_ONLY => 'Read-only',
    ];

    /**
     * The `read_only` flag to filter on. Null keeps both.
     *
     * @throws \InvalidArgumentException When the value is not a known option
     */
    public static function readOnly(string $value): ?bool
    {
        return match ($value) {
            self::ANY => null,
            self::REPLACEABLE => false,
            self::READ_ONLY => true,
            default => throw new \InvalidArgumentException('Unknown field filter "' . $value . '".'),
        };
    }
}
