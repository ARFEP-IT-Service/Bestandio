<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Session;
use Bestandio\Models\Settings;

final class SettingsController extends Controller
{
    public function form(): string
    {
        $this->authorize('settings');
        $values = Settings::all();
        return $this->render('settings', ['title' => t('settings.title'), 'values' => $values, 'errors' => [], 'labelSize' => Settings::labelSize($values)]);
    }

    public function update(): ?string
    {
        $this->authorize('settings');
        [$values, $errors] = Settings::validate($_POST);
        if ($errors) {
            http_response_code(422);
            return $this->render('settings', ['title' => t('settings.title'), 'values' => $values, 'errors' => $errors, 'labelSize' => null]);
        }
        Settings::save($values);
        Session::flash('success', 'settings.saved');
        return $this->redirect('/settings');
    }
}
