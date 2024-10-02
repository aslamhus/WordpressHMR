<?php

$rootDir = __DIR__ . '/../../../../';
require_once $rootDir . "vendor/autoload.php";

use Aslamhus\WordpressHMR\EnqueueAssets;

// get assets json
// load assets json file and return it as an associative array
$path =  $rootDir . './resources/assets/assets.json';

if (empty(file_exists($path))) {
    throw new \Exception("assets.json does not exist at " . $path);
}
$jsonString = file_get_contents($path);
$assetsJson =  json_decode($jsonString, true);
$enqueuer = new EnqueueAssets($assetsJson, $rootDir . 'public');
$enqueuer->buildAssetsFile();
