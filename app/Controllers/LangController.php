<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\I18n;
use Bestandio\Core\Request;

final class LangController extends Controller
{
    public function set(): ?string
    {
        $lang = Request::post('lang');
        if (in_array($lang, I18n::LANGS, true)) {
            $_SESSION['lang'] = $lang;
            setcookie('lang', $lang, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'secure'   => Request::isHttps(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        // Only local paths are accepted as return target.
        $back = Request::post('back');
        if (!preg_match('#^/(?![/\\\\])[A-Za-z0-9/_\-]*$#', $back)) {
            $back = '/';
        }
        return $this->redirect($back);
    }
}
