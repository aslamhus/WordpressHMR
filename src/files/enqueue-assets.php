<?php

/**
 * This config file is only used in development.
 * When you build the theme for production, this file is not included.
 * Instead, an assets manifest file replaces enqueue-assets.php.
 */
require_once ABSPATH . '/../vendor/autoload.php';

use Aslamhus\WordpressHMR\EnqueueAssets;

// get assets json
// load assets json file and return it as an associative array
$path =  '/assets/assets.json';
$path = get_stylesheet_directory() . $path;
if (!file_exists($path)) {
    throw new \Exception("assets.json does not exist at " . $path);
}
$jsonString = file_get_contents($path);
$assetsJson =  json_decode($jsonString, true);

$enqueuer = new EnqueueAssets($assetsJson);
$enqueuer->enqueue();
