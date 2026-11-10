<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\User;

/** Change your own password. */
final class AccountController extends Controller
{
    public function form(): string
    {
        $this->authorize('view');
        return $this->render('account', ['title' => t('account.title'), 'errors' => []]);
    }

    public function update(): ?string
    {
        $me = $this->authorize('view');
        $errors = [];
        if (!password_verify(Request::postRaw('current'), $me['password_hash'])) {
            $errors[] = 'account.error.current';
        }
        $password = Request::postRaw('password');
        $errors = array_merge($errors, User::passwordErrors($password, Request::postRaw('password2')));
        if ($errors) {
            http_response_code(422);
            return $this->render('account', ['title' => t('account.title'), 'errors' => $errors]);
        }
        User::setPassword((int) $me['id'], $password);
        Session::flash('success', 'account.saved');
        return $this->redirect('/devices');
    }
}
