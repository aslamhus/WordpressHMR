<?php

namespace Aslamhus\WordpressHMR;

/**
 * Enqueue Assets 1.2.3
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
    private string $handlePrefix;
    private \stdClass $isEnqueued;
    private array $queue = [];
    public const DEFAULT_WORDPRESS_PATH = __DIR__ . '/../../../../public';


    public function __construct(array $assetsJson, string $wordpressPath = self::DEFAULT_WORDPRESS_PATH)
    {
        // load wordpress functions
        if (!file_exists($wordpressPath . '/wp-load.php')) {
            throw new \Exception("wp-load.php not found at " . $wordpressPath);
        }
        // prevent wp-load from producing warning: "Undefined index: HTTP_HOST"
        if (PHP_SAPI === 'cli') {

            $_SERVER['HTTP_HOST'] ??= '';

        }
        // Note: There is an error where wp-load.php is being required twice
        // and giving warnings:
        // i.e. Warning: Constant DB_NAME already defined
        // the public build path defines where the assets are built
        // the public directory is where your wordpress installation is
        require_once $wordpressPath . '/wp-load.php';
        // init the isEnqueued object
        $this->isEnqueued = new \stdClass();
        // get the assets json
        $this->assetsJson = $assetsJson;
        // get the config
        $this->config = $assetsJson['config'] ?? [];

        if (empty($this->config)) {
            throw new \Exception("'config' is not defined in assets.json.");
        }
        // check the the config theme is the same as the stylesheet directory basename
        $configTheme = $this->config['theme'] ?? '';
        // get the theme name from the stylseheet (returns the theme directory name)
        // @see: https://developer.wordpress.org/reference/classes/wp_theme/
        $wpTheme = wp_get_theme()->get_stylesheet();
        if ($configTheme !== $wpTheme) {
            throw new \Exception("The theme name in the config file ('$configTheme') does not match the theme name in the stylesheet directory ('$wpTheme'). Please make sure that the active theme and the theme set in your assets.json files match.");
        }

        // get the handle prefix
        $this->handlePrefix = $this->config['handlePrefix'] ?? "custom-asset";
        // get the hooks by traversing assets json and merging all hooks
        // @see https://developer.wordpress.org/apis/hooks/action-reference/
        $this->queue = $this->buildQueue($this->assetsJson['assets']);

    }

    /**
     * Enqueue the development assets
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
        // reset the isEnqueued object
        $this->isEnqueued = new \stdClass();
        // for each hook (i.e. 'admin_enqueue_scripts'), add an action that enqueues each relevant asset
        foreach ($this->queue as $hook => $queueItems) {
            add_action($hook, function () use ($queueItems, $hook) {
                // note: sometimes if a url hits a 404 you'll get hooks called for each null asset
                // this will call certain actions multiple times but with null args.
                // this is a workaround to prevent that
                if (empty($queueItems)) {
                    return;
                }
                foreach ($queueItems as $queueItem) {
                    $func_name = $queueItem[0];
                    $enqueueArgs = $queueItem[1];
                    $condition = $queueItem[2] ?? null;
                    // add stylesheet directory uri to the src path in the enqueue args
                    // 1. handle
                    // 2. src
                    // 3. dependencies
                    // 4. version

                    list($handle, $src, $deps, $ver,$args) = $enqueueArgs;
         
                    $enqueueArgs[1] = get_stylesheet_directory_uri() . $enqueueArgs[1];
                    // check if the condition is met
                    if (!$this->evalCondition($condition)) {
                        continue;
                    }
                    // add the handle to the isEnqueued object
                    $path = $enqueueArgs[1];
                    if (isset($this->isEnqueued->$path)) {
                        continue;
                    }
                    $this->isEnqueued->$path = true;
                    // enqueue the asset
                    call_user_func($func_name, $handle, get_stylesheet_directory_uri() . $src, $deps, $ver, $args);
                    // enqueue accompanying stylesheet, if it exists
                    self::enqueueAccompanyingStylesheet_dev($handle,$src,$deps,$ver);
                
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
     *
     * Note: we can't use wordpress functions in the build process, so we need to
     * hardcode the paths and functions.
     *
     * @return void
     */
    public function buildAssetsFile(): void
    {
        // enqueue assets file path (local and absolute)
        $enqueueAssetsFile = get_stylesheet_directory() . "/inc/enqueue-assets.php";
        $content = "<?php\n";
        foreach ($this->queue as $hook => $queueItems) {
            $content .= "// $hook\n";
            $content .= "add_action('$hook', function () {\n";
            // get the theme path at the time of enqueueing
            // why? Because if allows the theme path to be dynamic based on buidl (production/staging etc)
            $content .= "\$themepath = get_stylesheet_directory_uri();\n";
            foreach ($queueItems as $queueItem) {
                $func_name = $queueItem[0];
                $enqueueArgs = $queueItem[1];

                    list($handle, $src, $deps, $ver,$args) = $enqueueArgs;
                $condition = $queueItem[2] ?? null;
                $enqueueArgs[1] = "\$themepath". $enqueueArgs[1];
                $argsArrayString = json_encode($enqueueArgs, JSON_UNESCAPED_SLASHES);
                // args array string is the json encoded array of enqueue arguments
                // 1. handle
                // 2. src
                // 3. dependencies
                // 4. version
                // add theme path to the src
                if ($condition) {
                    $statement = $condition[0];
                    $argument = $this->getConditionArgumentForBuild($condition[1]);
                    $value = $this->getConditionValueForBuild($condition[2]);
                    // if condition is true, enqueue the asset
                    $content .= "if($statement($argument) == $value) {\n";
                    // enqueue the asset
                    $content .= "$func_name(...$argsArrayString);\n";
                    
                    // enqueue accompanying stylesheet, if it exists
                    $content .= self::enqueueAccompanyingStylesheet_build($handle, $src, $deps, $ver);
                    $content .= "}\n";
                } else {
                    $content .= "$func_name(...$argsArrayString);\n";
                    $content .= self::enqueueAccompanyingStylesheet_build($handle, $src, $deps, $ver);
                }
            }
            $content .= "});\n\n";
        }
        file_put_contents($enqueueAssetsFile, $content);
    }

    private static function enqueueAccompanyingStylesheet_build($handle, $src, $deps, $ver) : string{
        // enqueue accompanying stylesheet, if it exists
       $cssSrc = str_replace('.js', '.css', $src);
        if(file_exists(get_stylesheet_directory() . $cssSrc)){
            return "wp_enqueue_style('".$handle . "-style', get_stylesheet_directory_uri() . '".$cssSrc."', ".json_encode($deps).", '$ver');\n";
        } 
        return '';
    }

    private static function enqueueAccompanyingStylesheet_dev($handle, $src, $deps, $ver) : void{
        // enqueue accompanying stylesheet, if it exists
      $cssSrc = str_replace('.js', '.css', $src);
        if(file_exists(get_stylesheet_directory() . $cssSrc)){
            wp_enqueue_style($handle . '-style', get_stylesheet_directory_uri() . $cssSrc, $deps, $ver);
        } 
    }

    /**
     * Build the array of assets to be enqueued
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
        if (!is_array($assetsForHooks)) {
            throw new \Exception("assets argument must be an array in order to enqueue your custom scripts and styles");
        }
        // 1. loop through the assets for the hook and enqueue them
        foreach ($assetsForHooks as $hook => $assets) {
            // initialize the queue item if it does not exist
            if (!isset($this->queue[$hook])) {
                $this->queue[$hook] = [];
            }
            // loop through the assets and enqueue them
            foreach ($assets as $assetData) {
                if (!is_array($assetData)) {
                    continue;
                }
                // get the enqueue item
                $queueItem = $this->pushEnqueueAsset($assetData);
                // push the enqueue item to the queue
                if ($queueItem) {
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
        if (!is_array($assetData)) {
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
        // $srcPath = $this->getSrcPath($assetRelativePath, $ext);
        $src = $assetRelativePath . "." .$ext;
        // get deps
        $deps = $assetData['dependencies'] ?? [];
        // prepare enqueue arguments
        // for more on enqueue arguments, @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
        $enqueueArgs = [ $handle, $src, $deps];
        // get the absolute path for the asset
        $asset = get_stylesheet_directory() . $assetRelativePath . ".asset.php";
        if (!file_exists($asset)) {
            // throw an error if the asset file does not exist
            throw new \Exception("Asset file does not exist: " . $asset);
        }
        // get the asset meta data
        $assetMeta = require $asset;
        // add the asset meta data to the enqueue arguments
        $enqueueArgs = [
            ...$enqueueArgs,
            // $assetMeta['dependencies'] ?? [],
            $assetMeta['version'] ?? false,
            $assetMeta['in_footer'] ?? false
        ];
        // Note: in development, we don't need to enqueue the asset file
        // we use the src path to load the asset from the dev server (achieving HMR)
        // 4. finally, we enqueue the asset
        $func_name = "wp_enqueue_script";
        // call_user_func_array($func_name, $enqueueArgs);
        return [  $func_name, $enqueueArgs, $condition];
    }





    /**
     * Get the src path of the asset
     *
     * @param string $assetRelativePath
     * @param string $ext
     * @return string
     */
    private function getSrcPath(string $assetRelativePath, string $ext): string
    {
        return  $assetRelativePath . "." .$ext;
    }

    /**
     * Evaluate the condition provided in the assets.json file
     *
     * The condition is an array with the following structure:
     * [condition, argument, value] where:
     * - condition: the function to check against
     * - argument: the argument to pass to the function
     * - value: the value to check the function against
     *
     *
     * # Example - String as an argument
     *
     * [ 'is_page_template', 'template-about.php', true]
     * is evaluated as: is_page_template('template-about.php') == true
     *
     * # Example - Function as an argument
     *
     * [ 'is_page_template', ['function', 'get_page_template_slug', $post_id], "my-template-slug""]
     *
     *
     *
     * @param array|null $condition
     * @return boolean
     */
    private function evalCondition(?array $condition): bool
    {

        if (!$condition) {
            return true;
        }

        // get the function to check against
        $func = $this->getConditionFunction($condition[0] ?? null);
        // get the argument to check against
        $argument = $this->getConditionArgument($condition[1] ?? null);
        // value to check expression against (defaults to true)

        $value = $condition[2] ?? true;
        // echo "condition: $func($argument) == $value";
        // echo "func: $func";
        // echo "result: " .
        return call_user_func($func, $argument) == $value;
    }

    /**
     * Get condition argument
     *
     *  get the argument. The argument can be any value but also a function if
     * the argument is an array with the first element describing the type of argument,
     *  the second element is the function name, and the rest of the elements are the arguments
     * i.e. ['function', 'is_page_template', ...args]
     *
     *
     * The argument value type can be any value, but also a function or variable
     * if the type is specified.
     *
     * # Example - Function as an argument
     *
     * [ 'is_page_template', ['function', 'get_page_template_slug', $post_id], "my-template-slug""]
     *
     * # Example - Variable as an argument
     *
     * [ 'is_page_template', '$post_id', "my-template-slug"]
     *
     * If you want to use a variable as an argument, you can use a string with a $ prefix. Note that
     * all variables must have global scope.
     * To pass in a value that starts with a $, you can escape it with a backslash.
     *
     *
     * @param [type] $argument
     * @return mixed
     */
    private function getConditionArgument($argument): mixed
    {
        // if the argument is an array and the first element is 'function'
        if ($this->isArgumentFunction($argument)) {
            $func = $argument[1];
            $args = array_slice($argument, 2);
            // resolve the function argument
            $argument = call_user_func($func, ...$args);
        }
        // if the argument is a string but the first character is a $, return the variable
        if (is_string($argument) && substr($argument, 0, 1) === '$') {
            $argument = $GLOBALS[substr($argument, 1)];
            return $argument;
        }

        return $argument;
    }

    private function isArgumentFunction($argument): bool
    {
        return is_array($argument) && count($argument) > 1 && $argument[0] === 'function';
    }

    private function getConditionFunction($func): callable
    {
        // check if the function exists
        if (!is_callable($func)) {
            throw new \Exception("Function $func is not callable.");
        }
        return $func;
    }

    /**
     * Get the condition argument for the build enqueue-assets.php
     *
     * @param [type] $argument
     * @return string
     */
    private function getConditionArgumentForBuild($argument): string
    {

        // if the argumnet is a function, write the function call
        if ($this->isArgumentFunction($argument)) {
            $statement = $argument[1];
            $args = array_slice($argument, 2);

            $args = json_encode($args, JSON_UNESCAPED_SLASHES);
            $argument = "$statement($args)";
        } elseif (is_array($argument)) {
            $argument = json_encode($argument, JSON_UNESCAPED_SLASHES);
        }
        // if the argument is null, return an empty string
        if ($argument === null) {
            return '';
        }
        return $argument;
    }

    private function getConditionValueForBuild($value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            $value = "'$value'";
        }
        return $value;
    }

}
