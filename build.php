<?php
// build file exists in docker container only, in parent directory of html,
// i.e. var/www/vendor/aslamhus/wordpress-hmr/build.php
// root dir is var/www
$rootDir = __DIR__ . '/../../../';
$wordpressRootPath = $rootDir . 'html';
require_once $rootDir . "vendor/autoload.php";

use Aslamhus\WordpressHMR\EnqueueAssets;

// get assets json
// load assets json file and return it as an associative array
$path =  $rootDir . './whr.json';
if (!file_exists($path)) {
    throw new \Exception("whr.json does not exist at path '" . $path . "'");
}
$jsonString = file_get_contents($path);
$whrJson =  json_decode($jsonString, true);
$enqueuer = new EnqueueAssets($whrJson, $wordpressRootPath);
$enqueuer->buildAssetsFile();
