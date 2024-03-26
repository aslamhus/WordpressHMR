# Wordpress Development setup with HMR and WP-Scripts

## Introduction

This is a simple setup for developing wordpress themes with webpack and hot module replacement (HMR). It uses the `wp-scripts` package in tandem with a custom enqueuing algorithm to enqueue assets in your theme.

### How does it work?

This package uses the assets you define in the `assets.json` file to generate the necessary webpack configuration to build your assets. In development, each asset is enqueued with a hard coded path to your local development server. When you make changes to your assets, webpack will automatically rebuild your assets and the browser will refresh to reflect the changes. In production, the assets are enqueued with the correct path to your theme directory in a Wordpress compliant manner.

Styles are not enqueued seprately with the wp_enqueue_style hook, but rather as imported dependencies in your javascript files. This allows us to use HMR for styles as well.

In the assets.json file you can specify conditions for enqueuing scripts. For example, you can specify that a script should only be enqueued on a specific page template or in the block editor.

## Requirements

- Node.js
- MAMP / XAMPP / WAMP

## Installation

### MAMP setup

Create local domain for your wordpress site. For example `localhost.mysite` where
the document root is the public folder of your wordpress site.

1. Add the following to your `httpd-vhosts.conf` file.

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

### Wordpress setup

1. Use wp-cli to install wordpress.

   ```bash
   wp core download
   ```

2. Go to your wordpress site and install the site.

   If you have difficulty connecting to the database, try specifying the port number in the `DB_HOST` constant. Make sure this is the correct port based on your MAMP/XAMPP/WAMP setup.

   ```php
   /** Database hostname */
   define( 'DB_HOST', '127.0.0.1:8889' );
   ```

3. Set your config env to development

   ```php
   define( 'WP_ENVIRONMENT_TYPE', 'development' )
   ```

### Setup the theme

Login into your wordpress site and activate your custom theme.

### Configure asset.json

```json
{
  "config": {
    "handlePrefix": "custom-asset",
    "host": "local.yourdomain",
    "port": "8888",
    "protocol": "http",
    "themePath": "/wp-content/themes/your-theme-directory"
  },
  "scripts": [
    {
      "handle": "editor-js",
      "hooks": ["enqueue_block_editor_assets"],
      "path": "/js/editor",
      "ext": "js"
    },
    {
      "hooks": ["wp_enqueue_scripts"],
      "path": "/js/screen",
      "ext": "js",
      "condition": ["get_page_template_slug", "", "page-with-featured-image-header"]
    }
  ]
}
```

## Start development

1. Install dependencies

   ```bash
   npm install
   ```

2. Start development server

   ```bash
   npm start
   ```

## Build

Build outputs to your public folder, in your theme path.

The theme path is defined in the `asset.json` file.

**_WARNING_**: You can set the webpack `output` option to `clean` the public folder before building, however if you have not defined the public folder correctly, you may end up deleting unintended files. Please test your configuration first before enabling `clean`.

`clean` is set to false by default to ensure that you do not accidentally delete files.

```bash
npm run build
```

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

## The assets.json file

The `assets.json` file is where you define your assets. It is a simple JSON file that contains the following properties:

- `config` - This is where you define the configuration for your assets.
- `scripts` - This is where you define your scripts.
- `styles` - This is where you define your styles.

### Config

- `handlePrefix` - This is the prefix for your asset handles. This is useful if you want to avoid conflicts with other plugins or themes.
- `host` - This is the host of your local development server.
- `port` - This is the port of your local development server.
- `protocol` - This is the protocol of your local development server.
- `themePath` - This is the path to your theme directory.

### Scripts

An array of script objects. Each script object contains the following properties:

- `handle` - The handle of the script. This is the name that you will use to enqueue the script.
- `hooks` - An array of hooks where the script will be enqueued.
- `path` - The path to the script file.
- `ext` - The file extension of the script file.
- `condition` - a condition expression, which is an array of 3 elements. The first element is the function name, the second element is the function argument, and the third element is the expected value. If the condition is met, the script will be enqueued.

## Potential Improvements

For a small number of assets, the performance overhead of the enqueue algorithm s negligible. However, for a very large number of assets, it would be better to perform the algorithm in a single pass and create an array of enqueued assets. This way we can avoid multiple iterations over the assets array.

## TODO

- TO get HMR to work with wp-scripts, you need to build the assets first in order to generate the asset.php files. (explain this further)
