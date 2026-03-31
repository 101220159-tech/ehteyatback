<?php

namespace App\Support;

final class Frontend
{
    public static function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $path === '' || $path === '/' ? $base : $base.'/'.ltrim($path, '/');
    }
}
