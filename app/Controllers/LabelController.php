<?php

declare(strict_types=1);

namespace Bestandio\Controllers;

use Bestandio\Core\HttpException;
use Bestandio\Core\Qr;
use Bestandio\Core\Request;
use Bestandio\Core\View;
use Bestandio\Models\Device;
use Bestandio\Models\LabelTemplate;
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
        $tpl = self::template();
        $mode = $tpl['kind'];
        $perSheet = LabelTemplate::perSheet($tpl);
        // Start position on the first A4 sheet (1-based, only for this print, never stored).
        // 'skip' (number of used positions) is still accepted for older links.
        $start = (int) Request::query('start', (string) ((int) Request::query('skip', '0') + 1));
        $start = $mode === 'sheet' ? max(1, min($perSheet, $start)) : 1;
        $skip = $start - 1;

        return View::render('labels/print', [
            'title'     => t('label.title'),
            'devices'   => $devices,
            'ids'       => array_map(static fn ($d) => (int) $d['id'], $devices),
            'mode'      => $mode,
            'tpl'       => $tpl,
            'templates' => LabelTemplate::all(),
            'skip'      => $skip,
            'start'     => $start,
            'cols'      => (int) ($tpl['sheet_cols'] ?? 1),
            'perSheet'  => $perSheet,
            'company'   => (string) Settings::all()['company_name'],
        ], 'print');
    }

    /** Print CSS built from the chosen label template (numbers only). */
    public function style(): ?string
    {
        $this->authorize('view');
        $t = self::template();
        [$pw, $ph] = LabelTemplate::pageSize($t);
        [$w, $h] = LabelTemplate::labelSize($t);
        $l = self::layout($w, $h, (int) $t['dpi']);
        $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') . 'mm';

        $css = "@page { size: {$mm($pw)} {$mm($ph)}; margin: 0; }
";
        if ($t['kind'] === 'single') {
            $css .= ".label { break-after: page; page-break-after: always; }
"
                . ".label:last-child { break-after: auto; page-break-after: auto; }
"
                . "@media screen { .label { margin: 0 auto 4mm; } }
";
        } else {
            $css .= ".sheet { width: {$mm($pw)}; height: {$mm($ph)}; padding: {$mm($t['margin_top'])} {$mm($t['margin_right'])} "
                . "{$mm($t['margin_bottom'])} {$mm($t['margin_left'])}; display: grid; "
                . 'grid-template-columns: repeat(' . $t['sheet_cols'] . ", {$mm($w)}); "
                . 'grid-template-rows: repeat(' . $t['sheet_rows'] . ", {$mm($h)}); "
                . "column-gap: {$mm($t['gap_x'])}; row-gap: {$mm($t['gap_y'])}; }
"
                // Start position picker: same grid as the sheet, cells in the label's aspect ratio.
                . '.sheetpick-grid { grid-template-columns: repeat(' . $t['sheet_cols'] . ", 1fr); }
"
                . '.sheetpick-cell { aspect-ratio: ' . round($w, 2) . ' / ' . round($h, 2) . "; }
";
        }
        $css .= ".label { width: {$mm($w)}; height: {$mm($h)}; padding: {$mm($l['pad'])}; gap: {$mm($l['gap'])}; }
"
            . ($l['vertical'] ? ".label { flex-direction: column; justify-content: center; text-align: center; }
.label-text { align-items: center; max-width: 100%; }
" : '')
            . ".label-qr { width: {$mm($l['qr'])}; height: {$mm($l['qr'])}; }
"
            . ".label-code { font-size: {$mm($l['font'])}; }
"
            . ".label-inv, .label-company { font-size: {$mm($l['font'] * 0.75)}; }
";

        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: no-store');
        echo $css;
        return null;
    }

    /**
     * Template for this print: ?tpl={id}, else the default of ?mode=single|sheet (older links), else the A4 default.
     */
    private static function template(): array
    {
        $id = (int) Request::query('tpl', '0');
        $tpl = $id > 0 ? LabelTemplate::find($id) : null;
        $tpl ??= LabelTemplate::defaultFor(Request::query('mode') === 'single' ? 'single' : 'sheet');
        if ($tpl === null) {
            throw new HttpException(404, 'error.404');
        }
        return $tpl;
    }

    /**
     * Arrangement for a label of w × h mm: QR as large as possible (module size rounded to whole
     * printer dots), text beside it on landscape labels and below it on portrait labels.
     * @return array{vertical:bool,pad:float,gap:float,qr:float,font:float}
     */
    public static function layout(float $w, float $h, int $dpi): array
    {
        $vertical = $h > $w * 1.15;
        $pad = min($w, $h) * 0.06;
        $gap = $pad;
        if ($vertical) {
            $qr = LabelTemplate::qrSize(min($w - 2 * $pad, $h * 0.6), $dpi);
            $textW = $w - 2 * $pad;
            $textH = $h - 2 * $pad - $qr - $gap;
        } else {
            $qr = LabelTemplate::qrSize(min($h - 2 * $pad, $w * 0.55), $dpi);
            $textW = $w - 2 * $pad - $qr - $gap;
            $textH = $h - 2 * $pad;
        }
        // The 10-character code in a monospace font is about 6.4 em wide; three lines need about 4 em.
        $font = max(1.6, min($textW / 6.6, $textH / 4.2, 14.0));
        return ['vertical' => $vertical, 'pad' => $pad, 'gap' => $gap, 'qr' => $qr, 'font' => $font];
    }
}