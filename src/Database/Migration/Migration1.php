<?php

/**
 * Migration: 1
 * Started:   2026-10-30
 */

namespace Nails\FindReplace\Database\Migration;

use Nails\Admin\Traits\Database\Migration\PermissionMap;
use Nails\Common\Interfaces;
use Nails\Common\Traits;
use Nails\FindReplace\Admin\Permission;

/**
 * Moves user groups from the `feature/pre-new-admin` permission strings to the
 * permission classes.
 *
 * Repeatable because `feature/pre-new-admin` has no equivalent migration, so an app
 * arriving from that branch resumes above this number and would never run it.
 */
class Migration1 implements Interfaces\Database\Migration\Repeatable
{
    use Traits\Database\Migration;
    use PermissionMap;

    const MAP = [
        'admin:findreplace:findreplace:search' => Permission\Search::class,
        'admin:findreplace:findreplace:replace' => Permission\Replace::class,
    ];
}
