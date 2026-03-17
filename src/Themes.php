<?php

namespace Aslamhus\WordpressHMR;

class Themes
{

    public array $themes = [];

    public function __construct()
    {
        $this->themes = self::getThemes();
    }


    public static function getThemes(): array
    {
        $themes = [];
        CLI::exec("wp theme list --fields='name'", $themes);
        // remove table names from output
        array_shift($themes);
        return $themes;
    }


    public static function getActiveTheme(): string
    {
        $output = [];
        CLI::exec("wp theme list --fields='name' --status=active", $output);
        // remove table names from output
        array_shift($output);
        return $output[0];
    }

    public static function setActiveTheme(string $themeSlug)
    {
        if (!CLI::exec("wp theme activate $themeSlug")) {
            throw new \Exception('Failed to active theme ' . $themeSlug);
        }
    }


    public static function search(string $theme): mixed
    {
        $search = [];
        $theme = str_replace(' ', '', $theme);
        CLI::exec("wp theme search $theme --fields='slug,name' --page=1 --per-page=15", $search);


        return $search;
    }

    public static function install(string $theme): bool
    {
        $result_code = 1;
        $output = "";
        CLI::exec("wp theme install $theme", $output, $result_code);
        if ($result_code !== 0) {
            CLI::log("Error installing theme: " . $output);
            return false;
        }
        return $result_code == 0;
    }

    public static function scaffoldChildTheme(string $childSlug, string $parentSlug)
    {

        CLI::log("Scaffolding child theme of $parentSlug with slug $childSlug");
        return CLI::exec("wp scaffold child-theme $childSlug --parent_theme='$parentSlug' --activate");
    }
}
