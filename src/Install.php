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


    public string $root = "";



    /**
     * @param $root - the project root directory, where wordpress public folder should be installed
     */
    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public  function install()
    {

        // install node_modules if not already installed
        $this->installNodeModules();
        $this->installManifestFiles();
        if (!CLI::confirm('Would you like to create a fresh install of wordpress? This will remove any previous wordpress directory and overwrite any existing database', CLI::$colors['Magenta'])) return;
        // create wp-content directory which serves as volume for docker container
        $this->createWPContentDirectory();

        // create the container and install wordpress db
        $this->startDocker(['--reset']);
        $this->initWebpack();
        // exec("chmod +x vendor/bin/whr || echo 'Failed to find whr in vendor/bin directory'");
        CLI::log('Installation complete!', CLI::$colors['Green']);
        CLI::log(' You can now run "vendor/bin/whr start" or "vendor/bin/whr start --hot"');
    }

    public function scaffoldChildTheme()
    {
        $themeSlug = ChildThemeCreator::create();
        $this->activateTheme($themeSlug);
        $this->initWebpack();
        CLI::log('Child theme activated!', CLI::$colors['Green']);
        CLI::log('You can now run "vendor/bin/whr start" or "vendor/bin/whr start --hot"');
    }

    private function updateWhrJsonTheme($themeSlug)
    {
        CLI::log("Updating whr.json theme -> $themeSlug", CLI::$colors['Light Magenta']);
        $path = $this->root . DIRECTORY_SEPARATOR . 'whr.json';
        $json = WHRJson::get($path);
        $json['config']['theme'] = $themeSlug;
        WHRJson::save($path, $json);
    }


    private function initWebpack()
    {
        // then move it to resources directory
        $this->copyActiveThemeFilesToResources();
        // add the enqueue assets include to active theme functions.php 
        $this->addEnqueueAssetsToFunctions();
        // build to copy resources files to active theme dir
        $this->build();
    }




    private function activateTheme($themeSlug)
    {

        $this->updateWhrJsonTheme($themeSlug);
        // make sure child theme is activated
        CLI::log('activating theme: ' . $themeSlug, CLI::$colors['Green']);
        Themes::setActiveTheme($themeSlug);
    }


    private function createWPContentDirectory()
    {
        $dirName = 'public';
        $path = $this->root . DIRECTORY_SEPARATOR . $dirName;
        if (file_exists($path)) {
            if (CLI::confirm('Public directory already exists. Would you like to overwrite it?', CLI::$colors['Light Blue'])) {
                $this->removePublicDir($dirName);
            } else {
                return;
            }
        }
        CLI::log("Downloading WP Directory to $path");
        $this->downloadWP($path);
    }

    private function downloadWP($path)
    {
        mkdir($path, 0775, true);
        // download latest wordpress
        $wp = file_get_contents('https://wordpress.org/latest.zip');
        $tmpDir = tempnam(sys_get_temp_dir(), 'wp-download');
        unlink($tmpDir);
        mkdir($tmpDir, 0755, true);
        file_put_contents("$tmpDir/latest.zip", $wp);
        // unzip wordpress (unzips to root within wordpress dir)
        $zip = new \ZipArchive();
        $res = $zip->open("$tmpDir/latest.zip");
        if ($res === true) {
            $zip->extractTo($tmpDir);
            $zip->close();
            // move wordpress wp-content to target dir
            $src = "$tmpDir/wordpress/wp-content";
            $dest = $path; // public dir
            exec("mv $src $dest");
            // delete the wordpress directory
            exec("rm -r -f $tmpDir");
            CLI::log('Wordpress unzipped successfully');
        } else {
            CLI::log('Wordpress unzipped failed');
        }
    }


    private function removePublicDir($dirName)
    {
        $path = $this->root . DIRECTORY_SEPARATOR . $dirName;
        exec("rm -r -f $path", $output, $result_code);
        if ($result_code != 0) {
            throw new \Exception('Failed to overwrite public directory: ' . json_encode($output));
        }
    }











    private function installManifestFiles()
    {
        $manifest = json_decode(file_get_contents(__DIR__ . '/install-manifest.json'), true);
        foreach ($manifest as $file) {
            list($filename, $filepath) = $file;
            CLI::log("Installing $filename to $filepath");
            // validate relative path exists
            if (!file_exists($this->root . DIRECTORY_SEPARATOR . $filepath) && !empty($filepath)) {
                try {
                    mkdir($this->root . DIRECTORY_SEPARATOR . $filepath, 0775, true);
                } catch (\Exception $e) {
                    CLI::log("Error creating directory: '" . $this->root . DIRECTORY_SEPARATOR . $filepath . "'. Error: " . $e->getMessage());
                    exit;
                }
            }
            $src = __DIR__ . '/files' . '/' . $filename;
            $destination = $this->root . DIRECTORY_SEPARATOR . $filepath . $filename;
            // if the file does not already exist, copy it
            if (!file_exists($destination)) {
                copy($src, $destination);
            } else {
                CLI::log("$filename already exists", CLI::$colors['Dark Grey']);
            }
        }
    }







    /**
     * Add enqueue line to functions.php
     *
     * Appends the enqueue line to the functions.php file if it does not already exist
     *
     * @param string $this->root
     * @return void
     */
    public static function addEnqueueAssetsToFunctions()
    {
        // find active theme
        $functionsPath = 'resources/functions.php';
        if (!is_file($functionsPath)) {
            throw new \Exception('Could not find functions.php in active theme at ' . $functionsPath);
        }
        $functions = file_get_contents($functionsPath);
        $enqueueLine = "/** Enqueue Custom Theme Assets */ " . PHP_EOL . "require_once  get_stylesheet_directory() . '/inc/enqueue-assets.php';";
        // append the line only if it doesn't already exist
        if (strpos($functions, $enqueueLine) === false) {
            // if functions.php has a closing php tag, prepend the line to it, otherwise append it
            if (strpos($functions, "?>") !== false) {
                $functions = str_replace("?>", $enqueueLine . PHP_EOL . "?>", $functions);
            } else {
                $functions .=   PHP_EOL . $enqueueLine;
            }
            file_put_contents($functionsPath, $functions);
        }
    }

    private function useDefaultTheme()
    {
        $themes = Themes::getThemes();
    }

    private  function getActiveThemePath()
    {
        $activeThemePath = WHRJson::get($this->root . DIRECTORY_SEPARATOR . 'whr.json')['config']['themePath'] ?? throw new \Exception('Failed to find theme path in whr.json');
        $activeTheme = WHRJson::get($this->root . DIRECTORY_SEPARATOR . 'whr.json')['config']['theme'] ?? throw new \Exception('Failed to find theme path in whr.json');
        return $this->root . DIRECTORY_SEPARATOR . $activeThemePath . $activeTheme;
    }

    /**
     * Either copy child theme or parent theme files to resources, depending on installation type
     */
    public function copyActiveThemeFilesToResources()
    {
        $activeThemePath = $this->getActiveThemePath();
        $resourcesPath = $this->root . DIRECTORY_SEPARATOR . 'resources';
        CLI::log("Copying functions.php to resources directory: cp $activeThemePath/functions.php $resourcesPath/functions.php");
        exec("cp $activeThemePath/functions.php resources/functions.php");
    }

    private static function startDocker($opts = [])
    {
        $output = [];
        $result_code = 0;
        $flags = implode(' ', $opts);
        CLI::log('Creating docker container...');
        if (!CLI::exec("start $flags", $output, $result_code)) {
            throw new \Exception("Failed to start docker container: " . implode("\n", $output));
        }
    }

    private function installNodeModules()
    {
        CLI::log("Installing wordpress npm dependencies");
        exec('cd vendor/aslamhus/wordpress-hmr && npm install --loglevel "error" || exit 1', $output, $result_code);
        if ($result_code != 0) {
            throw new \Exception("failed to install nodem modules: " . implode("\n", $output));
        }
        return $result_code == 0;
    }

    private static function build()
    {
        $output = [];
        if (!CLI::exec('build', $output)) {
            throw new \Exception('Build failed:' . implode('\n', $output));
        }
    }

    /**
     * Copy functions.php from active theme to resources 
     * and add require enqueue-assets line.
     * This is necessary when we switch themes (see whr syncTheme in utils.sh)
     */
    public function copyFunctions()
    {
        if (CLI::confirm('Copy functions.php from active theme to resources? This will overwrite resources/functions.php')) {
            $this->copyActiveThemeFilesToResources();
            $this->addEnqueueAssetsToFunctions();
        }
    }
}
