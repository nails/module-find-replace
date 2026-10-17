<?php

namespace Nails\FindReplace\Interfaces\Resolver;

interface Facets
{
    /**
     * Facet keys and labels this resolver supplies.
     *
     * @return array<string, string>
     */
    public function facets(): array;
}
