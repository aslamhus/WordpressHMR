<?php

namespace Aslamhus\WordpressHMR;

class CLI
{

    public static $command = 'vendor/bin/whr';
    public static $colors = [
        'Black'       =>           '0;30',
        'White'        =>          '1;37',
        'Dark Grey'    =>         '1;30',
        'Red'        =>           '0;31',
        'Green'     =>            '0;32',
        'Brown'     =>            '0;33',
        'Yellow'    =>            '1;33',
        'Blue'      =>            '0;34',
        'Magenta'   =>            '0;35',
        'Cyan'      =>            '0;36',
        'Light Cyan' =>           '1;36',
        'Light Grey'   =>         '0;37',
        'Light Red'     =>       '1;31',
        'Light Green'    =>       '1;32',
        'Light Blue'     =>       '1;34',
        'Light Magenta'  =>       '1;35'
    ];
    public static $defaultLogColor = '1;30';

    // # Background Colors     Code
    // # ---------------------------
    // # Black                 40
    // # Red                   41
    // # Green                 42
    // # Yellow                43
    // # Blue                  44
    // # Magenta               45
    // # Cyan                  46
    // # Light Grey            47

    public static function exec(string $command, &$output = [], int &$result_code = 0): bool
    {

        exec(self::$command . ' ' . $command, $output, $result_code);
        return $result_code == 0;
    }

    public static function log($message, $color = '')
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        if (!$color) {
            $color = self::$defaultLogColor;
        }
        echo "\033[" . $color . "m" . $message . "\033[0m" . PHP_EOL;
    }

    public static function read($prompt = ""): string
    {


        self::log($prompt); // add a new line
        return trim(fgets(STDIN));
    }

    public static function confirm(string $message): bool
    {
        $input = CLI::read("$message [y/n]");
        return $input == 'y';
    }
}
