<?php


namespace Aslamhus\WordpressHMR;


class WHRJson
{

    public static function save(string $filePath, array $json)
    {

        if (!file_exists($filePath)) {
            throw new \Exception("whr.json does not exist at " . $filePath);
        }
        file_put_contents($filePath, json_encode($json, JSON_UNESCAPED_SLASHES));
    }

    public static function get(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("whr.json does not exist at " . $filePath);
        }
        $jsonString = file_get_contents($filePath);
        return json_decode($jsonString, true);
    }
}
