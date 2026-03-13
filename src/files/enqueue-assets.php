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
$path =  ABSPATH . '/../whr.json';
if (!file_exists($path)) {
    throw new \Exception("whr.json does not exist at " . $path);
}
$jsonString = file_get_contents($path);
$assetsJson =  json_decode($jsonString, true);

if (empty($assetsJson)) {
    throw new \Exception('Enqueue assets failed: whr.json could not be parsed or was empty.');
}
$enqueuer = new EnqueueAssets($assetsJson, ABSPATH);
$enqueuer->enqueue();
