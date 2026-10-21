<?php

use Nails\Testing;

require 'vendor/autoload.php';

$cache = dirname(__DIR__) . '/cache';
foreach (['private', 'public'] as $directory) {
    $path = $cache . '/' . $directory;
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

Testing::bootstrapModule(__FILE__);
