<?php

use Nails\FindReplace\Constants;

$config['email_types'] = [
    (object) [
        'slug' => 'find_replace_search_complete',
        'name' => 'Find & Replace: Search complete',
        'can_unsubscribe' => false,
        'description' => 'Sent when a saved find-replace search finishes',
        'template_header' => '',
        'template_body' => 'find-replace/Email/templates/search/complete',
        'template_footer' => '',
        'default_subject' => 'Find and replace search complete',
        'factory' => Constants::MODULE_SLUG . '::EmailSearchComplete',
    ],
    (object) [
        'slug' => 'find_replace_search_fail',
        'name' => 'Find & Replace: Search failed',
        'can_unsubscribe' => false,
        'description' => 'Sent when a saved find-replace search fails',
        'template_header' => '',
        'template_body' => 'find-replace/Email/templates/search/fail',
        'template_footer' => '',
        'default_subject' => 'Find and replace search failed',
        'factory' => Constants::MODULE_SLUG . '::EmailSearchFail',
    ],
];
