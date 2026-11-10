<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\NamedList;

/** Categories and locations share one controller and one view. */
final class ListController extends Controller
{
    public function categories(): string
    {
        return $this->index('categories');
    }

    public function locations(): string
    {
        return $this->index('locations');
    }

    public function categoriesStore(): ?string
    {
        return $this->store('categories');
    }

    public function locationsStore(): ?string
    {
        return $this->store('locations');
    }

    public function categoriesRename(int $id): ?string
    {
        return $this->rename('categories', $id);
    }

    public function locationsRename(int $id): ?string
    {
        return $this->rename('locations', $id);
    }

    public function categoriesDelete(int $id): ?string
    {
        return $this->delete('categories', $id);
    }

    public function locationsDelete(int $id): ?string
    {
        return $this->delete('locations', $id);
    }

    private function index(string $kind): string
    {
        $this->authorize('view');
        return $this->render('lists/index', [
            'title' => t('list.' . $kind),
            'kind'  => $kind,
            'items' => (new NamedList($kind))->all(),
        ]);
    }

    private function store(string $kind): ?string
    {
        $this->authorize('lists');
        $list = new NamedList($kind);
        $name = $this->name();
        if ($name !== null && !$list->nameExists($name)) {
            $list->create($name);
            Session::flash('success', 'list.saved');
        } else {
            Session::flash('error', $name === null ? 'list.error.name' : 'list.error.exists');
        }
        return $this->redirect('/' . $kind);
    }

    private function rename(string $kind, int $id): ?string
    {
        $this->authorize('lists');
        $list = new NamedList($kind);
        $list->find($id) ?? $this->notFound();
        $name = $this->name();
        if ($name !== null && !$list->nameExists($name, $id)) {
            $list->rename($id, $name);
            Session::flash('success', 'list.saved');
        } else {
            Session::flash('error', $name === null ? 'list.error.name' : 'list.error.exists');
        }
        return $this->redirect('/' . $kind);
    }

    private function delete(string $kind, int $id): ?string
    {
        $this->authorize('lists');
        $list = new NamedList($kind);
        $list->find($id) ?? $this->notFound();
        Session::flash(...($list->delete($id) ? ['success', 'list.deleted'] : ['error', 'list.error.in_use']));
        return $this->redirect('/' . $kind);
    }

    private function name(): ?string
    {
        $name = Request::post('name');
        return ($name === '' || mb_strlen($name) > 100) ? null : $name;
    }
}
