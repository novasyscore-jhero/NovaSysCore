<?php

namespace NovaSysCore;

class Url
{
    public static function to(string $path = '/'): string
    {
        $basePath = Config::get(
            'app.base_path'
        );

        if (!is_string($basePath)) {
            $basePath = '';
        }

        $basePath = rtrim(
            $basePath,
            '/'
        );

        $path = '/' . ltrim(
            $path,
            '/'
        );

        if ($path === '/') {
            return $basePath !== ''
                ? $basePath . '/'
                : '/';
        }

        return $basePath . $path;
    }
}
