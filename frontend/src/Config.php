<?php
declare(strict_types=1);

namespace App;

class Config
{
    public static function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return isset($_ENV[$key]) ? (int)$_ENV[$key] : $default;
    }

    public static function require(string $key): string
    {
        if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
            throw new \RuntimeException("Pflicht-Variable '$key' fehlt in .env");
        }
        return $_ENV[$key];
    }
}
