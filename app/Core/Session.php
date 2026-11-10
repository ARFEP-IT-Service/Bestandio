<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class Session
{
    /** Sign-in ends after 2 h without activity and at the latest after 12 h. */
    public const IDLE_SECONDS = 7200;
    public const MAX_SECONDS = 43200;

    /** True if a signed-in session has run out (idle or absolute limit). */
    public static function expired(array $session, int $now): bool
    {
        if (!isset($session['uid'])) {
            return false;
        }
        $created = (int) ($session['created_at'] ?? 0);
        $last = (int) ($session['last_seen'] ?? 0);
        return $now - $last > self::IDLE_SECONDS || $now - $created > self::MAX_SECONDS;
    }

    /** Called once per request: end expired sign-ins, otherwise record activity. */
    public static function enforceLifetime(): void
    {
        $now = Clock::now();
        if (self::expired($_SESSION, $now)) {
            $lang = $_SESSION['lang'] ?? null;
            $_SESSION = [];
            self::regenerate();
            if ($lang !== null) {
                $_SESSION['lang'] = $lang;
            }
            self::flash('error', 'login.expired');
            return;
        }
        if (isset($_SESSION['uid'])) {
            $_SESSION['last_seen'] = $now;
        }
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secureSetting = Config::get('app.secure_cookie', 'auto');
        $secure = $secureSetting === true || ($secureSetting === 'auto' && Request::isHttps());

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_SECONDS);
        session_name($secure ? '__Host-bestandio' : 'bestandio');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_destroy();
        }
    }

    public static function flash(string $type, string $messageKey, array $params = []): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'key' => $messageKey, 'params' => $params];
    }

    /** @return list<array{type:string,key:string,params:array}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
