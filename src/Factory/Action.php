<?php

namespace Nails\FindReplace\Factory;

class Action
{
    public function __construct(
        private readonly string $label,
        private readonly string $url,
        private readonly ?string $cssClass = null,
        private readonly ?string $target = null,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }
}
