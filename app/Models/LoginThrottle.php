<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/** Brake against password guessing: failed attempts per user name and per IP. */
final class LoginThrottle
{
    public const WINDOW_MINUTES = 15;
    public const MAX_PER_USER = 5;
    public const MAX_PER_IP = 20;

    public static function blocked(string $username, string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $byUser = (int) Db::value(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > ?',
            [mb_strtolower($username), $since]
        );
        if ($byUser >= self::MAX_PER_USER) {
            return true;
        }
        $byIp = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]);
        return $byIp >= self::MAX_PER_IP;
    }

    public static function fail(string $username, string $ip): void
    {
        Db::run(
            'INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)',
            [mb_substr(mb_strtolower($username), 0, 64), $ip, date('Y-m-d H:i:s')]
        );
        Db::run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function clear(string $username): void
    {
        Db::run('DELETE FROM login_attempts WHERE username = ?', [mb_strtolower($username)]);
    }
}
