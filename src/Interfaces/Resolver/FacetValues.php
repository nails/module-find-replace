<?php

namespace Nails\FindReplace\Interfaces\Resolver;

interface FacetValues
{
    /**
     * Closed values for facets this resolver declares.
     * Omit a key when that facet accepts any string.
     *
     * @return array<string, list<string>>
     */
    public function values(): array;
}
