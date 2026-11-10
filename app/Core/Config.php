<?php

declare(strict_types=1);

namespace Bestandio\Core;

use RuntimeException;

final class Config
{
    private static ?array $data = null;

    public static function path(): string
    {
        $env = getenv('BESTANDIO_CONFIG');
        return ($env !== false && $env !== '') ? $env : BESTANDIO_ROOT . '/config/config.php';
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    public static function all(): array
    {
        if (self::$data === null) {
            if (!self::exists()) {
                throw new RuntimeException('Configuration file missing.');
            }
            $data = require self::path();
            if (!is_array($data)) {
                throw new RuntimeException('Configuration file must return an array.');
            }
            self::$data = $data;
        }
        return self::$data;
    }

    /** Read a value by dot path, e.g. "db.host". */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null && !self::exists()) {
            return $default;
        }
        $value = self::all();
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(?array $data): void
    {
        self::$data = $data;
    }

    /** Write the configuration atomically. */
    public static function write(array $data): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('Configuration directory is not writable.');
        }
        $php = "<?php\n\n// Written by Bestandio setup on " . date('Y-m-d H:i:s') . "\n\nreturn "
            . var_export($data, true) . ";\n";
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('Configuration file could not be written.');
        }
        @chmod($tmp, 0640);
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Configuration file could not be written.');
        }
        self::$data = $data;
    }
}
