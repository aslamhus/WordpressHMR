<?php

namespace Aslamhus\WordpressHMR;

/**
 *
 * Install
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
    public static function postPackageInstall()
    {
        echo 'installing...';
        $root = __DIR__ . '/../../../../';
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
}
