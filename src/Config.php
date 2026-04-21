<?php
declare(strict_types=1);

namespace Stain;

final class Config
{
    public static function get(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException("Missing config value: {$key}");
        }

        return (string) $value;
    }
}
