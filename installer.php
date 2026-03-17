<?php

//installer lives in vendor/aslamhus/wordpress-hmr directory
$pathToAutoload = __DIR__ . '/../../autoload.php';
if (!file_exists(__DIR__ . '/../../autoload.php')) {
    echo "Vendor directory not found in the root directory. Please run the installer from the root directory of your project";
    return;
}
require_once $pathToAutoload;


use Aslamhus\WordpressHMR\Install;
use Aslamhus\WordpressHMR\CLI;

/**
 * 
 * Options
 * --scaffold-child: creates child theme
 * none: installs
 * 
 */
$option = $argv[1] ?? [];
$installer = new Install(getcwd());
try {
    switch ($option) {
        case '--scaffold-child':
            $installer->scaffoldChildTheme();
            break;

        default:
            $installer->install();
    }
} catch (\Exception $e) {
    CLI::log($e->getMessage(), CLI::$colors['Red']);
}
