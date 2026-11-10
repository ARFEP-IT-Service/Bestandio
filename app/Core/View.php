<?php

declare(strict_types=1);

namespace Bestandio\Core;

final class View
{
    /** Render a template inside the layout. Templates live in app/Views. */
    public static function render(string $template, array $vars = [], string $layout = 'layout'): string
    {
        $file = BESTANDIO_ROOT . '/app/Views/' . $template . '.php';
        $layoutFile = BESTANDIO_ROOT . '/app/Views/' . $layout . '.php';
        $vars += [
            'title'   => 'Bestandio',
            'user'    => Auth::user(),
            'flashes' => Session::takeFlash(),
            'lang'    => I18n::lang(),
        ];
        return (static function (string $__template, string $__layout, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__layout;
            } catch (\Throwable $ex) {
                ob_end_clean();
                throw $ex;
            }
            return (string) ob_get_clean();
        })($file, $layoutFile, $vars);
    }
}
