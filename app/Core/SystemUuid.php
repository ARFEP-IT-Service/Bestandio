<?php

declare(strict_types=1);

namespace Bestandio\Core;

/** SMBIOS system UUID: canonical form and firmware placeholders. */
final class SystemUuid
{
    /** Well-known values firmware vendors leave in unfilled UUID fields. */
    private const PLACEHOLDERS = [
        '03000200-0400-0500-0006-000700080009',
        '00020003-0004-0005-0006-000700080009',
        '12345678-1234-5678-90AB-CDDEEFAABBCC',
    ];

    public static function wellFormed(string $v): bool
    {
        return (bool) preg_match('/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/', $v);
    }

    /** Upper-case canonical UUID, or '' for placeholders (all zeros, all F, known dummies). */
    public static function normalize(string $v): string
    {
        $v = strtoupper(trim($v));
        if (!self::wellFormed($v)) {
            return '';
        }
        $hex = str_replace('-', '', $v);
        if (count(array_unique(str_split($hex))) === 1 || in_array($v, self::PLACEHOLDERS, true)) {
            return '';
        }
        return $v;
    }
}
