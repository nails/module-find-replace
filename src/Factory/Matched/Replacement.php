<?php

namespace Nails\FindReplace\Factory\Matched;

use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\Term;

class Replacement extends Matched implements \JsonSerializable
{
    private string $replacementTerm;

    private string $replacementContent;

    public function __construct(Matched $match, Term $term)
    {
        parent::__construct(
            $match->getTable(),
            $match->getColumn(),
            $match->getEncoding(),
            $match->getTerm(),
            $match->getId(),
            $match->getContent(),
            $match->getLocation(),
            $match->isSerialised(),
            $match->getContextLength(),
        );

        $this->replacementTerm = $term->getVariant($match->getEncoding())->getTerm();
        $this->replacementContent = str_replace(
            $this->getTerm(),
            $this->replacementTerm,
            $this->getContent(),
        );
    }

    public function __toString(): string
    {
        $encoded = json_encode($this);

        return is_string($encoded) ? $encoded : '';
    }

    public function jsonSerialize(): mixed
    {
        return (object) [
            'sTable' => $this->getTable(),
            'sColumn' => $this->getColumn(),
            'sEncoding' => $this->getEncoding(),
            'sTerm' => $this->getTerm(),
            'sId' => $this->getId(),
            'sReplacementTerm' => $this->getReplacementTerm(),
        ];
    }

    public function getReplacementTerm(): string
    {
        return $this->replacementTerm;
    }

    public function getReplacementContent(): string
    {
        return $this->replacementContent;
    }

    /**
     * Returns the replacement content, with the prefix and suffix wrapped around
     * the substrings which were actually replaced.
     *
     * The content is rebuilt by splitting the original content on the search
     * term, rather than by searching the replaced content for the replacement
     * term; the latter also decorates pre-existing occurrences of the
     * replacement term, implying replacements which never happened.
     *
     * @param callable(string): string|null $stringFunction
     */
    public function getReplacementContentWithFixes(
        string $prefix,
        string $suffix,
        bool $caseSensitive = true,
        ?callable $stringFunction = null,
    ): string {
        if (empty($this->getReplacementTerm())) {
            return '';
        }

        if ($this->getTerm() === '') {
            $apply = $stringFunction ?? static fn(string $string): string => $string;

            return $apply($this->getReplacementContent());
        }

        return $this->highlight(
            $this->getTerm(),
            $this->getContent(),
            $prefix,
            $suffix,
            $caseSensitive,
            $stringFunction,
            true,
        );
    }

    protected function replacementLabel(): string
    {
        return $this->replacementTerm;
    }
}
