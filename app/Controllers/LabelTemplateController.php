<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\HttpException;
use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\LabelTemplate;

/** Label templates: admins create, change, copy and delete them; built-in ones are fixed. */
final class LabelTemplateController extends Controller
{
    public function index(): string
    {
        $this->authorize('settings');
        return $this->render('labels/templates', ['title' => t('tpl.title'), 'templates' => LabelTemplate::all()]);
    }

    /** New template; ?copy={id} starts from an existing (also built-in) template, ?kind=sheet for an A4 sheet. */
    public function create(): string
    {
        $this->authorize('settings');
        $copy = (int) Request::query('copy', '0');
        if ($copy > 0) {
            $values = LabelTemplate::copyValues(LabelTemplate::find($copy) ?? $this->notFound());
        } else {
            $kind = Request::query('kind') === 'sheet' ? 'sheet' : 'single';
            $base = LabelTemplate::BUILTIN[$kind === 'sheet' ? 'sheet_a4_3x8' : 'single_57x32'];
            $values = ['name' => ''] + $base;
        }
        return $this->form($values, null, []);
    }

    public function store(): ?string
    {
        $this->authorize('settings');
        [$values, $errors] = LabelTemplate::validate($_POST);
        if ($errors) {
            http_response_code(422);
            return $this->form($values, null, $errors);
        }
        LabelTemplate::create($values);
        Session::flash('success', 'tpl.saved');
        return $this->redirect('/labels/templates');
    }

    public function edit(int $id): string
    {
        $this->authorize('settings');
        return $this->form($this->own($id), $id, []);
    }

    public function update(int $id): ?string
    {
        $this->authorize('settings');
        $tpl = $this->own($id);
        // The kind cannot change: the template may be the default of its kind.
        [$values, $errors] = LabelTemplate::validate(['kind' => $tpl['kind']] + $_POST);
        if ($errors) {
            http_response_code(422);
            return $this->form($values, $id, $errors);
        }
        LabelTemplate::update($id, $values);
        Session::flash('success', 'tpl.saved');
        return $this->redirect('/labels/templates');
    }

    public function delete(int $id): ?string
    {
        $this->authorize('settings');
        $tpl = LabelTemplate::find($id) ?? $this->notFound();
        $blocker = LabelTemplate::deleteBlocker($tpl);
        if ($blocker === 'tpl.error.builtin') {
            throw new HttpException(403, 'tpl.error.builtin');
        }
        if ($blocker !== null) {
            Session::flash('error', 'tpl.error.default');
            return $this->redirect('/labels/templates');
        }
        LabelTemplate::delete($id);
        Session::flash('success', 'tpl.deleted');
        return $this->redirect('/labels/templates');
    }

    public function makeDefault(int $id): ?string
    {
        $this->authorize('settings');
        LabelTemplate::setDefault(LabelTemplate::find($id) ?? $this->notFound());
        Session::flash('success', 'tpl.default_saved');
        return $this->redirect('/labels/templates');
    }

    /** An own (not built-in) template, else 404 / 403. */
    private function own(int $id): array
    {
        $tpl = LabelTemplate::find($id) ?? $this->notFound();
        if (LabelTemplate::isBuiltin($tpl)) {
            throw new HttpException(403, 'tpl.error.builtin');
        }
        return $tpl;
    }

    private function form(array $values, ?int $id, array $errors): string
    {
        return $this->render('labels/template_form', [
            'title'  => t($id === null ? 'tpl.new' : 'tpl.edit'),
            'values' => $values,
            'id'     => $id,
            'errors' => $errors,
        ]);
    }
}
