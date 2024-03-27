# Wordpress Development setup with HMR and WP-Scripts

## Introduction

This package allows you to develop wordpress themes with `Webpack` and hot module replacement (HMR). It uses the `wp-scripts` package in tandem with a custom enqueuing algorithm to enqueue assets in your theme.

Defining and enqueing assets is as simple as managing a single `assets.json` file. This file contains the configuration for your assets, including the path to your scripts and styles, the hooks where they should be enqueued, and any conditions for enqueuing them.

## Why not just use `wp-scripts`?

`wp-scripts` is a great package for managing assets in your theme, but it lacks the ability to hot reload styles and scripts. While you can leverage hot module replacement in block development, it is not currenntly possible to do so for theme development. This package combines the power of `wp-scripts` to manage asset dependencies with a dynamic enqueing algorithm to provide hot module replacement for your theme development.

### Example

```json
{
  "config": {
    "handlePrefix": "custom-asset",
    "host": "local.mysite",
    "site": "http://local.mysite:8888",
    "port": "8888",
    "protocol": "http",
    "themePath": "/wp-content/themes/my-custom-theme"
  },
  "assets": {
    "enqueue_block_editor_assets": [
      {
        "handle": "editor-js",
        "path": "/js/editor",
        "ext": "js"
      }
    ],
    "wp_enqueue_scripts": [
      {
        "path": "/js/screen",
        "ext": "js",
        "condition": ["is_user_logged_in", null, false]
      }
    ]
  }
}
```

### How does it work?

This package uses the assets you define in the `assets.json` file to generate the necessary webpack configuration to build your assets. In development, each asset is enqueued dynamically using a path to a proxy development server. When you make changes to your assets, webpack will provide hot updates without requiring a reload (unless you change javascript). In production, the assets are statically enqueued with the correct path to your theme directory in an efficient and Wordpress compliant manner.

#### Styles

Styles are not enqueued separately with the wp_enqueue_style hook, but rather as imported dependencies in your javascript files. This allows us to use HMR for styles as well.

#### Conditional enqueuing

In the assets.json file you can specify conditions for enqueuing scripts. For example, you can specify that a script should only be enqueued on a specific page template or in the block editor.

## Requirements

- Node.js
- MAMP / XAMPP / WAMP

## Pre Installation

### Setup your apache server

#### Example: MAMP

Create local domain for your wordpress site. For example `localhost.mysite` where
the document root is the public folder of your wordpress site `project-root/public`

1. If using MAMP, Add the following to your `httpd-vhosts.conf` file.

   ```bash
   <VirtualHost *:8888>
       DocumentRoot "/path/to/your/project-root/public"
       ServerName localhost.mysite
   </VirtualHost>
   ```

2. Add the following to your `hosts` file (sudo nano /etc/hosts)

   ```bash
   127.0.0.1 local.mysite
   ```

## Install

Once you have completed the pre-installation instructions above, you are ready to install the package and its dependencies.

1. Install the package in the root directory of your project

   ```bash
   composer require aslamhus/wordpress-hmr
   ```

2. Run the installer script. You can find installer.php file in the package's root directory (vendor/aslamhus/wordpress-hmr/installer.php). Move this file to the root directory of your wordpress site and run it on the command line. This will install the necessary files in your theme directory as well as the wepback configuration files.

   ```bash
   # cd into your project root
   cd /path/to/your/project-root
   # move the installer
   mv vendor/aslamhus/wordpress-hmr/installer.php ./installer.php
   # run the installer
   php installer.php
   ```

3. Install `npm` dependencies. This will install the necessary packages for development.

   ```bash
   npm install
   ```

4. Rename `assets.sample.json` file to `assets.json` and configure it with your development settings (see pre-installation instructions above for setting up your apache server and defining a local domain for your wordpress site)

   ```json
   {
     "config": {
       "handlePrefix": "custom-asset",
       "host": "local.mysite",
       "site": "http://local.mysite:8888",
       "port": "8888",
       "protocol": "http",
       "themePath": "/wp-content/themes/my-custom-theme"
     }
   }
   ```

   For more details on the `config` object properties, see the [Define your own assets for your theme](#define-your-own-assets-for-your-theme) section below.

5. Generate `wp-scripts` asset dependencies.

   Before we get started with development, we need `wp-scripts` to do some setup. Run the following command to generate the necessary asset files.

   ```bash
   npm run build
   ```

   **_NOTE:_** Our setup leverages `wp-scripts` in order to manage asset dependencies. Each asset specified in the wepback configuration will generate it's own asset file `[filename].asset.php` which defines the asset's dependencies. For more information on the `wp-scripts` package, see [wp-scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).

### Develop with HMR

1. Start `apache` server with MAMP/WAMP/XAMP.

2. Start the proxy (development) server

   After you run the following command, Wordpress' installation prompt should appear when your proxy server opens the url. Follow the instructions to install wordpress.

   ```bash
   npm run start
   ```

3. Once the install is complete, activate your custom theme in your wordpress site.

That's it! Try changing the `src/js/screen.js` file and see the changes reflected in your browser. By default, the package adds two scripts to your theme, `screen.js` and `editor.js`. Each of these scripts is defined in the `assets.json` file with conditions to enqueue them in the public facing area of your wordpress site nd editor respectively. Each script imports a correspdonding css file in your theme. Add more scripts or more css / scss files as you like. Try updating the css and see the changes reflected without a reload.

### Build for production

Building is a very simple process, and leverages `wp-scripts` to generate the necessary asset files. A little magic is performed by `Aslamhus\WordpressHMR\EnqueueAssets` to create a static script which enqueues the assets in your theme, avoiding the performance overhead of enqueuing assets dynamically.

```bash
npm run build
```

### Troubleshooting the setup

1. If you have difficulty connecting to the database, try specifying the port number in the `DB_HOST` constant. Make sure this is the correct port based on your MAMP/XAMPP/WAMP setup.

   ```php
   /** Database hostname */
   define( 'DB_HOST', '127.0.0.1:8889' );
   ```

2. Set your config env to development

   ```php
   define( 'WP_ENVIRONMENT_TYPE', 'development' )
   ```

### Define your own assets for your theme

The `assets.json` file is where you define your assets. It is a simple JSON file that contains the following properties:

- `config` - This is where you define the configuration for your assets.
- `assets` - This is where you define your scripts. Each property of the `assets` object is a hook where you want to enqueue your script. The value of each property is an array of script objects.

### Config

- `handlePrefix` - This is the prefix for your asset handles. This is useful if you want to avoid conflicts with other plugins or themes.
- `host` - This is the host of your local development server.
- `port` - This is the port of your local development server.
- `protocol` - This is the protocol of your local development server.
- `themePath` - This is the path to your theme directory.

### Assets

An array of script objects. Each script object contains the following properties:

- `handle` - The handle of the script. This is the name that you will use to enqueue the script.
- `hooks` - An array of hooks where the script will be enqueued.
- `path` - The path to the script file.
- `ext` - The file extension of the script file.
- `condition` - a condition expression, which is an array of 3 elements. The first element is the function name, the second element is the function argument, and the third element is the expected value. If the condition is met, the script will be enqueued.

```json
{
  "assets": {
    "enqueue_block_editor_assets": [
      {
        "handle": "editor-js",
        "path": "/js/editor",
        "ext": "js"
      }
    ],
    "wp_enqueue_scripts": [
      {
        "path": "/js/screen",
        "ext": "js",
        "condition": ["is_user_logged_in", null, false]
      }
    ]
  }
}
```

### Adding a new asset

To add a new asset, simply add a new object to the `assets` array in the `assets.json` file. You will have to run `npm run build` to generate the necessary asset files.

Let's add some styles to our theme which we only want to appear on our custom template, "My Custom Template".

1. Create a scss/css file in your `resources/assets/css` directory. For example, `my-custom-template.scss`.

2. Create a script in your `resources/js` directory that will import the scss file. For example, `my-custom-template.js`.

   ```js
   import '../css/my-custom-template.scss';
   ```

3. Add the following to your `assets.json` file:

   ```json
   {
     "assets": {
       "wp_enqueue_scripts": [
         {
           "path": "/css/custom-template",
           "ext": "css",
           "condition": ["is_page_template", "My Custom Template", true]
         }
       ]
     }
   }
   ```

4. Run `npm run build` to generate the necessary asset files.

5. Restart your development server with `npm run start`. The styles should now be enqueued on the "My Custom Template" page.

You're done! Try changing the styles in your `my-custom-template.scss` file and see the changes reflected in your browser only on pages that use the "My Custom Template" page template.

For a list of condition functions that you can use, see the [Wordpress Conditional Tags](https://developer.wordpress.org/themes/basics/conditional-tags/).

## Modifying the Webpack configurations

**_WARNING_**: You can set the webpack `output` option to `clean` the public folder before building, however if you have not defined the public folder correctly, you may end up deleting unintended files. Please test your configuration first before enabling `clean`.

`clean` is set to false by default to ensure that you do not accidentally delete files.

## Directory Structure

```bash
├── public (This is the public folder of your wordpress site)
│   ├── wp-content
│   │   ├── themes
│   │   │   ├── your-theme-directory (this is where webpack will output the build files)
│   resources (source files for your theme)
|   ├── js (entry points for your js files)
|   ├── assets
|   │   ├── css
|   │   ├── assets.json (This is where your asset.json file is located)
|   │   ├── assets.php (load the assets.json file in your theme)
|   ├── inc (your theme includes)
|   │   ├── enqueue-assets.php (where the enqueue magic happens!)
```

The following files are required in your theme:

- `assets/assets.json` - This is where you define your assets.
- `assets/assets.php` - This is where you load the assets.json file.
- `inc/enqueue-assets.php` - This is where you enqueue your assets.
