<?php

namespace Nails\FindReplace\Admin\Permission;

use Nails\Admin\Interfaces\Permission;

class Search implements Permission
{
    public function label(): string
    {
        return 'Can search, view results, export CSV, and manage your own saved searches';
    }

    public function group(): string
    {
        return 'Find & Replace';
    }
}
