<?php

namespace Nails\FindReplace\Exclusion;

use Nails\FindReplace\Interfaces\Exclusion;

class Package implements Exclusion
{
    public function hidden(): array
    {
        return [
            'nails_find_replace_search',
            'nails_find_replace_result',
            'nails_find_replace_result_facet',
            'nails_find_replace_change',
            '/\.(token|ref)$/',
        ];
    }

    public function readOnly(): array
    {
        return [
            '/\.slug$/',
        ];
    }
}
