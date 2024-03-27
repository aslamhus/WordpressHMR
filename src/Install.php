<?php

namespace Aslamhus\WordpressHMR;

/**
 *
 * Install
 *
 * installs the wordpress theme and other files based on the install-manifest.json
 *
 *
 * Directory Structure
 * |__ vendor
 *  |   |__ aslamhus
 *  |  |___|__ src
 *   |  |___|__ bin
 *
 */
class Install
{
    public static function postPackageInstall(string $root = "")
    {
        echo 'installing...';
        $root = $root ?? __DIR__;
        // check that vendor exists in the root directory
        if (!file_exists($root . 'vendor')) {
            echo "Vendor directory not found in the root directory. Please run composer install";
            return;
        }
        // create public directory and install wordpress if it does not exist
        self::createPublicDirectory($root);
        // install resources directory with latest wordpress theme
        self::copyWordpressChildThemeToResources($root);
        // Install files based on the install-manifest.json
        self::installManifestFiles($root);
        // install package.json or add scripts/dependencies to existing package.json
        self::installPackageJson($root);
        // finally add enquue-assets to functions.php
        self::addEnqueueAssetsToFunctions($root);
    }

    private static function createPublicDirectory($root)
    {
        // create public directory if it does not exist
        if(!file_exists($root . 'public')) {
            mkdir($root . 'public', 0775, true);
            // download latest wordpress
            $wp = file_get_contents('https://wordpress.org/latest.zip');
            file_put_contents($root . 'public/latest.zip', $wp);
            // unzip wordpress
            $zip = new \ZipArchive();
            $res = $zip->open($root . 'public/latest.zip');
            if ($res === true) {
                $zip->extractTo($root . 'public/');
                $zip->close();
                // remove the zip file
                unlink($root . 'public/latest.zip');
                // move all files from wordpress directory to public directory
                $files = scandir($root . 'public/wordpress');
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        rename($root . 'public/wordpress/' . $file, $root . 'public/' . $file);
                    }
                }
                // delete the wordpress directory
                rmdir($root . 'public/wordpress');
                echo 'Wordpress unzipped successfully';
            } else {
                echo 'Wordpress unzipped failed';
            }
        }
    }

    private static function copyWordpressChildThemeToResources($root)
    {
        // install resources directory with latest wordpress theme
        if(!file_exists($root . 'resources')) {
            mkdir($root . 'resources', 0775, true);
            // copy latest wordpress theme directory (twentytwentyfour) from public directory
            self::recursiveCopyDirectory($root . 'public/wp-content/themes/twentytwentyfour', $root . 'resources/');
        }

    }

    private static function recursiveCopyDirectory(string $src, string $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::recursiveCopyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private static function installManifestFiles($root)
    {
        $manifest = json_decode(file_get_contents(__DIR__ . '/install-manifest.json'), true);

        foreach ($manifest as $file) {
            list($filename, $relativePath) = $file;
            // validate relative path exists
            if (!file_exists($root . $relativePath)) {
                mkdir($root . $relativePath, 0775, true);
            }
            $src = __DIR__ . '/files' . '/' . $filename;
            $destination = $root . $relativePath . $filename;
            // if the file does not already exist, copy it
            if(!file_exists($destination)) {
                copy($src, $destination);
            } else {
                echo "File already exists: $destination \n\n";
            }

        }
    }

    private static function installPackageJson($root)
    {
        // check if package.json exists
        if (!file_exists($root . 'package.json')) {
            // copy package.json from the package
            copy(__DIR__ . '/files/package.json', $root . 'package.json');
        } else {
            // add scripts and dependencies to existing package.json
            $package = json_decode(file_get_contents($root . 'package.json'), true);
            $scripts = json_decode(file_get_contents(__DIR__ . '/files/package.json'), true)['scripts'];
            $devDependencies = json_decode(file_get_contents(__DIR__ . '/files/package.json'), true)['devDependencies'];
            $package['scripts'] = $scripts;
            $package['devDependencies'] = $devDependencies;
            file_put_contents($root . 'package.json', json_encode($package, JSON_PRETTY_PRINT));
        }
    }

    private static function addEnqueueAssetsToFunctions($root)
    {
        $functions = file_get_contents($root . 'resources/functions.php');
        $enqueue = file_get_contents(__DIR__ . '/files/enqueue-assets.txt');
        if (strpos($functions, $enqueue) === false) {
            $functions = str_replace('<?php', '<?php' . $enqueue, $functions);
            file_put_contents($root . 'resources/functions.php', $functions);
        }
    }
}
