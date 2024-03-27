<?php

if(!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "Vendor directory not found in the root directory. Please run the installer from the root directory of your project";
    return;
}
require_once __DIR__ . '/vendor/autoload.php';

use Aslamhus\WordpressHMR\Install;

Install::postPackageInstall(__DIR__ . '/');
