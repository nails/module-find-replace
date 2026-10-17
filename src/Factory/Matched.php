<?php

namespace Nails\FindReplace\Factory;

class Matched
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly string $encoding,
        private readonly string $term,
        private readonly string $id,
        private readonly string $content,
        private readonly ?Location $location = null,
        private readonly bool $serialised = false,
        private readonly int $contextLength = 80,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
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

    public function getId(): string
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function isSerialised(): bool
    {
        return $this->serialised;
    }

    public function getContextLength(): int
    {
        return $this->contextLength;
    }

    /**
     * @param callable(string): string|null $stringFunction
     */
    public function getContentWithFixes(
        string $prefix,
        string $suffix,
        bool $caseSensitive = true,
        ?callable $stringFunction = null,
    ): string {
        return $this->highlight(
            $this->getTerm(),
            $this->getContent(),
            $prefix,
            $suffix,
            $caseSensitive,
            $stringFunction,
            false,
        );
    }

    /**
     * Long CMS fields bury a single match. Keep a window around each hit so
     * the preview shows the matched text instead of the whole column.
     */
    protected function windowAroundNeedle(string $haystack, string $needle, bool $caseSensitive): string
    {
        $context = $this->contextLength;

        if ($needle === '' || mb_strlen($haystack) <= ($context * 2)) {
            return $haystack;
        }

        $needleLength = mb_strlen($needle);
        $haystackLength = mb_strlen($haystack);
        $from = 0;
        $ranges = [];

        while (true) {
            $position = $caseSensitive
                ? mb_strpos($haystack, $needle, $from)
                : mb_stripos($haystack, $needle, $from);

            if ($position === false) {
                break;
            }

            $ranges[] = [
                max(0, $position - $context),
                min($haystackLength, $position + $needleLength + $context),
            ];
            $from = $position + max(1, $needleLength);
        }

        if ($ranges === []) {
            return $haystack;
        }

        $merged = [];

        foreach ($ranges as $range) {
            $last = count($merged) - 1;

            if ($last >= 0 && $range[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);
                continue;
            }

            $merged[] = $range;
        }

        $parts = [];

        foreach ($merged as $range) {
            $parts[] = mb_substr($haystack, $range[0], $range[1] - $range[0]);
        }

        return implode(' … ', $parts);
    }

    /**
     * @param callable(string): string|null $stringFunction
     */
    protected function highlight(
        string $needle,
        string $haystack,
        string $prefix,
        string $suffix,
        bool $caseSensitive,
        ?callable $stringFunction,
        bool $insertReplacement,
    ): string {
        $apply = $stringFunction ?? static fn(string $string): string => $string;
        $haystack = $this->windowAroundNeedle($haystack, $needle, $caseSensitive);

        if ($needle === '') {
            return $apply($haystack);
        }

        $needleLength = mb_strlen($needle);
        $return = '';

        while (true) {
            $position = $caseSensitive
                ? mb_strpos($haystack, $needle)
                : mb_stripos($haystack, $needle);

            if ($position === false) {
                break;
            }

            $matched = $insertReplacement
                ? $this->replacementLabel()
                : mb_substr($haystack, $position, $needleLength);

            $return .= $apply(mb_substr($haystack, 0, $position))
                . $prefix
                . $apply($matched)
                . $suffix;

            $haystack = mb_substr($haystack, $position + $needleLength);
        }

        return $return . $apply($haystack);
    }

    protected function replacementLabel(): string
    {
        return '';
    }
}
