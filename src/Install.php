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

        self::installManifestFiles($root);
        // // create wp-content directory which serves as volume for docker container
        self::createWPContentDirectory($root);
        // // // install package.json or add scripts/dependencies to existing package.json
        // self::installPackageJson($root);
        // exec("npm install && run build");
        // use default active theme
        self::useDefaultTheme($root);

        // make whr executable
        exec("chmod +x vendor/bin/whr || echo 'Failed to find whr in vendor/bin directory");



        // self::log('Installation complete! Please update your assets.sample.json file with the correct values and rename it to whr.json');
    }

    private static function useDefaultTheme($root)
    {
        $themes = self::getThemes($root);
        $filePath = $root . 'whr.json';
        $json = self::getWhrJson($filePath);
        $json['config']['theme'] = $themes[0];
        self::saveWhrJson($filePath, $json);
        self::log("Using default theme '" . $themes[0] . "'. You can change your theme in the whr.json file, but make sure to update your active theme in the wordpress admin.");
    }



    private static function createWPContentDirectory($root)
    {
        $dirName = 'public';
        // create directory if it does not exist
        if (!file_exists($root . $dirName)) {
            mkdir($root . $dirName, 0775, true);
            // download latest wordpress
            $wp = file_get_contents('https://wordpress.org/latest.zip');
            file_put_contents('/tmp/latest.zip', $wp);
            // unzip wordpress (unzips to root within wordpress dir)
            $zip = new \ZipArchive();
            $res = $zip->open('/tmp/latest.zip');
            if ($res === true) {
                $zip->extractTo('/tmp');
                $zip->close();
                // // move wordpress wp-content to target dir
                $src = '/tmp/wordpress/wp-content';
                $dest = $root . $dirName;
                exec("mv $src $dest");

                // delete the wordpress directory
                exec("rm -r /tmp/wordpress && rm /tmp/latest.zip");
                self::log('Wordpress unzipped successfully');
            } else {
                self::log('Wordpress unzipped failed');
            }
        } else {
            self::log('Public directory already exists');
        }
    }

    // public static function postPackageInstall(string $root = "")
    // {


    //     self::log("Starting post package install");
    //     $root = $root ?? __DIR__;
    //     // check that vendor exists in the root directory
    //     if (!file_exists($root . 'vendor')) {
    //         self::log("Vendor directory not found in the root directory. Please run composer install");
    //         return;
    //     }
    //     // create public directory and install wordpress if it does not exist
    //     self::createPublicDirectory($root);
    //     // load wp
    //     self::loadWPFunctions($root);
    //     // get themes and list them
    //     $themes = self::printThemes($root);
    //     // create child theme directory in public/wp-content/themes
    //     $chosenTheme = self::chooseParentTheme($root, $themes);
    //     // create child theme directory in public/wp-content/themes
    //     $childTheme = self::createChildTheme($root, $chosenTheme);
    //     // Install files based on the install-manifest.json
    //     self::installManifestFiles($root);
    //     // add style.css to resources directory
    //     self::addStylesheetToResources($root, $childTheme, $chosenTheme);
    //     // update whr.json with the child theme name
    //     self::updateWhrJson($root, $childTheme);
    //      // get all releveant files from chosen theme and add them to resources directory
    //     self::addParentThemeFiles($root, $chosenTheme);
    //     // now copy the resources directory contents to the child theme
    //     self::copyResourcesToChildTheme($root, $childTheme);


    //     // set active theme
    //     switch_theme( $childTheme);
    //     // build asset files

    //     self::log("Running npm run build to create asset files");
    //     exec("npm install && run build");
    //     // update_option('current_theme', $childTheme);
    //     // end
    //     self::log('Installation complete! Please update your assets.sample.json file with the correct values and rename it to whr.json');
    // }

    private static function log($message)
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        echo $message . PHP_EOL;
    }

    private static function read($prompt): string
    {

        self::log($prompt);
        self::log(''); // add a new line
        return trim(fgets(STDIN));
    }




    private static function createPublicDirectory($root)
    {
        // create public directory if it does not exist
        if (!file_exists($root . 'public')) {
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
                self::log('Wordpress unzipped successfully');
            } else {
                self::log('Wordpress unzipped failed');
            }
        } else {
            self::log('Public directory already exists');
        }
    }

    private static function loadWPFunctions($root)
    {
        if (!file_exists($root . 'public/wp-load.php')) {
            throw new \Exception('wp-load.php does not exist in public directory, please check that wordpress was installed correctly at ' . $root . 'public');
        }
        require_once $root . 'public/wp-load.php';
    }


    private static function printThemes($root)
    {
        $themes = self::getThemes($root);
        // find list of themes in public/wp-content/themes
        $themes = self::getThemes($root);
        self::log('Themes found in public/wp-content/themes');
        self::log('-----------------------------');
        foreach ($themes as $index => $theme) {
            self::log($index + 1 . '. ' . $theme);
        }
        self::log('-----------------------------');
        return $themes;
    }



    private static function getThemes($root): array
    {
        $themes = [];
        $it = new \FilesystemIterator($root . 'public/wp-content/themes');
        foreach ($it as $fileinfo) {
            $file = $fileinfo->getFilename();
            // self::log('Found theme: ' . $file . ' is_dir: ' . is_dir($root . 'public/wp-content/themes/' . $file));
            if (is_dir($root . 'public/wp-content/themes/' . $file) !== true) {
                continue;
            }
            $themes[] = $file;
        }
        return $themes;
    }

    private static function chooseParentTheme($root, $themes)
    {

        // ask user to choose a theme
        $chosenThemeIndex = self::read('Enter parent theme number (you can change this later): ');
        if (!filter_var($chosenThemeIndex, FILTER_VALIDATE_INT)) {
            self::log("Please enter a number (found:$chosenThemeIndex)");
            return self::chooseParentTheme($root, $themes);
        }
        $chosenTheme = $themes[$chosenThemeIndex - 1];
        if (empty($chosenTheme)) {
            self::log('Theme does not exist');
            return self::chooseParentTheme($root, $themes);
        }


        // check if the theme exists
        if (!file_exists($root . 'public/wp-content/themes/' . $chosenTheme)) {
            self::log('Theme does not exist');
            return self::chooseParentTheme($root, $themes);
        }
        return $chosenTheme;
    }

    private static function createChildTheme($root, $parentTheme)
    {

        // ask user to choose a child theme name
        $childThemeName = self::read('Enter child theme name (no spaces or special characters): ');
        // remove special characters and spaces
        $childThemeName = preg_replace('/[^A-Za-z0-9\-]/', '', $childThemeName);
        // make child theme dir
        if (!file_exists($root . 'public/wp-content/themes/' . $childThemeName)) {
            mkdir($root . 'public/wp-content/themes/' . $childThemeName, 0775, true);
        } else {
            self::log('Child theme already exists');
        }



        return $childThemeName;
    }

    private static function addParentThemeFiles($root, $chosenTheme)
    {
        $files = ['theme.json', 'screenshot.png'];
        $themeRoot = $root . 'public/wp-content/themes/' . $chosenTheme;
        $resourcesRoot = $root . 'resources';
        foreach ($files as $file) {

            copy($themeRoot . '/' . $file, $resourcesRoot . '/' . $file);
            // $root . 'resources/style.css
        }
    }

    private static function copyResourcesToChildTheme($root, $childTheme)
    {
        // // install resources directory with latest wordpress theme
        // if (!file_exists($root . 'resources')) {
        //     mkdir($root . 'resources', 0775, true);
        // copy latest wordpress theme directory (twentytwentyfour) from public directory
        self::recursiveCopyDirectory($root . 'resources', $root . 'public/wp-content/themes/' . $childTheme . '/');
        // } else {
        //     self::log('Resources directory already exists');
        // }

    }

    private static function addStylesheetToResources($root, $childTheme, $chosenTheme)
    {
        // add style.css to resources directory
        // if (!file_exists($root . 'resources/style.css')) {
        $style = '/*' . PHP_EOL;
        $style .= 'Theme Name: ' . $childTheme . PHP_EOL;
        $style .= 'Template: ' . $chosenTheme . PHP_EOL;
        $style .= 'Theme URI: ' . PHP_EOL;
        $style .= 'Description: ' . PHP_EOL;
        $style .= 'Author: ' . PHP_EOL;
        $style .= 'Author URI: ' . PHP_EOL;
        $style .= 'Version: 1.0.0' . PHP_EOL;
        $style .= 'License: GNU General Public License v2 or later' . PHP_EOL;
        $style .= 'License URI: http://www.gnu.org/licenses/gpl-2.0.html' . PHP_EOL;
        $style .= 'Text Domain: ' . $childTheme . PHP_EOL;
        $style .= '*/' . PHP_EOL;
        file_put_contents($root . 'resources/style.css', $style);
        // } else {
        //     self::log('Style.css already exists');
        // }
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
            list($filename, $filepath) = $file;
            self::log("Installing $filename to $filepath");
            // validate relative path exists
            if (!file_exists($root . $filepath) && !empty($filepath)) {
                try {
                    mkdir($root . $filepath, 0775, true);
                } catch (\Exception $e) {
                    self::log("Error creating directory: '" . $root . $filepath . "'. Error: " . $e->getMessage());
                    exit;
                }
            }
            $src = __DIR__ . '/files' . '/' . $filename;
            $destination = $root . $filepath . $filename;
            // if the file does not already exist, copy it
            if (!file_exists($destination)) {
                copy($src, $destination);
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
            if (!isset($package['scripts'])) {
                $package['scripts'] = [];
            }
            foreach ($scripts as $key => $value) {
                // skip script if it already exists
                if (isset($package['scripts'][$key])) {
                    continue;
                }
                $package['scripts'][$key] = $value;
            }
            $package['devDependencies'] = $devDependencies;
            file_put_contents($root . 'package.json', json_encode($package, JSON_PRETTY_PRINT));
        }
    }


    // private static function updateWhrJson($root, $childTheme)
    // {
    //     $sample = $root . 'resources/assets/assets.sample.json';
    //     $assets = $root . 'resources/assets/whr.json';
    //     self::updateWhrJsonConfig($sample, $childTheme);
    //     self::updateWhrJsonConfig($assets, $childTheme);
    // }

    private static function saveWhrJson(string $filePath, array $json)
    {

        if (!file_exists($filePath)) {
            throw new \Exception("whr.json does not exist at " . $filePath);
        }
        file_put_contents($filePath, json_encode($json, JSON_PRETTY_PRINT));
    }

    private static function getWhrJson(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("whr.json does not exist at " . $filePath);
        }
        $jsonString = file_get_contents($filePath);
        return json_decode($jsonString, true);
    }

    private static function updateWhrJsonConfig($path, $childTheme)
    {

        if (!file_exists($path)) {
            throw new \Exception("whr.json does not exist at " . $path);
        }
        $jsonString = file_get_contents($path);
        $whrJson =  json_decode($jsonString, true);
        $whrJson['config']['theme'] = $childTheme;
        file_put_contents($path, json_encode($whrJson, JSON_PRETTY_PRINT));
    }

    /**
     * Add enqueue line to functions.php
     *
     * Appends the enqueue line to the functions.php file if it does not already exist
     *
     * @param string $root
     * @return void
     */
    private static function addEnqueueAssetsToFunctions($root)
    {
        $functionsPath = $root . 'resources/functions.php';
        $functions = file_get_contents($functionsPath);
        $enqueueLine = "/** Enqueue Custom Theme Assets */ " . PHP_EOL . "require_once  get_stylesheet_directory() . '/inc/enqueue-assets.php';() . '/inc/enqueue-assets.php';";
        if (strpos($functions, $enqueueLine) === false) {
            file_put_contents($functionsPath, $functions . PHP_EOL . $enqueueLine);
        }
    }
}
