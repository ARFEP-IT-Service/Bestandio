<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\DeviceCode;
use Bestandio\Core\Request;
use Bestandio\Models\Device;

/** Scan page: camera, photo or manual entry. A hit opens the device page. */
final class ScanController extends Controller
{
    public function index(): ?string
    {
        $this->authorize('view');
        $input = mb_substr(Request::query('code'), 0, 64);
        $notFound = false;
        if ($input !== '') {
            $device = Device::findByCode(DeviceCode::normalize($input));
            if ($device !== null) {
                return $this->redirect('/devices/' . $device['id']);
            }
            $notFound = true;
            http_response_code(404);
        }
        return $this->render('scan', ['title' => t('scan.title'), 'input' => $input, 'notFound' => $notFound]);
    }
}
