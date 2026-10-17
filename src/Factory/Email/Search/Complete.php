<?php

namespace Nails\FindReplace\Factory\Email\Search;

use Nails\Email\Interfaces;
use Nails\Email\Traits;

class Complete implements Interfaces\Email
{
    use Traits\Email;

    public function __construct()
    {
        $this->type('find_replace_search_complete');
    }

    /**
     * @return array<string, mixed>
     */
    public function getTestData(): array
    {
        return [
            'term' => 'alpha',
            'result_count' => 3,
            'results_url' => 'https://www.example.com/admin',
        ];
    }
}
