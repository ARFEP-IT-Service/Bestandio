<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valid(mixed $submitted): bool
    {
        $expected = $_SESSION['csrf'] ?? null;
        return is_string($expected) && $expected !== ''
            && is_string($submitted) && hash_equals($expected, $submitted);
    }

    public static function rotate(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
