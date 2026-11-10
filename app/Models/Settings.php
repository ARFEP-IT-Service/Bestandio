<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/** Admin settings: company name and label layout (all lengths in mm). */
final class Settings
{
    /** name => [default, min, max]; min/max null for text. */
    public const FIELDS = [
        'company_name'        => ['', null, null],
        'sheet_cols'          => [3, 1, 10],
        'sheet_rows'          => [8, 1, 30],
        'sheet_margin_top'    => [4.5, 0, 50],
        'sheet_margin_bottom' => [4.5, 0, 50],
        'sheet_margin_left'   => [0, 0, 50],
        'sheet_margin_right'  => [0, 0, 50],
        'sheet_gap_x'         => [0, 0, 20],
        'sheet_gap_y'         => [0, 0, 20],
        'single_width'        => [62, 20, 150],
        'single_height'       => [29, 10, 150],
    ];

    public const SHEET_WIDTH = 210.0;
    public const SHEET_HEIGHT = 297.0;
    public const MIN_LABEL_WIDTH = 20.0;
    public const MIN_LABEL_HEIGHT = 10.0;

    public static function all(): array
    {
        $out = [];
        foreach (self::FIELDS as $name => [$default]) {
            $out[$name] = $default;
        }
        foreach (Db::all('SELECT name, value FROM settings') as $row) {
            if (isset(self::FIELDS[$row['name']])) {
                $out[$row['name']] = self::FIELDS[$row['name']][1] === null ? $row['value'] : (float) $row['value'];
            }
        }
        return $out;
    }

    /**
     * Validate raw input.
     * @return array{0:array,1:array<string,string>} values, errors (field => i18n key)
     */
    public static function validate(array $in): array
    {
        $values = [];
        $errors = [];
        foreach (self::FIELDS as $name => [$default, $min, $max]) {
            $raw = is_string($in[$name] ?? null) ? trim($in[$name]) : '';
            if ($min === null) {
                if (!mb_check_encoding($raw, 'UTF-8') || mb_strlen($raw) > 100) {
                    $errors[$name] = 'validation.too_long';
                }
                $values[$name] = mb_check_encoding($raw, 'UTF-8') ? $raw : '';
                continue;
            }
            $raw = str_replace(',', '.', $raw);
            $isInt = in_array($name, ['sheet_cols', 'sheet_rows'], true);
            if (!preg_match($isInt ? '/^\d{1,2}$/' : '/^\d{1,3}(\.\d)?$/', $raw) || (float) $raw < $min || (float) $raw > $max) {
                $errors[$name] = 'settings.error.range';
                $values[$name] = $raw;
                continue;
            }
            $values[$name] = $isInt ? (int) $raw : (float) $raw;
        }
        if (!$errors) {
            [$w, $h] = self::labelSize($values);
            if ($w < self::MIN_LABEL_WIDTH || $h < self::MIN_LABEL_HEIGHT) {
                $errors['sheet_cols'] = 'settings.error.too_small';
            }
        }
        return [$values, $errors];
    }

    /** Label width and height on the A4 sheet in mm. */
    public static function labelSize(array $s): array
    {
        $cols = max(1, (int) $s['sheet_cols']);
        $rows = max(1, (int) $s['sheet_rows']);
        $w = (self::SHEET_WIDTH - $s['sheet_margin_left'] - $s['sheet_margin_right'] - ($cols - 1) * $s['sheet_gap_x']) / $cols;
        $h = (self::SHEET_HEIGHT - $s['sheet_margin_top'] - $s['sheet_margin_bottom'] - ($rows - 1) * $s['sheet_gap_y']) / $rows;
        return [round($w, 2), round($h, 2)];
    }

    public static function save(array $values): void
    {
        Db::transaction(static function () use ($values): void {
            $stmt = Db::pdo()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
            foreach (self::FIELDS as $name => $_) {
                $stmt->execute([$name, (string) $values[$name]]);
            }
        });
    }
}
