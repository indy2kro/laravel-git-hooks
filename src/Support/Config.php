<?php

declare(strict_types=1);

namespace Igorsgm\GitHooks\Support;

/**
 * Typed accessors for the git-hooks configuration.
 *
 * Laravel's config() helper returns mixed, which requires explicit type guards
 * whenever PHPStan strict mode is enabled. This class centralises those guards
 * so call-sites stay readable.
 */
class Config
{
    public static function string(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) config($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }
}
