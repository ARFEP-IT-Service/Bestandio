<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/** Per-device change log: who, when, field, old, new. */
final class History
{
    public static function add(int $deviceId, ?int $userId, string $field, ?string $old, ?string $new): void
    {
        Db::run(
            'INSERT INTO device_history (device_id, user_id, changed_at, field, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)',
            [$deviceId, $userId, date('Y-m-d H:i:s'), $field, $old, $new]
        );
    }

    public static function forDevice(int $deviceId): array
    {
        return Db::all(
            'SELECT h.changed_at, h.field, h.old_value, h.new_value, u.username
             FROM device_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.device_id = ? ORDER BY h.changed_at DESC, h.id DESC',
            [$deviceId]
        );
    }
}
