<?php

namespace Nails\FindReplace\Admin;

/**
 * The admin permission strings. The keys are what FindReplace::permissions() offers
 * on a user group; the full strings are what userHasPermission() checks.
 */
class Permission
{
    const SEARCH = 'admin:findreplace:findreplace:search';
    const REPLACE = 'admin:findreplace:findreplace:replace';
}
