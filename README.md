# Wordpress Development setup with HMR and WP-Scripts

## Introduction

This is an experimental package inspired by `wp-env`, which allows you to develop containerized wordpress sites with `Docker`, `Webpack` and `hot module replacement (HMR)`. It uses the Wordpress `wp-scripts` package in tandem with a custom enqueuing algorithm to enqueue assets in your theme.

WordpressHMR now comes with a CLI tool that allows you to spin up a containerized wordpress site with one command.

Defining and enqueing assets is as simple as managing a single `whr.json` file. This file contains the configuration for your assets, including the path to your scripts and styles, the hooks where they should be enqueued, and any conditions for enqueuing them.

## Who is this package for?

Anyone who wants to develop Wordpress block themes with HMR for a fast paced development in a containerized environment.

## Gettings started

This package requires [Docker](https://www.docker.com/get-started/), [PHP](https://www.ionos.ca/digitalguide/websites/web-development/install-php/), [Composer](https://getcomposer.org/download/), and [Node.js](https://nodejs.org/en).

1. Install WordpressHMR package

```bash
composer require --dev aslamhus/wordpress-hmr
```

2. Run the installer and follow the prompts.

```bash
vendor/bin/whr install
```

3. Create a child theme

The default installation creates a wordpress site for you, but it is recommended that you create a child theme. To do so, simply run:

```bash
vendor/bin/whr create-child-theme
```

Follow the prompts to use a default theme or install your own. If you want to manually install a theme, simply add its directory to the `public/wp-content/themes` directory in your project.

4. Start hot module replacement!

```bash
vendor/bin/whr start --hot
```

That's it! Try changing the css in your `resources/assets/screen.scss` file to see the changes immediately reflected in your browser. By default, the package adds two scripts to your theme, `screen.js` and `editor.js`. Each of these scripts is defined in the `whr.json` file with conditions to enqueue them in the public facing area of your wordpress site nd editor respectively. Each script imports a correspdonding css file in your theme. Add more scripts or more css / scss files as you like. Try updating the css and see the changes reflected without a reload.

## Hot module replacement entry points

Currently, this package only supports HMR for a single entry point per page. This means that if you have multiple entry points on a single page, only the first entry point will be hot reloaded. You can by all means have as many entry points as you like, but only the first one will benefit from HMR.

In development, I recommend having a single entry point per page so that you can take advantage of the fast paced development experience of HMR, and then use a separate config for your production environment. This can easily be faciliated with separate `whr.json` files, according to your environment.

### Why not just use `wp-scripts`?

`wp-scripts` is a great package for managing assets in your theme, but it lacks the ability to hot reload styles and scripts. While you can leverage hot module replacement in custom block development, it is not currently possible to do so for theme development. This package combines the power of `wp-scripts` to manage asset dependencies with a dynamic enqueing algorithm to provide hot module replacement for your theme development.

## Example

```js
{
  "config": {
    "handlePrefix": "my-asset-handle",
    "host": "localhost",
    "site": "http://localhost:8889",
    "port": "8889",
    "protocol": "http",
    "theme": "twentytwentyfive",
    "src": "resources",
    "themePath": "public/wp-content/themes/"
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

In development, each asset is enqueued dynamically using a path to a proxy dev server. When you make changes to your assets, webpack will provide hot updates. When you build your theme, the assets are statically enqueued in your theme directory in an efficient and Wordpress compliant manner.

### Start developing!

```bash
# start your container
vendor/bin/whr start
# start your container with HMR
vendor/bin/whr start --hot
```

### Build for production

Building is a very simple process, and leverages `wp-scripts` to generate the necessary asset files. A little magic is performed by `Aslamhus\WordpressHMR\EnqueueAssets` to create a static script which enqueues the assets in your theme, avoiding the performance overhead of enqueuing assets dynamically. Run the build command and then check out your enqueue-assets.php file in your build folder `public/wp-content/themes/your-theme/inc/enqueue-assets.php`

```bash
vendor/bin/whr build
```

#### Styles

Adding styles is as simple as importing a css file to your entry point. For example, see the default screen.js file:

```js
import "../assets/css/screen.scss";
```

These styles are extracted by webpack and enqueued separately with the same condition you describe for the entry-point (in this case, screen.js) in your whr.json file.

#### Conditional enqueuing

In the whr.json file you can specify conditions for enqueuing scripts. For example, you can specify that a script should only be enqueued on a specific page template or in the block editor.
Conditional enqueuing can get complicated, so let's break it down with examples.

##### Example 1: Enqueue a script only on the block editor

In this example, we only want to enqueue a script in the block editor. We set the path to the script file as well as the extension. We don't need to specify a condition for this script, as it will be enqueued in the block editor by default.

```json
{
  "assets": {
    "enqueue_block_editor_assets": [
      {
        "handle": "editor-js",
        "path": "/js/editor",
        "ext": "js"
      }
    ]
  }
}
```

##### Example 2: Enqueue a script only on the front end

Same as above, but we want to enqueue a script only on the front end. We use the `wp_enqueue_scripts` hook to enqueue the script.

```json
{
  "assets": {
    "wp_enqueue_scripts": [
      {
        "path": "/js/screen",
        "ext": "js"
      }
    ]
  }
}
```

##### Example 3: Enqueue a script only on a specific page template

In this example, we only want to enqueue a script on a specific page template. We use the `get_page_template_slug` function to get the page template slug and compare it to our custom template slug.
You can find the page template slug by looking at the classname of the body tag of any page given the template.

```js
// for custom template
{
  "assets": {
    "wp_enqueue_scripts": [
      {
        "path": "/js/screen",
        "ext": "js",
        "condition": ["get_page_template_slug", "", "my-custom-template"]
      }
    ]
  }
}
// for default page template
  {
        "handle": "pages-feature-image",
        "path": "/js/templates/pages-feature-image.js",
        "ext": "js",

        "condition": ["get_page_template_slug", null, "page-template-default"]
      }

// for a custom post type
{
    "handle": "instructor-js",
    "path": "/js/instructor-single",
    "ext": "js",
    "condition": ["get_post_type", ["function", "get_the_id"], "custom-instructors"]
  }

// get the id of the page
{
 "condition": ["get_the_id", null, "2039"]
}

// targeting block editor styles only
 "enqueue_block_assets": [
      {
        "handle": "editor-js",
        "path": "/js/editor",
        "ext": "js",
        "condition": ["is_admin", null, true]
      }
    ],
```

##### Example 4: Use a conditional argument that takes the result of a function as an argument

You can use a function as an argument by declaring the type in an array, followed by the function name, and then a list of optional arguments.

In this example, we only want to enqueue an asset on the about page. We need the id value of the current page to use the `get_the_title` method. We can use the `get_the_id` function to get the id of the current page.

This will evaluate to `get_the_title(get_the_id()) === 'about'`.

```json
{
  "assets": {
    "wp_enqueue_scripts": [
      {
        "path": "/js/screen",
        "ext": "js",
        "condition": ["get_the_title", ["function", "get_the_id"], "about"]
      }
    ]
  }
}
```

### Project directory structure

The installer will create the following folders/files:

- `Docker` directory, which serves as an entrypoint for files in your project root and the docker container.
- `public` directory, with the latest version of wordpress installed. The installer will also prompt you to create a custom theme directory with the name you specify.
- `resources` directory where all your theme files will reside. Webpack copies all the files from your resources folder to the current active theme.
- `resources/assets` directory where your `whr.json` file and css / scss files will reside.
- `resources/inc` directory where the `enqueue-assets.php` and `functions.php` file will reside.
- `resources/js` directory where your entry points will reside.
- `style.css` file in the root of your theme directory with the template name of your custom theme.
- `vendor` directory where your composer packages are stored. This directory is mounted in the docker container in the parent directory of your wordpress site (/var/www/).

### Accessing Docker containers

#### Commands

`WordpressHMR` provides some convenient commands to access your container. To run `wp-cli` commands inside your container you can use:

```bash
vendor/bin/whr wp --info
```

To run commands inside your wordpress container you can use:

```bash
vendor/bin/whr exec <commands>
```

Note that not all common binaries exist in the wordpress container. Therefore, it's recommended to run commands in the wordpress container like so:

```bash
vendor/bin/whr exec bash -c 'cd ../ && ls -1'
```

#### Container entry points

Your containers can access files in your local project through `Docker/data/tmp`. You'll find any files you add to this directory at the container path `/tmp/Docker`. If you want to change this directory, please see `volumes` in your `compose.yml` file, located in your project root.

When is this useful? Say you want to import / export a database. Add your .sql dump file to Docker/data/tmp in your project. Then import it into your container's database by running `vendor/bin/whr wp db import /Docker/tmp`.

Likewise, you can export your database by running `vendor/bin/whr wp db export /tmp/Docker/dump.sql`

### Troubleshooting

#### Webpack Errors

If you encounter an error such as `Module parse failed: 'import' and 'export' may appear only with 'sourceType: module'`, please make sure your root directory of your project does not contain a package.json file where the type is set to "commonjs". While `aslamhus/wordpress-hmr` uses ES6 syntax for its webpack configuration, `wp-scripts` uses CommonJS and specifiying a type can cause errors.

### whr.json

The `whr.json` file is where you define your assets. It is a simple JSON file that contains the following properties:

- `config` - This is where you define the configuration for your assets.
- `assets` - This is where you define your scripts. Each property of the `assets` object is a hook where you want to enqueue your script. The value of each property is an array of script objects.

### Config

- `handlePrefix` - This is the prefix for your asset handles. This is useful if you want to avoid conflicts with other plugins or themes.
- `host` - This is the host of your local development server.
- `port` - This is the port of your local development server.
- `protocol` - This is the protocol of your local development server.
- `theme` - This is the name of your theme directory, i.e. twentytwentyfour

### Assets

An array of script objects. Each script object contains the following properties:

- `handle` - The handle of the script. This is the name that you will use to enqueue the script.
- `hooks` - An array of hooks where the script will be enqueued.
- `path` - The path to the script file.
- `ext` - The file extension of the script file.
- `dependencies` - An array of dependencies the script requires.
- `condition` - a condition expression, which is an array of 3 elements. The first element is the function name, the second element is the function argument, and the third element is the expected value. If the condition is met, the script will be enqueued. For more on conditional enqueuing, see the [Conditional enqueuing](#conditional-enqueuing) section below.

```json
{
  "assets": {
    "enqueue_block_editor_assets": [
      {
        "handle": "editor-js",
        "path": "/js/editor",
        "ext": "js",
        "dependencies": ["wp-hooks"]
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

To add a new asset, simply add a new object to the `assets` array in the `whr.json` file. You will have to run `vendor/bin/whr build` to generate the necessary asset files.

Let's add some styles to our theme which we only want to appear on our custom template, "My Custom Template".

1. Create a scss/css file in your `resources/assets/css` directory. For example, `my-custom-template.scss`.

2. Create a script in your `resources/js` directory that will import the scss file. For example, `my-custom-template.js`.

   ```js
   import "../css/my-custom-template.scss";
   ```

3. Add the following to your `whr.json` file:

   ```json
   {
     "assets": {
       "wp_enqueue_scripts": [
         {
           "path": "/js/custom-template",
           "ext": "js",
           "condition": ["is_page_template", "My Custom Template", true]
         }
       ]
     }
   }
   ```

4. Run `vendor/bin/whr build` to generate the necessary asset files.

5. Restart your development server with `vendor/bin/whr start`. The styles should now be enqueued on the "My Custom Template" page.

You're done! Try changing the styles in your `my-custom-template.scss` file and see the changes reflected in your browser only on pages that use the "My Custom Template" page template.

For a list of condition functions that you can use, see the [Wordpress Conditional Tags](https://developer.wordpress.org/themes/basics/conditional-tags/).

## A note on block theme hooks

When enqueuing assets for block themes, you will need to use the following hooks:

`enqueue_block_editor_assets` - to load only in editor view
`enqueue_block_assets` - loads both on frontend and editor view

## Modifying the Webpack configurations

You'll find tall he webpack config files in the `vendor/aslamhus/wordpress-hmr/build`.

## Contributing

If you would like to contribute to this package, please feel free to submit a pull request. I would love to hear your feedback and suggestions for improvement.

This package was proudly built without AI, just one single human clacking away at the keyboard.
