<?php

namespace Aslamhus\WordpressHMR;

/**
 * Enqueue Assets 1.0.0
 *
 * by @aslamhus
 *
 * EnqueueAssets is a class that dynamically enqueues wordpress assets in development
 * and builds an assets file for production for efficient asset loading.
 */
class EnqueueAssets
{
    private array $assetsJson;
    private array $config;
    private string $devPath;
    private string $handlePrefix;
    private array $queue = [];
    private string $env;


    public function __construct(array $assetsJson)
    {
        // get the environment type
        if(function_exists('wp_get_environment_type')) {
            $this->env = wp_get_environment_type() ;
        } else {
            $this->env = 'build';
        }
        $this->assetsJson = $assetsJson;
        // get the config
        $this->config = $assetsJson['config'] ?? [];
        if(empty($this->config)) {
            throw new \Exception("'config' is not defined in assets.json.");
        }
        // get devPath (the base path for development assets)
        $this->devPath = $this->getDevPath();
        if(empty($this->devPath)) {
            throw new \Exception("'devPath' is not defined in assets.json");
        }
        // get the handle prefix
        $this->handlePrefix = $this->config['handlePrefix'] ?? "custom-asset";
        // get the hooks by traversing assets json and merging all hooks
        // @see https://developer.wordpress.org/apis/hooks/action-reference/
        $this->queue = $this->buildQueue($this->assetsJson['assets']);

    }

    /**
     * Enqueue the assets
     *
     * This method is used in development to dynamically enqueue assets
     * and enable hot module replacement (HMR)
     *
     * Enqueue iterates through the queue assets (compiled by buildQueue method)
     * and enqueues them based on the hook and condition provided in the assets.json file
     *
     * @return void
     */
    public function enqueue(int $priority = 10)
    {
        // for each hook (i.e. 'admin_enqueue_scripts'), add an action that enqueues each relevant asset
        foreach($this->queue as $hook => $queueItems) {
            add_action($hook, function () use ($queueItems) {
                // note: sometimes if a url hits a 404 you'll get hooks called for each null asset
                // this will call certain actions multiple times but with null args.
                // this is a workaround to prevent that
                if(empty($queueItems)) {
                    return;
                }

                foreach($queueItems as $queueItem) {
                    $func_name = $queueItem[0];
                    $enqueueArgs = $queueItem[1];
                    $condition = $queueItem[2] ?? null;
                    // check if the condition is met
                    if(!$this->evalCondition($condition)) {
                        continue;
                    }
                    // enqueue the asset
                    call_user_func($func_name, ...$enqueueArgs);
                }
            }, $priority, 0);
        }

    }

    /**
     * Build the assets file
     *
     * Creates a file that enqueues all the assets without
     * traversing the assets.json file each page load, avoiding dynamic asset loading
     * and improving performance. This method is called in the build process.
     *
     * @return void
     */
    public function buildAssetsFile(): void
    {


        $themePath = $this->config['themePath'] ?? '';
        $enqueueAssetsFile = __DIR__ . '/../public' . $themePath . "/inc/enqueue-assets.php";
        $content = "<?php\n";
        foreach($this->queue as $hook => $queueItems) {
            $content .= "// $hook\n";
            $content .= "add_action('$hook', function () {\n";
            foreach($queueItems as $queueItem) {
                $func_name = $queueItem[0];
                $enqueueArgs = $queueItem[1];
                $condition = $queueItem[2] ?? null;
                $argsArrayString = json_encode($enqueueArgs, JSON_UNESCAPED_SLASHES);
                if($condition) {
                    $statement = $condition[0];
                    $argument = $condition[1];
                    $value = $condition[2] ?? true;
                    if(is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }
                    // if condition is true, enqueue the asset
                    $content .= "if(call_user_func('$statement', '$argument') == $value) {\n";
                    // enqueue the asset
                    $content .= "call_user_func('$func_name', ...$argsArrayString);\n";
                    $content .= "}\n";
                } else {
                    $content .= "call_user_func('$func_name', ...$argsArrayString);\n";

                }
            }
            $content .= "});\n\n";
        }
        file_put_contents($enqueueAssetsFile, $content);
    }

    /**
     * Build the queue of assets to be enqueued
     *
     * Builds an array of hooks with their respective assets to be enqueued
     * example: [ 'wp_enqueue_scripts' => [ ['handle', 'wp_enqueue_script', $enqueueArgs], ... ] ]
     *
     * @param array $assetsForHooks
     * @return array
     */
    private function buildQueue(array $assetsForHooks): array
    {

        // loop through the assets and enqueue them
        if(!is_array($assetsForHooks)) {
            throw new \Exception("assets argument must be an array in order to enqueue your custom scripts and styles");
        }
        // 1. loop through the assets for the hook and enqueue them
        foreach($assetsForHooks as $hook => $assets) {

            // initialize the queue item if it does not exist
            if(!isset($this->queue[$hook])) {
                $this->queue[$hook] = [];
            }
            // loop through the assets and enqueue them
            foreach($assets as $assetData) {
                if(!is_array($assetData)) {
                    continue;
                }
                // get the enqueue item
                $queueItem = $this->pushEnqueueAsset($assetData);
                // push the enqueue item to the queue
                if($queueItem) {
                    $this->queue[$hook][] = $queueItem;
                }
            }
        }
        return $this->queue;
    }


    /**
     * Push an asset to the queue
     *
     * Adds a queue item to the queue array ['handle', 'wp_enqueue_script', $enqueueArgs]
     *
     * @param array $assetData
     * @return array|null
     */
    private function pushEnqueueAsset(array $assetData): ?array
    {
        if(!is_array($assetData)) {
            return null;
        }
        // check for a conditional
        // i.e. is_page_template() or is_front_page() etc
        $condition = $assetData['condition'] ?? null;
        // get the handle of the asset
        $handle = $this->handlePrefix . "-" . ($assetData['handle'] ?? uniqid());
        // get the asset extension (js/css/min.js/....)
        $ext = $assetData['ext'];
        // get asset relative path
        $assetRelativePath = $assetData['path'];
        // get src path of the asset
        $srcPath = $this->getSrcPath($assetRelativePath, $this->env, $this->devPath, $ext);
        // prepare enqueue arguments
        // for more on enqueue arguments, @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
        $enqueueArgs = [ $handle, $srcPath];
        // 3. check if the environment is production or development
        if($this->env === 'production' || $this->env === 'build') {
            // get the asset file generated by wp-scripts (@see Webpack config)
            // this file contains the asset handle, dependencies, version, and in_footer flag
            if($this->env === 'build') {
                $themePath = $this->config['themePath'] ?? '';
                $asset = __DIR__ . "/../public". $themePath .  $assetRelativePath . ".asset.php";
            } else {
                $asset = get_parent_theme_file_uri($assetRelativePath . ".asset.php");
            }


            if(!file_exists($asset)) {
                // throw an error if the asset file does not exist
                throw new \Exception("Asset file does not exist: " . $asset);
            }
            // get the asset meta data
            $assetMeta = require $asset;
            // add the asset meta data to the enqueue arguments
            $enqueueArgs = [
                ...$enqueueArgs,
                $assetMeta['dependencies'] ?? [],
                $assetMeta['version'] ?? false,
                $assetMeta['in_footer'] ?? false
            ];
        }
        // Note: in development, we don't need to enqueue the asset file
        // we use the src path to load the asset from the dev server (achieving HMR)
        // 4. finally, we enqueue the asset
        $func_name = "wp_enqueue_script";
        // call_user_func_array($func_name, $enqueueArgs);
        return [  $func_name, $enqueueArgs, $condition];
    }

    private function getDevPath(): string
    {
        // devPath is constructed from the protocol, host, port and themePath
        $themePath = $this->config['themePath'] ?? '';
        $host = $this->config['host'] ?? '';
        $port = $this->config['port'] ?? ''; // default to empty
        $protocol = $config['protocol'] ?? 'http'; // default to http
        if(empty($themePath) || empty($host) && $this->env === 'development') {
            throw new \Exception("'themePath' and 'host' are required in assets.json for development environment.");
        }
        // construct the devPath, e.g. http://localhost:8888/wp-content/themes/my-child-theme
        $devPath = $protocol . "://" . $host . ($port ? ":" . $port : "") . $themePath;
        // remove trailing slash if present
        return rtrim($devPath, '/');

    }



    /**
     * Get the src path of the asset
     *
     *
     *  development (compiled from config values): http://localhost:8888/wp-content/themes/my-child-theme/js/screen.js
     *  production: /path/to/theme/assets/js/screen.js
     *
     * @param string $assetRelativePath
     * @param string $env
     * @param string $devPath
     * @param string $ext
     * @return string
     */
    private function getSrcPath(string $assetRelativePath, string $env, string $devPath, string $ext): string
    {


        $root = $devPath;
        if($env === 'build' || $env === 'production') {
            $root =  $this->config['site'] .  $this->config['themePath'] ;
        }
        return $root . $assetRelativePath . "." .$ext;
    }

    /**
     * Evaluate the condition provided in the assets.json file
     *
     * example: [ 'is_page_template', 'template-about.php', true]
     * is evaluated as: is_page_template('template-about.php') == true
     *
     * @param array|null $condition
     * @return boolean
     */
    private function evalCondition(?array $condition): bool
    {

        if(!$condition) {
            return true;
        }
        $func = $condition[0];
        $argument = $condition[1] ?? null;
        // value to check expression against (defaults to true)
        $value = $condition[2] ?? true;
        return call_user_func($func, $argument) == $value;
    }

}
