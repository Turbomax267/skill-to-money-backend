<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function publicMediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) ? $parsedPath : $path;
        }

        $normalizedPath = ltrim($path, '/');
        $normalizedPath = preg_replace('#^(api/media|storage)/#i', '', $normalizedPath) ?? $normalizedPath;

        return request()->getSchemeAndHttpHost() . '/api/media/' . $normalizedPath;
    }
}
