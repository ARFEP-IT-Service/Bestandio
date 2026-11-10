<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;

/** Installed programs of a device, replaced as a whole by each import. */
final class Software
{
    public static function forDevice(int $deviceId): array
    {
        return Db::all('SELECT name, version, vendor FROM device_software WHERE device_id = ? ORDER BY name, version', [$deviceId]);
    }

    public static function count(int $deviceId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM device_software WHERE device_id = ?', [$deviceId]);
    }

    /**
     * Replace the list; records one history entry with old and new count.
     * @param list<array{name:string,version:string,vendor:string}> $items
     */
    public static function replace(int $deviceId, array $items, ?int $userId): void
    {
        Db::transaction(static function () use ($deviceId, $items, $userId): void {
            $old = self::count($deviceId);
            Db::run('DELETE FROM device_software WHERE device_id = ?', [$deviceId]);
            $stmt = Db::pdo()->prepare('INSERT INTO device_software (device_id, name, version, vendor) VALUES (?, ?, ?, ?)');
            foreach ($items as $it) {
                $stmt->execute([$deviceId, $it['name'], $it['version'], $it['vendor']]);
            }
            History::add($deviceId, $userId, 'software', (string) $old, (string) count($items));
        });
    }
}
