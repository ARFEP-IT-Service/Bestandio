<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\User;

final class UserController extends Controller
{
    public function index(): string
    {
        $this->authorize('users');
        return $this->render('users/index', ['title' => t('user.title'), 'users' => User::all()]);
    }

    public function create(): string
    {
        $this->authorize('users');
        return $this->form(['username' => '', 'role' => 'viewer', 'active' => 1], null, []);
    }

    public function store(): ?string
    {
        $this->authorize('users');
        $username = Request::post('username');
        $role = Request::post('role');
        $password = Request::postRaw('password');
        $errors = User::passwordErrors($password, Request::postRaw('password2'));
        if (!User::validUsername($username)) {
            $errors[] = 'user.error.username';
        } elseif (User::findByUsername($username) !== null) {
            $errors[] = 'user.error.exists';
        }
        if (!in_array($role, User::ROLES, true)) {
            $errors[] = 'user.error.role';
        }
        if ($errors) {
            http_response_code(422);
            return $this->form(['username' => $username, 'role' => $role, 'active' => 1], null, $errors);
        }
        User::create($username, $password, $role);
        Session::flash('success', 'user.saved');
        return $this->redirect('/users');
    }

    public function edit(int $id): string
    {
        $this->authorize('users');
        $user = User::find($id) ?? $this->notFound();
        return $this->form($user, $id, []);
    }

    public function update(int $id): ?string
    {
        $me = $this->authorize('users');
        $user = User::find($id) ?? $this->notFound();
        $role = Request::post('role');
        $active = Request::post('active') === '1';
        $password = Request::postRaw('password');
        $errors = [];
        if (!in_array($role, User::ROLES, true)) {
            $errors[] = 'user.error.role';
        }
        // Never lock out the last active admin, never demote or deactivate yourself.
        $wasActiveAdmin = $user['role'] === 'admin' && (int) $user['active'] === 1;
        if ($wasActiveAdmin && ($role !== 'admin' || !$active) && (User::activeAdmins() <= 1 || (int) $me['id'] === $id)) {
            $errors[] = 'user.error.last_admin';
        }
        if ($password !== '') {
            $errors = array_merge($errors, User::passwordErrors($password, Request::postRaw('password2')));
        }
        if ($errors) {
            http_response_code(422);
            return $this->form(['username' => $user['username'], 'role' => $role, 'active' => $active ? 1 : 0], $id, $errors);
        }
        User::update($id, $role, $active);
        if ($password !== '') {
            User::setPassword($id, $password);
        }
        Session::flash('success', 'user.saved');
        return $this->redirect('/users');
    }

    private function form(array $user, ?int $id, array $errors): string
    {
        return $this->render('users/form', [
            'title'  => $id === null ? t('user.new') : t('user.edit'),
            'edit'   => $user,
            'id'     => $id,
            'errors' => $errors,
        ]);
    }
}
