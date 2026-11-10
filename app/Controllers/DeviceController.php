<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Auth;
use Bestandio\Core\Config;
use Bestandio\Core\Csv;
use Bestandio\Core\I18n;
use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\Device;
use Bestandio\Models\History;
use Bestandio\Models\NamedList;
use Bestandio\Models\Software;

final class DeviceController extends Controller
{
    public function home(): ?string
    {
        return $this->redirect(Auth::user() === null ? '/login' : '/devices');
    }

    public function index(): string
    {
        $this->authorize('view');
        [$filter, $sort, $dir] = $this->listParams();
        $page = max(1, (int) Request::query('page', '1'));
        $perPage = max(5, min(200, (int) Config::get('app.per_page', 25)));
        $result = Device::search($filter, $sort, $dir, $page, $perPage);
        $pages = max(1, (int) ceil($result['total'] / $perPage));

        return $this->render('devices/index', [
            'title'      => t('device.list_title'),
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => min($page, $pages),
            'pages'      => $pages,
            'filter'     => $filter,
            'sort'       => $sort,
            'dir'        => $dir,
            'categories' => (new NamedList('categories'))->options(),
            'locations'  => (new NamedList('locations'))->options(),
        ]);
    }

    public function export(): ?string
    {
        $this->authorize('view');
        [$filter, $sort, $dir] = $this->listParams();
        $cols = ['inventory_no', 'code', 'category', 'manufacturer', 'model', 'serial_no', 'location', 'status', 'purchase_date',
            'warranty_until', 'note', 'cpu', 'ram_mb', 'storage', 'os_name', 'os_version', 'scanned_at', 'updated_at'];
        $sep = Csv::separatorFor(I18n::lang());

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bestandio-' . date('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        echo Csv::line(array_map(static fn ($c) => t('device.' . $c), $cols), $sep);
        foreach (Device::each($filter, $sort, $dir) as $row) {
            $row['status'] = t('status.' . $row['status']);
            echo Csv::line(array_map(static fn ($c) => $row[$c], $cols), $sep);
        }
        return null;
    }

    public function show(int $id): string
    {
        $this->authorize('view');
        $device = Device::find($id) ?? $this->notFound();
        return $this->render('devices/show', [
            'title'   => $device['inventory_no'],
            'device'  => $device,
            'history'  => History::forDevice($id),
            'software' => Software::forDevice($id),
        ]);
    }

    public function create(): string
    {
        $this->authorize('edit');
        $device = Device::fromInput(['status' => 'in_use']);
        return $this->form($device, null, [], []);
    }

    public function store(): ?string
    {
        $user = $this->authorize('edit');
        $device = Device::fromInput($_POST);
        $errors = Device::validate($device);
        $dupes = Device::serialDuplicates($device['serial_no']);
        if ($errors || ($dupes && Request::post('confirm_duplicate') !== '1')) {
            http_response_code($errors ? 422 : 409);
            return $this->form($device, null, $errors, $dupes);
        }
        $id = Device::create($device, (int) $user['id']);
        Session::flash('success', 'device.saved');
        return $this->redirect('/devices/' . $id);
    }

    public function edit(int $id): string
    {
        $this->authorize('edit');
        $device = Device::find($id) ?? $this->notFound();
        return $this->form($device, $id, [], []);
    }

    public function update(int $id): ?string
    {
        $user = $this->authorize('edit');
        Device::find($id) ?? $this->notFound();
        $device = Device::fromInput($_POST);
        $errors = Device::validate($device, $id);
        $dupes = Device::serialDuplicates($device['serial_no'], $id);
        if ($errors || ($dupes && Request::post('confirm_duplicate') !== '1')) {
            http_response_code($errors ? 422 : 409);
            return $this->form($device, $id, $errors, $dupes);
        }
        $changed = Device::update($id, $device, (int) $user['id']);
        Session::flash('success', $changed > 0 ? 'device.saved' : 'device.unchanged');
        return $this->redirect('/devices/' . $id);
    }

    public function retire(int $id): ?string
    {
        $user = $this->authorize('edit');
        $device = Device::find($id) ?? $this->notFound();
        $data = array_intersect_key($device, array_flip(Device::FIELDS));
        $data['status'] = 'retired';
        Device::update($id, $data, (int) $user['id']);
        Session::flash('success', 'device.retired');
        return $this->redirect('/devices/' . $id);
    }

    private function form(array $device, ?int $id, array $errors, array $dupes): string
    {
        return $this->render('devices/form', [
            'title'      => $id === null ? t('device.new') : t('device.edit'),
            'device'     => $device,
            'id'         => $id,
            'errors'     => $errors,
            'dupes'      => $dupes,
            'categories' => (new NamedList('categories'))->options(),
            'locations'  => (new NamedList('locations'))->options(),
        ]);
    }

    /** @return array{0:array,1:string,2:string} */
    private function listParams(): array
    {
        $filter = [
            'q'        => mb_substr(Request::query('q'), 0, 100),
            'category' => (int) Request::query('category'),
            'location' => (int) Request::query('location'),
            'status'   => in_array(Request::query('status'), Device::STATUSES, true) ? Request::query('status') : '',
        ];
        $sort = array_key_exists(Request::query('sort'), Device::SORTS) ? Request::query('sort') : 'inventory_no';
        $dir = Request::query('dir') === 'desc' ? 'desc' : 'asc';
        return [$filter, $sort, $dir];
    }
}
