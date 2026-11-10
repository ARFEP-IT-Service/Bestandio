<?php

declare(strict_types=1);

define('BESTANDIO_ROOT', dirname(__DIR__));
// The version is stored only in the VERSION file.
define('BESTANDIO_VERSION', trim((string) @file_get_contents(BESTANDIO_ROOT . '/VERSION')) ?: 'dev');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bestandio\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';
