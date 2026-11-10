<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\App;
use Bestandio\Core\Auth;
use Bestandio\Core\Halt;
use Bestandio\Core\HttpException;
use Bestandio\Core\Request;
use Bestandio\Core\View;

abstract class Controller
{
    protected function render(string $template, array $vars = []): string
    {
        return View::render($template, $vars);
    }

    protected function redirect(string $path, array $query = []): ?string
    {
        App::redirect($path, $query);
        return null;
    }

    /** Require a logged-in user with the given permission; checked server-side. */
    protected function authorize(string $permission = 'view'): array
    {
        $user = Auth::user();
        if ($user === null) {
            if (Request::method() === 'GET') {
                App::redirect('/login');
                throw new Halt();
            }
            throw new HttpException(403, 'error.403');
        }
        if (!Auth::can($permission)) {
            throw new HttpException(403, 'error.403');
        }
        return $user;
    }

    protected function notFound(): never
    {
        throw new HttpException(404, 'error.404');
    }
}
