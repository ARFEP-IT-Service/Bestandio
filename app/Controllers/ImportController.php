<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\HttpException;
use Bestandio\Core\Request;
use Bestandio\Core\Session;
use Bestandio\Models\Device;
use Bestandio\Models\NamedList;
use Bestandio\Models\ScanImport;

/**
 * Hardware scan import: paste or upload → preview → confirm.
 * The parsed data waits in the session until confirmed; nothing is stored before.
 */
final class ImportController extends Controller
{
    public function form(): string
    {
        $this->authorize('import');
        return $this->render('import/form', ['title' => t('import.title'), 'errors' => [], 'json' => '']);
    }

    public function preview(): string
    {
        $this->authorize('import');
        $json = $this->input();
        if ($json === null) {
            http_response_code(413);
            return $this->render('import/form', ['title' => t('import.title'), 'errors' => [['import.error.too_large', []]], 'json' => '']);
        }
        [$data, $errors, $ignored] = ScanImport::parse($json);
        if ($data === null) {
            http_response_code(422);
            $keep = strlen($json) <= 65536 && mb_check_encoding($json, 'UTF-8') ? $json : '';
            return $this->render('import/form', ['title' => t('import.title'), 'errors' => $errors, 'json' => $keep]);
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['import'] = ['token' => $token, 'data' => $data, 'ignored' => $ignored];
        return $this->previewPage($token, $data, $ignored, [], []);
    }

    public function confirm(): ?string
    {
        $user = $this->authorize('import');
        $pending = $_SESSION['import'] ?? null;
        if (!is_array($pending) || !hash_equals((string) $pending['token'], Request::post('token'))) {
            throw new HttpException(409, 'import.error.expired');
        }
        $data = $pending['data'];
        $matches = Device::findByManufacturerSerial($data['manufacturer'], $data['serial_no']);
        $target = Request::post('target');

        if ($target !== 'new') {
            $ids = array_map(static fn ($d) => (string) $d['id'], $matches);
            if (!in_array($target, $ids, true)) {
                http_response_code(422);
                return $this->previewPage($pending['token'], $data, (int) $pending['ignored'], ['target' => 'validation.required'], []);
            }
            ScanImport::applyUpdate((int) $target, $data, (int) $user['id']);
            unset($_SESSION['import']);
            Session::flash('success', 'import.updated');
            return $this->redirect('/devices/' . $target);
        }

        $device = Device::fromInput($_POST + ['manufacturer' => $data['manufacturer'], 'serial_no' => $data['serial_no']]);
        $device['manufacturer'] = $data['manufacturer'];
        $device['serial_no'] = $data['serial_no'];
        $device['model'] = $data['model'] ?? '';
        $errors = Device::validate($device);
        $dupes = Device::serialDuplicates($data['serial_no']);
        if ($errors || ($dupes && Request::post('confirm_duplicate') !== '1')) {
            http_response_code($errors ? 422 : 409);
            return $this->previewPage($pending['token'], $data, (int) $pending['ignored'], $errors, $dupes, $device);
        }
        $id = ScanImport::applyCreate($device, $data, (int) $user['id']);
        unset($_SESSION['import']);
        Session::flash('success', 'import.created');
        return $this->redirect('/devices/' . $id);
    }

    /** Uploaded file (preferred) or pasted text; null if larger than allowed. */
    private function input(): ?string
    {
        $file = $_FILES['file'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                || (int) $file['size'] > ScanImport::MAX_BYTES) {
                return null;
            }
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                return '';
            }
            return (string) file_get_contents($file['tmp_name'], false, null, 0, ScanImport::MAX_BYTES + 1);
        }
        $text = $_POST['json'] ?? '';
        $text = is_string($text) ? $text : '';
        return strlen($text) > ScanImport::MAX_BYTES ? null : $text;
    }

    private function previewPage(string $token, array $data, int $ignored, array $errors, array $dupes, ?array $device = null): string
    {
        $matches = Device::findByManufacturerSerial($data['manufacturer'], $data['serial_no']);
        $diffs = [];
        foreach ($matches as $m) {
            $diffs[(int) $m['id']] = ScanImport::diff($m, $data);
        }
        return $this->render('import/preview', [
            'title'      => t('import.preview'),
            'token'      => $token,
            'data'       => $data,
            'ignored'    => $ignored,
            'matches'    => $matches,
            'diffs'      => $diffs,
            'errors'     => $errors,
            'dupes'      => $dupes,
            'device'     => $device ?? Device::fromInput(['status' => 'in_use']),
            'target'     => Request::post('target', $matches ? (string) $matches[0]['id'] : 'new'),
            'categories' => (new NamedList('categories'))->options(),
            'locations'  => (new NamedList('locations'))->options(),
        ]);
    }
}
