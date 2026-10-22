<?php
declare(strict_types=1);

namespace App;

final class Config
{
    /** Read an env var, falling back to a local .env file at the repo root. */
    public static function get(string $key, ?string $default = null): ?string
    {
        static $file = null;
        if ($file === null) {
            $file = [];
            $path = dirname(__DIR__, 2) . '/.env';
            if (is_readable($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if ($line[0] === '#' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $file[trim($k)] = trim($v);
                }
            }
        }
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $file[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            throw new \RuntimeException("Missing required config: {$key}");
        }
        return $v;
    }
}
