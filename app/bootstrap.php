<?php

declare(strict_types=1);

define('BESTANDIO_ROOT', dirname(__DIR__));
define('BESTANDIO_VERSION', '0.1.0');

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
