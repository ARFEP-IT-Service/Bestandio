<?php

declare(strict_types=1);

namespace Bestandio\Core;

/** Fixed device code: 10 characters, no look-alike characters (0/O, 1/I/L). */
final class DeviceCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const LENGTH = 10;

    /** Cryptographically secure random code (random_int). */
    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }

    public static function valid(string $code): bool
    {
        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH;
    }

    /** Normalise user or scanner input: upper case, drop spaces and dashes. */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', $input));
    }
}
