<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Auth;
use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\LoginThrottle;
use Bestandio\Models\User;

final class AuthController extends Controller
{
    public function form(): ?string
    {
        if (Auth::user() !== null) {
            return $this->redirect('/devices');
        }
        return $this->render('login', ['title' => t('login.title'), 'username' => '', 'error' => null]);
    }

    public function login(): ?string
    {
        $username = mb_substr(Request::post('username'), 0, 64);
        $password = Request::postRaw('password');
        $ip = Request::ip();

        if (LoginThrottle::blocked($username, $ip)) {
            http_response_code(429);
            return $this->render('login', ['title' => t('login.title'), 'username' => $username, 'error' => 'login.blocked']);
        }

        $user = $username === '' ? null : User::findByUsername($username);
        // Verify against a dummy hash for unknown users to keep timing similar.
        $hash = $user['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $user !== null && (int) $user['active'] === 1;

        if (!$ok) {
            LoginThrottle::fail($username, $ip);
            http_response_code(401);
            return $this->render('login', ['title' => t('login.title'), 'username' => $username, 'error' => 'login.failed']);
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::setPassword((int) $user['id'], $password);
        }
        LoginThrottle::clear($username);
        Auth::login($user);
        return $this->redirect('/devices');
    }

    public function logout(): ?string
    {
        Auth::logout();
        Session::start();
        Session::flash('success', 'login.logged_out');
        return $this->redirect('/login');
    }
}
