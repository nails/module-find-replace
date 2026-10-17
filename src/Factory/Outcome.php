<?php

namespace Nails\FindReplace\Factory;

class Outcome
{
    const APPLIED = 'applied';

    const SKIPPED = 'skipped';

    const FAILED = 'failed';

    public function __construct(
        private readonly string $status,
        private readonly ?Hit $hit = null,
        private readonly ?string $reason = null,
        private readonly ?string $valueBefore = null,
        private readonly ?string $valueAfter = null,
    ) {
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHit(): ?Hit
    {
        return $this->hit;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getValueBefore(): ?string
    {
        return $this->valueBefore;
    }

    public function getValueAfter(): ?string
    {
        return $this->valueAfter;
    }
}
