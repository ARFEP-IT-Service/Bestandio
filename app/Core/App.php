<?php

declare(strict_types=1);

namespace Bestandio\Core;

use Bestandio\Controllers\AccountController;
use Bestandio\Controllers\AuthController;
use Bestandio\Controllers\DeviceController;
use Bestandio\Controllers\ImportController;
use Bestandio\Controllers\LabelController;
use Bestandio\Controllers\LabelTemplateController;
use Bestandio\Controllers\LangController;
use Bestandio\Controllers\ListController;
use Bestandio\Controllers\ScanController;
use Bestandio\Controllers\SettingsController;
use Bestandio\Controllers\SetupController;
use Bestandio\Controllers\UserController;
use Throwable;

final class App
{
    public const CSP = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; "
        . "font-src 'self'; connect-src 'self'; worker-src 'self'; manifest-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    public static function router(): Router
    {
        $r = new Router();
        $r->get('/', [DeviceController::class, 'home']);
        $r->get('/setup', [SetupController::class, 'form']);
        $r->post('/setup', [SetupController::class, 'submit']);
        $r->get('/login', [AuthController::class, 'form']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->post('/lang', [LangController::class, 'set']);

        $r->get('/devices', [DeviceController::class, 'index']);
        $r->get('/devices/export', [DeviceController::class, 'export']);
        $r->get('/devices/new', [DeviceController::class, 'create']);
        $r->post('/devices', [DeviceController::class, 'store']);
        $r->get('/devices/{id}', [DeviceController::class, 'show']);
        $r->get('/devices/{id}/edit', [DeviceController::class, 'edit']);
        $r->post('/devices/{id}', [DeviceController::class, 'update']);
        $r->post('/devices/{id}/retire', [DeviceController::class, 'retire']);
        $r->get('/devices/{id}/qr', [LabelController::class, 'qr']);

        $r->get('/labels', [LabelController::class, 'print']);
        $r->get('/labels/style', [LabelController::class, 'style']);
        $r->get('/labels/templates', [LabelTemplateController::class, 'index']);
        $r->get('/labels/templates/new', [LabelTemplateController::class, 'create']);
        $r->post('/labels/templates', [LabelTemplateController::class, 'store']);
        $r->get('/labels/templates/{id}/edit', [LabelTemplateController::class, 'edit']);
        $r->post('/labels/templates/{id}', [LabelTemplateController::class, 'update']);
        $r->post('/labels/templates/{id}/delete', [LabelTemplateController::class, 'delete']);
        $r->post('/labels/templates/{id}/default', [LabelTemplateController::class, 'makeDefault']);
        $r->get('/scan', [ScanController::class, 'index']);
        $r->get('/import', [ImportController::class, 'form']);
        $r->post('/import', [ImportController::class, 'preview']);
        $r->post('/import/confirm', [ImportController::class, 'confirm']);
        $r->get('/settings', [SettingsController::class, 'form']);
        $r->post('/settings', [SettingsController::class, 'update']);

        foreach (['categories', 'locations'] as $kind) {
            $r->get('/' . $kind, [ListController::class, $kind]);
            $r->post('/' . $kind, [ListController::class, $kind . 'Store']);
            $r->post('/' . $kind . '/{id}', [ListController::class, $kind . 'Rename']);
            $r->post('/' . $kind . '/{id}/delete', [ListController::class, $kind . 'Delete']);
        }

        $r->get('/users', [UserController::class, 'index']);
        $r->get('/users/new', [UserController::class, 'create']);
        $r->post('/users', [UserController::class, 'store']);
        $r->get('/users/{id}/edit', [UserController::class, 'edit']);
        $r->post('/users/{id}', [UserController::class, 'update']);

        $r->get('/account', [AccountController::class, 'form']);
        $r->post('/account', [AccountController::class, 'update']);
        return $r;
    }

    public static function securityHeaders(): void
    {
        header('Content-Security-Policy: ' . self::CSP);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        header('Cache-Control: no-store');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function run(): void
    {
        ini_set('display_errors', '0');
        $tz = Config::get('app.timezone');
        if (is_string($tz) && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
        }
        self::securityHeaders();

        try {
            Session::start();
            Session::enforceLifetime();
            I18n::init();
            $path = Request::path();
            $method = Request::method();

            if (!Config::exists() && $path !== '/setup' && $path !== '/lang') {
                self::redirect('/setup');
                return;
            }
            if ($method === 'POST' && !Csrf::valid($_POST['_csrf'] ?? null)) {
                throw new HttpException(403, 'error.csrf');
            }

            [$handler, $params] = self::router()->match($method, $path);
            [$class, $action] = $handler;
            $out = (new $class())->$action(...array_map('intval', $params));
            if (is_string($out)) {
                header('Content-Type: text/html; charset=utf-8');
                echo $out;
            }
        } catch (Halt) {
            return;
        } catch (HttpException $ex) {
            self::error($ex->status, $ex->messageKey);
        } catch (Throwable $ex) {
            error_log('Bestandio: ' . $ex::class . ': ' . $ex->getMessage() . ' in '
                . $ex->getFile() . ':' . $ex->getLine());
            self::error(500, 'error.500');
        }
    }

    public static function redirect(string $path, array $query = []): void
    {
        header('Location: ' . url($path, $query), true, 302);
    }

    private static function error(int $status, string $key): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        try {
            echo View::render('error', ['status' => $status, 'messageKey' => $key, 'title' => t('error.title') . ' ' . $status]);
        } catch (Throwable) {
            echo 'Error ' . $status;
        }
    }
}
