<?php

namespace Nails\FindReplace\Factory\Email\Search;

use Nails\Email\Interfaces;
use Nails\Email\Traits;

class Fail implements Interfaces\Email
{
    use Traits\Email;

    public function __construct()
    {
        $this->type('find_replace_search_fail');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTestData(): array
    {
        return [
            'term' => 'alpha',
            'error' => 'The search could not be completed.',
        ];
    }
}
