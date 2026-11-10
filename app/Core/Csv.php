<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class Csv
{
    /** Separator by UI language: comma for English, semicolon otherwise (spreadsheet defaults). */
    public static function separatorFor(string $lang): string
    {
        return $lang === 'en' ? ',' : ';';
    }

    /** Neutralise cells a spreadsheet would treat as a formula. */
    public static function cell(?string $value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }
        return $value;
    }

    /** One CSV line, every field quoted, quotes doubled. */
    public static function line(array $fields, string $separator = ';'): string
    {
        $out = [];
        foreach ($fields as $f) {
            $out[] = '"' . str_replace('"', '""', self::cell($f === null ? null : (string) $f)) . '"';
        }
        return implode($separator, $out) . "\r\n";
    }
}
