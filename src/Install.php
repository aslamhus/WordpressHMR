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
        if (!CLI::confirm('Would you like to create a fresh install of wordpress?')) return;
        self::installManifestFiles($root);
        // create wp-content directory which serves as volume for docker container
        self::createWPContentDirectory($root);
        // create the container
        self::createDockerContainer();
        // set up wp database
        self::createDB();
        if (CLI::confirm('Would you like to create a child theme?')) {
            ChildThemeCreator::create($root);
        }

        // then move it to resources directory
        self::copyActiveThemeFilesToResources($root);
        // add the enqueue assets include to active theme functions.php 
        self::addEnqueueAssetsToFunctions($root);
        self::build();
        // make whr executable
        exec("chmod +x vendor/bin/whr || echo 'Failed to find whr in vendor/bin directory'");
        CLI::log('Installation complete! You can now run "vendor/bin/whr start"', CLI::$colors['Green']);
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
                CLI::log('Wordpress unzipped successfully');
            } else {
                CLI::log('Wordpress unzipped failed');
            }
        } else {
            CLI::log('Public directory already exists');
        }
    }





    private static function createDB()
    {
        CLI::log('Creating wp database');
        sleep(4);
        if (!CLI::exec("create-db", $output)) {
            CLI::log('Failed to create wordpress database: ' . json_encode($output), CLI::$colors['Red']);
            throw new \Exception('Failed to create wordpress database');
        }
        CLI::log('Successfully created database', CLI::$colors['Green']);
    }









    private static function installManifestFiles($root)
    {
        $manifest = json_decode(file_get_contents(__DIR__ . '/install-manifest.json'), true);

        foreach ($manifest as $file) {
            list($filename, $filepath) = $file;
            CLI::log("Installing $filename to $filepath");
            // validate relative path exists
            if (!file_exists($root . $filepath) && !empty($filepath)) {
                try {
                    mkdir($root . $filepath, 0775, true);
                } catch (\Exception $e) {
                    CLI::log("Error creating directory: '" . $root . $filepath . "'. Error: " . $e->getMessage());
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
        // find active theme
        // $activeThemePath = self::getActiveThemePath($root);
        // $functionsPath = $activeThemePath . '/functions.php';
        $functionsPath = 'resources/functions.php';
        if (!is_file($functionsPath)) {
            throw new \Exception('Could not find functions.php in active theme at ' . $functionsPath);
        }
        $functions = file_get_contents($functionsPath);
        $enqueueLine = "/** Enqueue Custom Theme Assets */ " . PHP_EOL . "require_once  get_stylesheet_directory() . '/inc/enqueue-assets.php';";
        if (strpos($functions, $enqueueLine) === false) {
            file_put_contents($functionsPath, $functions . PHP_EOL . $enqueueLine);
        }
    }

    private static function getActiveThemePath($root)
    {
        $activeThemePath = WHRJson::get($root . 'whr.json')['config']['themePath'] ?? throw new \Exception('Failed to find theme path in whr.json');
        $activeTheme = WHRJson::get($root . 'whr.json')['config']['theme'] ?? throw new \Exception('Failed to find theme path in whr.json');
        return $root . $activeThemePath . $activeTheme;
    }

    private static function copyActiveThemeFilesToResources($root)
    {
        $activeThemePath = self::getActiveThemePath($root);
        $resourcesPath = $root . 'resources';

        CLI::log("Copying functions.php to resources directory: cp $activeThemePath/functions.php $resourcesPath/functions.php");
        exec("cp $activeThemePath/functions.php resources/functions.php");
    }

    private static function createDockerContainer()
    {
        $output = [];
        $result_code = 0;
        CLI::exec('start-docker', $output, $result_code);
    }


    private static function build()
    {
        $output = [];
        CLI::exec('start-docker');
        CLI::exec('build', $output);
        // print_r($output);
        foreach ($output as $line) {
            echo "$line\n";
        }
    }
}
