<?php

namespace NovaSysCore;

class Config
{
    protected static array $config = [];


    public static function load(string $name, string $file): void
    {
        self::$config[$name] = require $file;
    }


    public static function get(string $key)
    {
        $parts = explode('.', $key);

        $file = $parts[0];
        $item = $parts[1] ?? null;


        if (!isset(self::$config[$file])) {
            return null;
        }


        if ($item === null) {
            return self::$config[$file];
        }


        return self::$config[$file][$item] ?? null;
    }
}