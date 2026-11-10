<?php

declare(strict_types=1);

namespace Bestandio\Core;

/** Current time; tests can move it forward. */
final class Clock
{
    private static ?int $fixed = null;

    public static function now(): int
    {
        return self::$fixed ?? time();
    }

    public static function set(?int $timestamp): void
    {
        self::$fixed = $timestamp;
    }
}
