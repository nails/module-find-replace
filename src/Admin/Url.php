<?php

namespace Nails\FindReplace\Admin;

/**
 * Admin URLs for the Find & Replace screens.
 *
 * The admin controller lives in admin/controllers and is only loaded by the admin
 * router, so the queue task, the API, and the views build links from here.
 */
class Url
{
    const BASE = 'admin/findReplace/findReplace';

    public static function to(string $action = ''): string
    {
        return siteUrl(self::BASE . ($action !== '' ? '/' . $action : ''));
    }
}
