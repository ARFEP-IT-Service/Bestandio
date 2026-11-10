<?php

declare(strict_types=1);

use Bestandio\Core\Csrf;
use Bestandio\Core\I18n;
use Bestandio\Core\Request;

/** Escape any value for HTML output. Every view output goes through this. */
function e(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translate a key from lang/i18n.json. */
function t(string $key, array $params = []): string
{
    return I18n::get($key, $params);
}

/** Build an application URL, respecting a sub-directory install. */
function url(string $path = '/', array $query = []): string
{
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    $qs = $query ? '?' . http_build_query($query) : '';
    return Request::basePath() . $path . $qs;
}

function asset(string $file): string
{
    $full = BESTANDIO_ROOT . '/public/assets/' . $file;
    $v = is_file($full) ? (string) filemtime($full) : BESTANDIO_VERSION;
    return url('/assets/' . $file, ['v' => $v]);
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Format a Y-m-d or Y-m-d H:i:s value for the current language. */
function fmt_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    $format = I18n::lang() === 'en' ? 'Y-m-d' : 'd.m.Y';
    return date($withTime ? $format . ' H:i' : $format, $ts);
}
