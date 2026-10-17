<?php

namespace Nails\FindReplace\Admin\Permission;

use Nails\Admin\Interfaces\Permission;

class Replace implements Permission
{
    public function label(): string
    {
        return 'Can apply find-replace replacements and revert your own changes';
    }

    public function group(): string
    {
        return 'Find & Replace';
    }
}
