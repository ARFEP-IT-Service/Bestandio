<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\Qr;
use Bestandio\Core\Request;
use Bestandio\Core\View;
use Bestandio\Models\Device;
use Bestandio\Models\Settings;

/** QR images and printable labels. */
final class LabelController extends Controller
{
    public const MAX_LABELS = 500;

    /** QR code of a device as SVG. The QR contains only the device code. */
    public function qr(int $id): ?string
    {
        $this->authorize('view');
        $device = Device::find($id) ?? $this->notFound();
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: private, max-age=86400');
        echo Qr::encode($device['code'], Qr::ECC_Q)->svg();
        return null;
    }

    public function print(): string
    {
        $this->authorize('view');
        $ids = $_GET['ids'] ?? [];
        $ids = is_array($ids) ? array_slice(array_filter($ids, 'is_string'), 0, self::MAX_LABELS) : [];
        $devices = Device::findMany($ids);
        $mode = Request::query('mode') === 'single' ? 'single' : 'sheet';
        $settings = Settings::all();
        $perSheet = (int) $settings['sheet_cols'] * (int) $settings['sheet_rows'];
        $skip = $mode === 'sheet' ? max(0, min($perSheet - 1, (int) Request::query('skip', '0'))) : 0;

        return View::render('labels/print', [
            'title'    => t('label.title'),
            'devices'  => $devices,
            'ids'      => array_map(static fn ($d) => (int) $d['id'], $devices),
            'mode'     => $mode,
            'skip'     => $skip,
            'perSheet' => $perSheet,
            'company'  => (string) $settings['company_name'],
        ], 'print');
    }

    /** Print CSS built from the label settings (numbers only). */
    public function style(): ?string
    {
        $this->authorize('view');
        $s = Settings::all();
        $single = Request::query('mode') === 'single';
        [$w, $h] = $single ? [(float) $s['single_width'], (float) $s['single_height']] : Settings::labelSize($s);
        $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . 'mm';
        $pad = min($h, $w) * 0.07;
        $qr = max(4.0, min($h - 2 * $pad, $w * 0.5));
        $font = max(1.8, min(4.2, $h * 0.13));

        $css = $single
            ? "@page { size: {$mm($w)} {$mm($h)}; margin: 0; }\n"
            . ".label { break-after: page; page-break-after: always; }\n"
            . "@media screen { .label { margin: 0 auto 4mm; } }\n"
            : "@page { size: A4 portrait; margin: 0; }\n"
            . ".sheet { width: 210mm; height: 297mm; padding: {$mm((float) $s['sheet_margin_top'])} {$mm((float) $s['sheet_margin_right'])} "
            . "{$mm((float) $s['sheet_margin_bottom'])} {$mm((float) $s['sheet_margin_left'])}; display: grid; "
            . 'grid-template-columns: repeat(' . (int) $s['sheet_cols'] . ", {$mm($w)}); "
            . 'grid-template-rows: repeat(' . (int) $s['sheet_rows'] . ", {$mm($h)}); "
            . "column-gap: {$mm((float) $s['sheet_gap_x'])}; row-gap: {$mm((float) $s['sheet_gap_y'])}; }\n";
        $css .= ".label { width: {$mm($w)}; height: {$mm($h)}; padding: {$mm($pad)}; gap: {$mm($pad)}; }\n"
            . ".label-qr { width: {$mm($qr)}; height: {$mm($qr)}; }\n"
            . ".label-code { font-size: {$mm($font)}; }\n"
            . ".label-inv, .label-company { font-size: {$mm($font * 0.8)}; }\n";

        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: no-store');
        echo $css;
        return null;
    }
}
