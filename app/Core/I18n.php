<?php

declare(strict_types=1);

namespace Bestandio\Core;

use RuntimeException;

/** All texts live in lang/i18n.json: { "de": {...}, "en": {...}, "tr": {...} }. */
final class I18n
{
    public const LANGS = ['de', 'en', 'tr'];
    public const FALLBACK = 'en';

    private static ?array $texts = null;
    private static string $lang = self::FALLBACK;

    public static function texts(): array
    {
        if (self::$texts === null) {
            $json = file_get_contents(BESTANDIO_ROOT . '/lang/i18n.json');
            $data = json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new RuntimeException('lang/i18n.json is invalid.');
            }
            self::$texts = $data;
        }
        return self::$texts;
    }

    /** Pick the language: session choice, then cookie, then browser header. */
    public static function init(): void
    {
        $chosen = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? null;
        if (is_string($chosen) && in_array($chosen, self::LANGS, true)) {
            self::$lang = $chosen;
            return;
        }
        self::$lang = self::negotiate((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /** Parse an Accept-Language header without the intl extension. */
    public static function negotiate(string $header): string
    {
        $best = self::FALLBACK;
        $bestQ = -1.0;
        foreach (explode(',', $header) as $i => $part) {
            $bits = explode(';', trim($part));
            $code = strtolower(substr(trim($bits[0]), 0, 2));
            $q = 1.0;
            foreach (array_slice($bits, 1) as $param) {
                if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                    $q = (float) $m[1];
                }
            }
            $q -= $i * 0.0001; // earlier entries win ties
            if (in_array($code, self::LANGS, true) && $q > $bestQ) {
                $best = $code;
                $bestQ = $q;
            }
        }
        return $best;
    }

    public static function setLang(string $lang): void
    {
        if (in_array($lang, self::LANGS, true)) {
            self::$lang = $lang;
        }
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function get(string $key, array $params = []): string
    {
        $texts = self::texts();
        $text = $texts[self::$lang][$key] ?? $texts[self::FALLBACK][$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }
}
