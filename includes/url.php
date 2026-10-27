<?php

declare(strict_types=1);

function appConfig(string $key, mixed $default = null): mixed
{
    return $GLOBALS['appConfig'][$key] ?? $default;
}

function baseUrl(string $path = ''): string
{
    $basePath = rtrim((string) appConfig('base_path', ''), '/');
    $cleanPath = ltrim($path, '/');

    if ($cleanPath === '') {
        return $basePath . '/';
    }

    return $basePath . '/' . $cleanPath;
}

function redirectTo(string $path, int $statusCode = 302): never
{
    // Redirects stay inside this application so user input cannot create an open redirect.
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1 || str_starts_with($path, '//')) {
        throw new InvalidArgumentException('External redirect targets are not allowed.');
    }

    header('Location: ' . baseUrl($path), true, $statusCode);
    exit;
}
