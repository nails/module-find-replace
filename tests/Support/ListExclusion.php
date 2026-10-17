<?php

namespace Tests\FindReplace\Support;

use Nails\FindReplace\Interfaces\Exclusion;

class ListExclusion implements Exclusion
{
    /**
     * @param list<string> $hidden
     * @param list<string> $readOnly
     */
    public function __construct(
        private array $hidden = [],
        private array $readOnly = [],
    ) {
    }

    public function hidden(): array
    {
        return $this->hidden;
    }

    public function readOnly(): array
    {
        return $this->readOnly;
    }

    /**
     * @param list<string> $hidden
     */
    public function setHidden(array $hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * @param list<string> $readOnly
     */
    public function setReadOnly(array $readOnly): void
    {
        $this->readOnly = $readOnly;
    }
}
