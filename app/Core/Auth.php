<?php

declare(strict_types=1);

namespace Bestandio\Core;

use Bestandio\Models\User;

final class Auth
{
    /** Permission => roles allowed. Checked on the server for every request. */
    public const PERMISSIONS = [
        'view'  => ['admin', 'editor', 'viewer'],
        'edit'  => ['admin', 'editor'],
        'lists' => ['admin', 'editor'],
        'users' => ['admin'],
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if (is_int($id) && Config::exists()) {
                $u = User::find($id);
                if ($u !== null && (int) $u['active'] === 1) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u === null ? null : (int) $u['id'];
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        $_SESSION['uid'] = (int) $user['id'];
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$loaded = true;
        Session::destroy();
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }
}
