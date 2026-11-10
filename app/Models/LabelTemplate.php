<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/**
 * Label templates (all lengths in mm). Kind "single" = one label per page for label printers,
 * "sheet" = A4 sheet with a grid. Width × height is the label as it comes off the roll / sheet;
 * orientation "landscape" turns a single label by 90° (page = height × width) and an A4 sheet to A4 landscape.
 * Built-in templates are fixed: they cannot be changed or deleted, only copied. One template per kind is the default.
 */
final class LabelTemplate
{
    public const KINDS = ['sheet', 'single'];
    public const ORIENTATIONS = ['portrait', 'landscape'];

    public const A4_WIDTH = 210.0;
    public const A4_HEIGHT = 297.0;
    /** Rounding tolerance when checking that a grid fits on the sheet. */
    private const FIT_TOLERANCE = 0.1;

    /** builtin_key => template. Sizes only, no manufacturer article numbers. */
    public const BUILTIN = [
        'sheet_a4_3x8'   => ['kind' => 'sheet', 'width_mm' => 70.0, 'height_mm' => 36.0, 'dpi' => 300, 'orientation' => 'portrait',
                             'sheet_cols' => 3, 'sheet_rows' => 8, 'margin_top' => 4.5, 'margin_bottom' => 4.5,
                             'margin_left' => 0.0, 'margin_right' => 0.0, 'gap_x' => 0.0, 'gap_y' => 0.0],
        'single_50x25'   => ['kind' => 'single', 'width_mm' => 50.0, 'height_mm' => 25.0, 'dpi' => 203, 'orientation' => 'portrait'],
        'single_57x32'   => ['kind' => 'single', 'width_mm' => 57.0, 'height_mm' => 32.0, 'dpi' => 203, 'orientation' => 'portrait'],
        'single_54x101'  => ['kind' => 'single', 'width_mm' => 54.0, 'height_mm' => 101.0, 'dpi' => 300, 'orientation' => 'landscape'],
        'single_102x152' => ['kind' => 'single', 'width_mm' => 102.0, 'height_mm' => 152.0, 'dpi' => 203, 'orientation' => 'portrait'],
        'single_103x199' => ['kind' => 'single', 'width_mm' => 103.0, 'height_mm' => 199.0, 'dpi' => 203, 'orientation' => 'portrait'],
    ];

    /** Numeric fields: name => [min, max, integer]. Sheet fields only apply to kind "sheet". */
    public const NUMBERS = [
        'width_mm'      => [10, 400, false],
        'height_mm'     => [10, 400, false],
        'dpi'           => [100, 1200, true],
        'sheet_cols'    => [1, 10, true],
        'sheet_rows'    => [1, 30, true],
        'margin_top'    => [0, 50, false],
        'margin_bottom' => [0, 50, false],
        'margin_left'   => [0, 50, false],
        'margin_right'  => [0, 50, false],
        'gap_x'         => [0, 20, false],
        'gap_y'         => [0, 20, false],
    ];
    public const SHEET_FIELDS = ['sheet_cols', 'sheet_rows', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'gap_x', 'gap_y'];

    private const COLUMNS = ['name', 'kind', 'width_mm', 'height_mm', 'dpi', 'orientation', 'sheet_cols', 'sheet_rows',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'gap_x', 'gap_y'];

    /** All templates: sheets first, then single labels; built-in before own, then by name. */
    public static function all(): array
    {
        $rows = Db::all('SELECT * FROM label_templates ORDER BY kind = \'single\', builtin_key IS NULL, width_mm, height_mm, name, id');
        return array_map([self::class, 'cast'], $rows);
    }

    public static function find(int $id): ?array
    {
        $row = Db::one('SELECT * FROM label_templates WHERE id = ?', [$id]);
        return $row === null ? null : self::cast($row);
    }

    public static function defaultFor(string $kind): ?array
    {
        $row = Db::one('SELECT * FROM label_templates WHERE kind = ? ORDER BY is_default DESC, builtin_key IS NULL, id LIMIT 1', [$kind]);
        return $row === null ? null : self::cast($row);
    }

    /** Typed values (DECIMAL comes back as string). */
    public static function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_default'] = (int) $row['is_default'] === 1;
        foreach (self::NUMBERS as $name => [, , $int]) {
            if ($row[$name] !== null) {
                $row[$name] = $int ? (int) $row[$name] : (float) $row[$name];
            }
        }
        return $row;
    }

    public static function isBuiltin(array $t): bool
    {
        return ($t['builtin_key'] ?? null) !== null;
    }

    /**
     * Name to show. Built-in templates have no stored name; theirs is built from the sizes,
     * e.g. "54 × 101 mm, landscape" or "A4 3 × 8 · 70 × 36 mm".
     */
    public static function displayName(array $t): string
    {
        if (!self::isBuiltin($t)) {
            return (string) $t['name'];
        }
        $size = self::num($t['width_mm']) . ' × ' . self::num($t['height_mm']) . ' mm';
        if ($t['kind'] === 'sheet') {
            return 'A4 ' . $t['sheet_cols'] . ' × ' . $t['sheet_rows'] . ' · ' . $size;
        }
        return $t['orientation'] === 'landscape' ? $size . ', ' . t('tpl.orientation.landscape_short') : $size;
    }

    /** Page size in mm: [width, height]. */
    public static function pageSize(array $t): array
    {
        if ($t['kind'] === 'sheet') {
            return $t['orientation'] === 'landscape' ? [self::A4_HEIGHT, self::A4_WIDTH] : [self::A4_WIDTH, self::A4_HEIGHT];
        }
        return $t['orientation'] === 'landscape' ? [(float) $t['height_mm'], (float) $t['width_mm']] : [(float) $t['width_mm'], (float) $t['height_mm']];
    }

    /** Printed area of one label in mm: [width, height]. */
    public static function labelSize(array $t): array
    {
        return $t['kind'] === 'sheet' ? [(float) $t['width_mm'], (float) $t['height_mm']] : self::pageSize($t);
    }

    public static function perSheet(array $t): int
    {
        return $t['kind'] === 'sheet' ? (int) $t['sheet_cols'] * (int) $t['sheet_rows'] : 1;
    }

    /**
     * Validate raw input.
     * @return array{0:array,1:array<string,string>} values, errors (field => i18n key)
     */
    public static function validate(array $in): array
    {
        $str = static fn (string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
        $errors = [];
        $values = [
            'name'        => $str('name'),
            'kind'        => in_array($str('kind'), self::KINDS, true) ? $str('kind') : 'single',
            'orientation' => in_array($str('orientation'), self::ORIENTATIONS, true) ? $str('orientation') : 'portrait',
        ];
        if ($values['name'] === '' || !mb_check_encoding($values['name'], 'UTF-8') || mb_strlen($values['name']) > 100) {
            $errors['name'] = 'tpl.error.name';
            $values['name'] = mb_check_encoding($values['name'], 'UTF-8') ? $values['name'] : '';
        }
        if (!in_array($str('kind'), self::KINDS, true)) {
            $errors['kind'] = 'validation.required';
        }
        foreach (self::NUMBERS as $name => [$min, $max, $int]) {
            if ($values['kind'] !== 'sheet' && in_array($name, self::SHEET_FIELDS, true)) {
                $values[$name] = null;
                continue;
            }
            $raw = str_replace(',', '.', $str($name));
            if (!preg_match($int ? '/^\d{1,4}$/' : '/^\d{1,3}(\.\d{1,2})?$/', $raw) || (float) $raw < $min || (float) $raw > $max) {
                $errors[$name] = 'settings.error.range';
                $values[$name] = $raw;
                continue;
            }
            $values[$name] = $int ? (int) $raw : (float) $raw;
        }
        if (!$errors && $values['kind'] === 'sheet') {
            [$pw, $ph] = self::pageSize($values);
            $usedW = $values['margin_left'] + $values['margin_right'] + $values['sheet_cols'] * $values['width_mm'] + ($values['sheet_cols'] - 1) * $values['gap_x'];
            $usedH = $values['margin_top'] + $values['margin_bottom'] + $values['sheet_rows'] * $values['height_mm'] + ($values['sheet_rows'] - 1) * $values['gap_y'];
            if ($usedW > $pw + self::FIT_TOLERANCE) {
                $errors['width_mm'] = 'tpl.error.too_wide';
            }
            if ($usedH > $ph + self::FIT_TOLERANCE) {
                $errors['height_mm'] = 'tpl.error.too_high';
            }
        }
        return [$values, $errors];
    }

    /** Form values for a copy of an existing template. */
    public static function copyValues(array $t): array
    {
        $v = array_intersect_key($t, array_flip(self::COLUMNS));
        $v['name'] = t('tpl.copy_of', ['name' => self::displayName($t)]);
        return $v;
    }

    public static function create(array $v): int
    {
        $cols = self::COLUMNS;
        Db::run('INSERT INTO label_templates (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_map(static fn ($c) => $v[$c] ?? null, $cols));
        return (int) Db::pdo()->lastInsertId();
    }

    /** Built-in templates are never changed. The kind is fixed after creation (a default must stay of its kind). */
    public static function update(int $id, array $v): void
    {
        $cols = array_values(array_diff(self::COLUMNS, ['kind']));
        Db::run('UPDATE label_templates SET ' . implode(', ', array_map(static fn ($c) => $c . ' = ?', $cols)) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND builtin_key IS NULL',
            array_merge(array_map(static fn ($c) => $v[$c] ?? null, $cols), [$id]));
    }

    /** @return string|null i18n key of the reason when the template may not be deleted */
    public static function deleteBlocker(array $t): ?string
    {
        if (self::isBuiltin($t)) {
            return 'tpl.error.builtin';
        }
        if ($t['is_default']) {
            return 'tpl.error.default';
        }
        return null;
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM label_templates WHERE id = ? AND builtin_key IS NULL AND is_default = 0', [$id]);
    }

    public static function setDefault(array $t): void
    {
        Db::transaction(static function () use ($t): void {
            Db::run('UPDATE label_templates SET is_default = 0 WHERE kind = ? AND id <> ?', [$t['kind'], $t['id']]);
            Db::run('UPDATE label_templates SET is_default = 1 WHERE id = ?', [$t['id']]);
        });
    }

    /**
     * Edge length of the QR image in mm so that one module is a whole number of printer dots.
     * The SVG has 21 modules plus a 4-module quiet zone on each side (29 in total).
     */
    public static function qrSize(float $maxMm, int $dpi, int $modules = 29): float
    {
        $dotsPerModule = max(1, (int) floor($maxMm / $modules * $dpi / 25.4));
        return $dotsPerModule * $modules * 25.4 / $dpi;
    }

    /** 70.0 → "70", 4.5 → "4.5". */
    public static function num(float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
