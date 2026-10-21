<?php

namespace Nails\FindReplace\Interfaces\Resolver;

interface Badges
{
    /**
     * Facet keys to show as badges on an admin result row, in this order.
     * A hit with no value for a key prints nothing.
     *
     * @return list<string>
     */
    public function badges(): array;
}
