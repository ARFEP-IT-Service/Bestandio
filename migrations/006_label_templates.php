<?php

// Bestandio: label templates in their own table. Built-in templates are added; the label layout
// saved so far under /settings becomes an own template and stays the default, so nothing is lost.
// The old rows in `settings` are left in place (no longer read).

declare(strict_types=1);

use Bestandio\Models\LabelTemplate;

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE label_templates (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        builtin_key   VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
        name          VARCHAR(100) NOT NULL DEFAULT '',
        kind          VARCHAR(10)  NOT NULL,
        width_mm      DECIMAL(5,2) NOT NULL,
        height_mm     DECIMAL(5,2) NOT NULL,
        dpi           SMALLINT UNSIGNED NOT NULL,
        orientation   VARCHAR(10)  NOT NULL DEFAULT 'portrait',
        sheet_cols    TINYINT UNSIGNED NULL,
        sheet_rows    TINYINT UNSIGNED NULL,
        margin_top    DECIMAL(5,2) NULL,
        margin_bottom DECIMAL(5,2) NULL,
        margin_left   DECIMAL(5,2) NULL,
        margin_right  DECIMAL(5,2) NULL,
        gap_x         DECIMAL(5,2) NULL,
        gap_y         DECIMAL(5,2) NULL,
        is_default    TINYINT(1)   NOT NULL DEFAULT 0,
        -- at most one default per kind, enforced by the database
        default_kind  VARCHAR(10) AS (IF(is_default = 1, kind, NULL)) STORED,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_label_templates_builtin (builtin_key),
        UNIQUE KEY uq_label_templates_default (default_kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cols = ['builtin_key', 'name', 'kind', 'width_mm', 'height_mm', 'dpi', 'orientation', 'sheet_cols', 'sheet_rows',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'gap_x', 'gap_y'];
    $insert = $pdo->prepare('INSERT INTO label_templates (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
    $add = static function (array $t) use ($insert, $cols, $pdo): int {
        $insert->execute(array_map(static fn ($c) => $t[$c] ?? null, $cols));
        return (int) $pdo->lastInsertId();
    };

    $ids = [];
    foreach (LabelTemplate::BUILTIN as $key => $t) {
        $ids[$key] = $add(['builtin_key' => $key, 'name' => ''] + $t);
    }

    // Settings saved so far (missing rows = the defaults that applied until now).
    $saved = [];
    foreach ($pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        $saved[$k] = $v;
    }
    $s = $saved + [
        'sheet_cols' => '3', 'sheet_rows' => '8', 'sheet_margin_top' => '4.5', 'sheet_margin_bottom' => '4.5',
        'sheet_margin_left' => '0', 'sheet_margin_right' => '0', 'sheet_gap_x' => '0', 'sheet_gap_y' => '0',
        'label_template' => 'custom', 'single_width' => '62', 'single_height' => '29', 'dpi' => '203',
    ];
    // A brand-new installation (nothing saved, no devices) simply starts with the built-in templates.
    $fresh = $saved === [] && (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn() === 0;
    $num = static fn (string $v): float => round((float) $v, 2);
    $label = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    // A4 sheet: the old settings gave margins and gaps; the label size followed from them.
    $sheetDefault = $ids['sheet_a4_3x8'];
    $sheet = [
        'kind' => 'sheet', 'orientation' => 'portrait', 'dpi' => (int) $s['dpi'],
        'sheet_cols' => (int) $s['sheet_cols'], 'sheet_rows' => (int) $s['sheet_rows'],
        'margin_top' => $num($s['sheet_margin_top']), 'margin_bottom' => $num($s['sheet_margin_bottom']),
        'margin_left' => $num($s['sheet_margin_left']), 'margin_right' => $num($s['sheet_margin_right']),
        'gap_x' => $num($s['sheet_gap_x']), 'gap_y' => $num($s['sheet_gap_y']),
    ];
    $sheet['width_mm'] = floor((LabelTemplate::A4_WIDTH - $sheet['margin_left'] - $sheet['margin_right'] - ($sheet['sheet_cols'] - 1) * $sheet['gap_x']) / $sheet['sheet_cols'] * 100 + 1e-6) / 100;
    $sheet['height_mm'] = floor((LabelTemplate::A4_HEIGHT - $sheet['margin_top'] - $sheet['margin_bottom'] - ($sheet['sheet_rows'] - 1) * $sheet['gap_y']) / $sheet['sheet_rows'] * 100 + 1e-6) / 100;
    $builtinSheet = LabelTemplate::BUILTIN['sheet_a4_3x8'];
    $sameAsBuiltin = true;
    foreach ($sheet as $k => $v) {
        $sameAsBuiltin = $sameAsBuiltin && ($k === 'dpi' || (string) $v === (string) $builtinSheet[$k]);
    }
    if (!$fresh && !($sameAsBuiltin && $sheet['dpi'] === $builtinSheet['dpi'])) {
        $sheet['name'] = 'A4 ' . $sheet['sheet_cols'] . ' × ' . $sheet['sheet_rows'] . ' · ' . $label($sheet['width_mm']) . ' × ' . $label($sheet['height_mm']) . ' mm · ' . $sheet['dpi'] . ' dpi';
        $sheetDefault = $add($sheet);
    }

    // Single label: the old fixed templates map to the built-in ones; the own size becomes an own template.
    $singleDefault = $ids['single_57x32'];
    $own = [
        'kind' => 'single', 'orientation' => 'portrait', 'dpi' => (int) $s['dpi'],
        'width_mm' => $num($s['single_width']), 'height_mm' => $num($s['single_height']),
    ];
    $own['name'] = $label($own['width_mm']) . ' × ' . $label($own['height_mm']) . ' mm · ' . $own['dpi'] . ' dpi';
    $ownChanged = isset($saved['single_width']) || isset($saved['single_height']);
    if ($s['label_template'] === 'dymo_54x101') {
        $singleDefault = $ids['single_54x101'];
    } elseif ($s['label_template'] === 'zebra_103x199') {
        $singleDefault = $ids['single_103x199'];
    } elseif (!$fresh) {
        $singleDefault = $add($own);
        $ownChanged = false;
    }
    if ($ownChanged && ($own['width_mm'] !== 62.0 || $own['height_mm'] !== 29.0)) {
        $add($own);
    }

    $pdo->prepare('UPDATE label_templates SET is_default = 1 WHERE id IN (?, ?)')->execute([$sheetDefault, $singleDefault]);
};
