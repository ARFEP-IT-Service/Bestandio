<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/** Admin settings: company name. Label sizes are label templates (see LabelTemplate). */
final class Settings
{
    /** name => default (text values). */
    public const FIELDS = [
        'company_name' => '',
    ];

    public static function all(): array
    {
        $out = self::FIELDS;
        foreach (Db::all('SELECT name, value FROM settings') as $row) {
            if (array_key_exists($row['name'], self::FIELDS)) {
                $out[$row['name']] = $row['value'];
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
        foreach (self::FIELDS as $name => $default) {
            $raw = is_string($in[$name] ?? null) ? trim($in[$name]) : '';
            if (!mb_check_encoding($raw, 'UTF-8') || mb_strlen($raw) > 100) {
                $errors[$name] = 'validation.too_long';
            }
            $values[$name] = mb_check_encoding($raw, 'UTF-8') ? $raw : '';
        }
        return [$values, $errors];
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
