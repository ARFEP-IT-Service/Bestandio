<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

final class User
{
    public const ROLES = ['admin', 'editor', 'viewer'];
    public const MIN_PASSWORD = 10;

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, username, password_hash, role, active, created_at FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return Db::one('SELECT id, username, password_hash, role, active FROM users WHERE username = ?', [$username]);
    }

    public static function all(): array
    {
        return Db::all('SELECT id, username, role, active, created_at FROM users ORDER BY username');
    }

    public static function count(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM users');
    }

    public static function activeAdmins(): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    }

    public static function validUsername(string $u): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{3,64}$/', $u);
    }

    /** @return list<string> i18n error keys */
    public static function passwordErrors(string $password, string $repeat): array
    {
        $errors = [];
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            $errors[] = 'user.error.password_short';
        }
        if (strlen($password) > 4096) {
            $errors[] = 'user.error.password_long';
        }
        if ($password !== $repeat) {
            $errors[] = 'user.error.password_mismatch';
        }
        return $errors;
    }

    public static function create(string $username, string $password, string $role): int
    {
        Db::run(
            'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), $role]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function update(int $id, string $role, bool $active): void
    {
        Db::run('UPDATE users SET role = ?, active = ? WHERE id = ?', [$role, $active ? 1 : 0, $id]);
    }

    public static function setPassword(int $id, string $password): void
    {
        Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
