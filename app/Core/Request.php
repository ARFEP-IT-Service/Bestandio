<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Base path when installed in a sub-directory, '' at the web root. */
    public static function basePath(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // dirname() returns "\" on Windows for "/index.php".
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $dir === '.' ? '' : $dir;
    }

    /** Request path relative to the base path, always starting with '/'. */
    public static function path(): string
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return self::clean($v) ?? $default;
    }

    /** Raw POST value without trimming (for passwords). */
    public static function postRaw(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return (is_string($v) && mb_check_encoding($v, 'UTF-8')) ? $v : '';
    }

    public static function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return self::clean($v) ?? $default;
    }

    /** Trimmed string or null for arrays and invalid UTF-8. */
    public static function clean(mixed $v): ?string
    {
        return (is_string($v) && mb_check_encoding($v, 'UTF-8')) ? trim($v) : null;
    }
}
