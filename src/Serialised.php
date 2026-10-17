<?php

namespace Nails\FindReplace;

class Serialised
{
    /**
     * A cell is PHP-serialised when it starts with a serialised marker and
     * unserialize() accepts it. `b:0;` is included on its own: unserialize()
     * returns false for that value, which is also what a failed parse returns.
     */
    public static function is(string $value): bool
    {
        if ($value === 'b:0;') {
            return true;
        }

        if (preg_match('/^(?:a:|O:|s:|i:|b:|d:|N;)/', $value) !== 1) {
            return false;
        }

        set_error_handler(static fn(int $severity, string $message): bool => true);
        $decoded = unserialize($value, ['allowed_classes' => false]);
        restore_error_handler();

        return $decoded !== false;
    }
}
