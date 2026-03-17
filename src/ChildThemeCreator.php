<?php

namespace Aslamhus\WordpressHMR;

class ChildThemeCreator
{



    public static function create()
    {
        // choose a parent theme and create child theme
        $chosenTheme =  self::chooseParentTheme();
        return self::createChildTheme($chosenTheme);
    }



    public static function installTheme(string $theme): mixed
    {

        CLI::log('Installing theme ' . $theme, CLI::$colors['Green']);
        if (Themes::install($theme)) {
            CLI::log("Succesfully installed $theme");
        } else {
            throw new \Exception("Failed to install $theme");
        }
        return $theme;
    }

    public static function searchTheme($theme): mixed
    {
        $chosenTheme = "";
        $search = Themes::search($theme);
        // search again if not found
        if (empty($search)) {
            CLI::log("No results for theme '$theme'. Search again? [y/n]");
            $confirm = CLI::read();
            if ($confirm == 'y') {
                $search = CLI::read("Search for a theme:");
                return self::searchTheme($search);
            }
        } else {
            // print search results and ask to
            CLI::log("");
            foreach ($search as $index => $line) {
                // print first title with table name "Success: Showing x of n themes
                if ($index == 0) {
                    CLI::log("$line");
                    continue;
                }
                // skip table name
                if ($index == 1) continue;
                // print search result
                $line = preg_split('/\s+/', $line);
                $slug = $line[0];
                array_shift($line);
                $name = implode(" ", $line);
                CLI::log($index - 1 . ". $name --- $slug");
            }
            CLI::log("");
            CLI::log("Which theme would you like to install?");

            $input = CLI::read("Type a number or a new theme to search for:");
            // select a theme by its index
            if (filter_var($input, FILTER_VALIDATE_INT)) {

                // Notes: return slug (each line is formatted NAME - SLUG )
                // add two to index to skip non-result lines (see above)
                $chosenTheme = preg_split('/\s+/', $search[$input + 1])[0];
            } else {
                // otherwise start over and search for a new theme
                return self::searchTheme($input);
            }
        }
        return $chosenTheme;
    }


    private static function chooseParentTheme()
    {
        $chosenTheme = '';
        echo "Choose a theme from the list below or type a theme slug to install";
        $themes = Themes::getThemes();
        foreach ($themes as $index => $theme) {
            echo "\n" . $index + 1 . ". $theme";
        }
        $input = CLI::read("");
        // if user types their own theme, search and install it
        if (!filter_var($input, FILTER_VALIDATE_INT)) {
            $chosenTheme = self::searchTheme($input);
            self::installTheme($chosenTheme);
        } else {
            $chosenTheme = $themes[(int) $input - 1];
        }
        return $chosenTheme;
    }



    private static function createChildTheme($parentSlug)
    {

        // ask user to choose a child theme name
        $childSlug = CLI::read('Enter child theme slug (no spaces or special characters): ');
        // remove special characters and spaces
        $childSlug = preg_replace('/[^A-Za-z0-9\-]/', '', $childSlug);
        // make child theme dir

        if (Themes::scaffoldChildTheme($childSlug, $parentSlug)) {
            CLI::log("Child theme $childSlug created", CLI::$colors['Green']);
        }
        return $childSlug;
    }
}
