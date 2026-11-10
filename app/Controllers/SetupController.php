<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Config;
use Bestandio\Core\Db;
use Bestandio\Core\HttpException;
use Bestandio\Core\Migrator;
use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\User;
use PDOException;
use Throwable;

final class SetupController extends Controller
{
    public function form(): string
    {
        $this->ensureOpen();
        return $this->render('setup', ['title' => t('setup.title'), 'values' => $this->defaults(), 'errors' => [], 'notice' => null]);
    }

    public function submit(): ?string
    {
        $this->ensureOpen();
        $values = $this->values();
        $db = [
            'host'    => $values['db_host'],
            'port'    => (int) $values['db_port'],
            'name'    => $values['db_name'],
            'user'    => $values['db_user'],
            'pass'    => Request::postRaw('db_pass'),
            'charset' => 'utf8mb4',
        ];
        $errors = [];
        $notice = null;

        try {
            $pdo = Db::connect($db);
            if (!Db::versionSupported($pdo)) {
                $errors[] = 'setup.error.version';
            }
        } catch (PDOException) {
            $pdo = null;
            $errors[] = 'setup.error.connect';
        }

        if (Request::post('action') === 'test') {
            if (!$errors) {
                $notice = 'setup.connect_ok';
            }
            return $this->render('setup', ['title' => t('setup.title'), 'values' => $values, 'errors' => $errors, 'notice' => $notice]);
        }

        $adminPass = Request::postRaw('admin_pass');
        if (!User::validUsername($values['admin_user'])) {
            $errors[] = 'user.error.username';
        }
        $errors = array_merge($errors, User::passwordErrors($adminPass, Request::postRaw('admin_pass2')));
        if (!is_writable(dirname(Config::path()))) {
            $errors[] = 'setup.error.config_dir';
        }

        if (!$errors && $pdo !== null) {
            try {
                Db::set($pdo);
                (new Migrator($pdo))->migrate();
                if (User::count() > 0) {
                    $errors[] = 'setup.error.has_users';
                } else {
                    User::create($values['admin_user'], $adminPass, 'admin');
                    Config::write([
                        'db'  => $db,
                        'app' => [
                            'secure_cookie' => 'auto',
                            'per_page'      => 25,
                            'timezone'      => date_default_timezone_get(),
                        ],
                    ]);
                    Session::flash('success', 'setup.done');
                    return $this->redirect('/login');
                }
            } catch (Throwable $ex) {
                error_log('Bestandio setup: ' . $ex->getMessage());
                $errors[] = 'setup.error.install';
            }
        }

        return $this->render('setup', ['title' => t('setup.title'), 'values' => $values, 'errors' => array_unique($errors), 'notice' => null]);
    }

    /** Setup is locked as soon as a configuration exists. */
    private function ensureOpen(): void
    {
        if (Config::exists()) {
            throw new HttpException(403, 'setup.locked');
        }
    }

    private function defaults(): array
    {
        return ['db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'bestandio', 'db_user' => 'bestandio', 'admin_user' => 'admin'];
    }

    private function values(): array
    {
        $v = [];
        foreach (array_keys($this->defaults()) as $k) {
            $v[$k] = mb_substr(Request::post($k), 0, 190);
        }
        if (!ctype_digit($v['db_port']) || (int) $v['db_port'] < 1 || (int) $v['db_port'] > 65535) {
            $v['db_port'] = '3306';
        }
        return $v;
    }
}
