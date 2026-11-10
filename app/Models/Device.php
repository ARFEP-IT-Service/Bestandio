<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;
use Bestandio\Core\DeviceCode;
use Bestandio\Core\Request;

final class Device
{
    public const STATUSES = ['in_use', 'stock', 'repair', 'retired'];

    /** Editable fields in display order. */
    public const FIELDS = [
        'inventory_no', 'category_id', 'manufacturer', 'model', 'serial_no',
        'location_id', 'status', 'purchase_date', 'warranty_until', 'note',
    ];

    /** Hardware fields, filled by scan imports. */
    public const HARDWARE = ['cpu', 'ram_mb', 'storage', 'os_name', 'os_version', 'scanned_at'];

    /** Every column that create()/update() may write. The code is not among them. */
    public const WRITABLE = [
        'inventory_no', 'category_id', 'manufacturer', 'model', 'serial_no', 'location_id', 'status',
        'purchase_date', 'warranty_until', 'note', 'cpu', 'ram_mb', 'storage', 'os_name', 'os_version', 'scanned_at',
    ];

    /** Sort key => SQL column (whitelist). */
    public const SORTS = [
        'inventory_no'   => 'd.inventory_no',
        'category'       => 'c.name',
        'manufacturer'   => 'd.manufacturer',
        'model'          => 'd.model',
        'serial_no'      => 'd.serial_no',
        'location'       => 'l.name',
        'status'         => 'd.status',
        'purchase_date'  => 'd.purchase_date',
        'warranty_until' => 'd.warranty_until',
        'updated_at'     => 'd.updated_at',
    ];

    private const SELECT = 'SELECT d.*, c.name AS category, l.name AS location
        FROM devices d
        JOIN categories c ON c.id = d.category_id
        JOIN locations l ON l.id = d.location_id';

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE d.id = ?', [$id]);
    }

    /** Normalise raw form input into a device array. */
    public static function fromInput(array $in): array
    {
        $s = static fn (string $k): string => Request::clean($in[$k] ?? null) ?? '';
        return [
            'inventory_no'   => $s('inventory_no'),
            'category_id'    => (int) $s('category_id'),
            'manufacturer'   => $s('manufacturer'),
            'model'          => $s('model'),
            'serial_no'      => $s('serial_no'),
            'location_id'    => (int) $s('location_id'),
            'status'         => $s('status'),
            'purchase_date'  => $s('purchase_date') === '' ? null : $s('purchase_date'),
            'warranty_until' => $s('warranty_until') === '' ? null : $s('warranty_until'),
            'note'           => str_replace("\r\n", "\n", $s('note')),
        ];
    }

    /** @return array<string,string> field => i18n error key */
    public static function validate(array $d, int $exceptId = 0): array
    {
        $err = [];
        if ($d['inventory_no'] === '') {
            $err['inventory_no'] = 'validation.required';
        } elseif (mb_strlen($d['inventory_no']) > 64) {
            $err['inventory_no'] = 'validation.too_long';
        } elseif (Db::value('SELECT id FROM devices WHERE inventory_no = ? AND id <> ?', [$d['inventory_no'], $exceptId]) !== null) {
            $err['inventory_no'] = 'device.error.inventory_taken';
        }
        if ((new NamedList('categories'))->find($d['category_id']) === null) {
            $err['category_id'] = 'validation.required';
        }
        if ((new NamedList('locations'))->find($d['location_id']) === null) {
            $err['location_id'] = 'validation.required';
        }
        foreach (['manufacturer', 'model', 'serial_no'] as $f) {
            if (mb_strlen($d[$f]) > 100) {
                $err[$f] = 'validation.too_long';
            }
        }
        if (!in_array($d['status'], self::STATUSES, true)) {
            $err['status'] = 'validation.required';
        }
        foreach (['purchase_date', 'warranty_until'] as $f) {
            if ($d[$f] !== null && !self::validDate($d[$f])) {
                $err[$f] = 'validation.date';
            }
        }
        if (mb_strlen($d['note']) > 5000) {
            $err['note'] = 'validation.too_long';
        }
        return $err;
    }

    public static function validDate(string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 1900;
    }

    /** Inventory numbers of other devices with the same serial number. */
    public static function serialDuplicates(string $serial, int $exceptId = 0): array
    {
        if ($serial === '') {
            return [];
        }
        return Db::run('SELECT inventory_no FROM devices WHERE serial_no = ? AND id <> ? ORDER BY inventory_no', [$serial, $exceptId])
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function create(array $d, ?int $userId): int
    {
        return Db::transaction(static function () use ($d, $userId): int {
            $cols = array_values(array_filter(self::WRITABLE, static fn ($f) => array_key_exists($f, $d)));
            $params = array_map(static fn ($f) => $d[$f], $cols);
            // Fixed random code; retry in the unlikely case of a collision.
            for ($try = 1; ; $try++) {
                $code = DeviceCode::generate();
                if (Db::value('SELECT id FROM devices WHERE code = ?', [$code]) === null || $try >= 5) {
                    break;
                }
            }
            Db::run(
                'INSERT INTO devices (code, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ')',
                array_merge([$code], $params)
            );
            $id = (int) Db::pdo()->lastInsertId();
            History::add($id, $userId, '_created', null, $d['inventory_no']);
            return $id;
        });
    }

    public static function findByCode(string $code): ?array
    {
        return DeviceCode::valid($code) ? Db::one(self::SELECT . ' WHERE d.code = ?', [$code]) : null;
    }

    /** All devices with the given ids, ordered by inventory number. */
    public static function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($i) => $i > 0)));
        if (!$ids) {
            return [];
        }
        return Db::all(self::SELECT . ' WHERE d.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY d.inventory_no', $ids);
    }

    /** Devices matching manufacturer + serial number (case-insensitive, trimmed). */
    public static function findByManufacturerSerial(string $manufacturer, string $serial): array
    {
        return Db::all(
            self::SELECT . ' WHERE LOWER(TRIM(d.manufacturer)) = LOWER(?) AND LOWER(TRIM(d.serial_no)) = LOWER(?) ORDER BY d.inventory_no',
            [trim($manufacturer), trim($serial)]
        );
    }

    /**
     * Update the given fields (any subset of WRITABLE) and record one history row per change.
     * The device code is never written. Returns the number of changed fields.
     */
    public static function update(int $id, array $d, ?int $userId): int
    {
        return Db::transaction(static function () use ($id, $d, $userId): int {
            $old = Db::one(self::SELECT . ' WHERE d.id = ? FOR UPDATE', [$id]);
            if ($old === null) {
                return 0;
            }
            $changes = [];
            foreach (self::WRITABLE as $f) {
                if (!array_key_exists($f, $d)) {
                    continue;
                }
                $before = $old[$f] === null ? null : (string) $old[$f];
                $after = $d[$f] === null ? null : (string) $d[$f];
                if ($before !== $after) {
                    $changes[$f] = [$before, $after];
                }
            }
            if ($changes) {
                $set = implode(', ', array_map(static fn ($f) => "$f = ?", array_keys($changes)));
                $params = array_map(static fn ($c) => $c[1], array_values($changes));
                $params[] = $id;
                Db::run("UPDATE devices SET $set WHERE id = ?", $params);
                $cats = (new NamedList('categories'))->options();
                $locs = (new NamedList('locations'))->options();
                foreach ($changes as $f => [$before, $after]) {
                    // Store readable names for list references.
                    if ($f === 'category_id') {
                        [$f, $before, $after] = ['category', $old['category'], $cats[(int) $after] ?? $after];
                    } elseif ($f === 'location_id') {
                        [$f, $before, $after] = ['location', $old['location'], $locs[(int) $after] ?? $after];
                    }
                    History::add($id, $userId, $f, $before, $after);
                }
            }
            return count($changes);
        });
    }
    /**
     * Filtered list.
     * @param array{q?:string,category?:int,location?:int,status?:string} $f
     * @return array{rows:list<array>,total:int}
     */
    public static function search(array $f, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::where($f);
        $total = (int) Db::value(
            "SELECT COUNT(*) FROM devices d JOIN categories c ON c.id = d.category_id
             JOIN locations l ON l.id = d.location_id $where",
            $params
        );
        $order = self::order($sort, $dir);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Db::all(self::SELECT . " $where ORDER BY $order LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset, $params);
        return ['rows' => $rows, 'total' => $total];
    }

    /** All rows matching the filter (for export), streamed. */
    public static function each(array $f, string $sort, string $dir): \Generator
    {
        [$where, $params] = self::where($f);
        $stmt = Db::run(self::SELECT . " $where ORDER BY " . self::order($sort, $dir), $params);
        while (($row = $stmt->fetch()) !== false) {
            yield $row;
        }
    }

    private static function order(string $sort, string $dir): string
    {
        $col = self::SORTS[$sort] ?? self::SORTS['inventory_no'];
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        return "$col $dir, d.id $dir";
    }

    private static function where(array $f): array
    {
        $parts = [];
        $params = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $parts[] = '(d.inventory_no LIKE ? OR d.code LIKE ? OR d.manufacturer LIKE ? OR d.model LIKE ? OR d.serial_no LIKE ? OR d.note LIKE ?)';
            // The code column is case-sensitive (ascii_bin); codes are upper case.
            array_push($params, $like, strtoupper($like), $like, $like, $like, $like);
        }
        if (!empty($f['category'])) {
            $parts[] = 'd.category_id = ?';
            $params[] = (int) $f['category'];
        }
        if (!empty($f['location'])) {
            $parts[] = 'd.location_id = ?';
            $params[] = (int) $f['location'];
        }
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $parts[] = 'd.status = ?';
            $params[] = $f['status'];
        }
        return [$parts ? 'WHERE ' . implode(' AND ', $parts) : '', $params];
    }
}
