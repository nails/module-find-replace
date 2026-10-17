<?php

namespace Nails\FindReplace\Factory;

use Nails\FindReplace\Factory\Matched\Replacement;

class StoredResult
{
    public function __construct(
        private readonly int $id,
        private readonly Hit $hit,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHit(): Hit
    {
        return $this->hit;
    }

    public function snippet(bool $caseSensitive): string
    {
        $matched = $this->hit->getMatched();

        if ($matched === null) {
            return '';
        }

        return $matched->getContentWithFixes(
            '<mark>',
            '</mark>',
            $caseSensitive,
            'htmlspecialchars',
        );
    }

    public function replacementPreview(string $replacement, bool $caseSensitive): string
    {
        $matched = $this->hit->getMatched();

        if ($matched === null || $this->hit->isReadOnly() || $replacement === '') {
            return '';
        }

        try {
            $preview = new Replacement($matched, new Term($replacement));
        } catch (\DomainException) {
            return '';
        }

        return $preview->getReplacementContentWithFixes(
            '<mark>',
            '</mark>',
            $caseSensitive,
            'htmlspecialchars',
        );
    }
}
