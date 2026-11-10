<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;
use Bestandio\Core\SystemUuid;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Hardware scan import, format "bestandio-scan" version 1 (see docs/import-format.md).
 * Only hardware data; unknown fields (e.g. user or host names) are ignored and never stored.
 */
final class ScanImport
{
    public const FORMAT = 'bestandio-scan';
    public const VERSION = 1;
    public const MAX_BYTES = 1048576;
    public const MAX_DEPTH = 16;
    public const DISK_TYPES = ['ssd' => 'SSD', 'hdd' => 'HDD', 'nvme' => 'NVMe', 'emmc' => 'eMMC', 'other' => 'Disk'];

    private const ROOT_KEYS = ['format', 'version', 'scanned_at', 'hardware', 'os', 'software'];
    private const HARDWARE_KEYS = ['manufacturer', 'model', 'serial_number', 'uuid', 'cpu', 'ram_mb', 'disks'];
    private const DISK_KEYS = ['type', 'size_gb'];
    private const OS_KEYS = ['name', 'version'];
    private const SOFTWARE_KEYS = ['name', 'version', 'vendor'];

    /** Device fields an import can set or update, in display order. */
    public const DEVICE_FIELDS = ['manufacturer', 'model', 'serial_no', 'uuid', 'cpu', 'ram_mb', 'storage', 'os_name', 'os_version', 'scanned_at'];

    private array $errors = [];
    private int $ignored = 0;

    /**
     * Parse and validate. Returns [data|null, errors (list of [i18n key, params]), ignored field count].
     * data keys: manufacturer, serial_no, scanned_at and, when present, model, cpu, ram_mb, storage,
     * os_name, os_version, software (list).
     */
    public static function parse(string $json): array
    {
        $p = new self();
        $data = $p->run($json);
        return [$p->errors ? null : $data, $p->errors, $p->ignored];
    }

    private function error(string $key, array $params = []): void
    {
        $this->errors[] = [$key, $params];
    }

    private function run(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            $this->error('import.error.too_large');
            return [];
        }
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
        }
        if (trim($json) === '') {
            $this->error('import.error.empty');
            return [];
        }
        try {
            $root = json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            $this->error('import.error.json');
            return [];
        }
        if (!is_object($root)) {
            $this->error('import.error.structure', ['field' => '(root)']);
            return [];
        }
        $this->countUnknown($root, self::ROOT_KEYS);

        if (($root->format ?? null) !== self::FORMAT) {
            $this->error('import.error.format');
            return [];
        }
        if (!isset($root->version) || $root->version !== self::VERSION) {
            $this->error('import.error.version', ['version' => is_scalar($root->version ?? null) ? (string) $root->version : '?']);
            return [];
        }

        $out = [];
        $out['scanned_at'] = $this->dateTime($root->scanned_at ?? null);

        $hw = $root->hardware ?? null;
        if (!is_object($hw)) {
            $this->error('import.error.required', ['field' => 'hardware']);
            return $out;
        }
        $this->countUnknown($hw, self::HARDWARE_KEYS);
        $out['manufacturer'] = $this->string($hw, 'manufacturer', 'hardware.manufacturer', 100, true);
        // The key is required; the value may be empty when the firmware has no real serial number.
        // Such devices are never matched and are always created as new devices.
        if (!property_exists($hw, 'serial_number') || !is_string($hw->serial_number)) {
            $this->error('import.error.required', ['field' => 'hardware.serial_number']);
        }
        $out['serial_no'] = $this->string($hw, 'serial_number', 'hardware.serial_number', 100, false);
        foreach (['model' => 100, 'cpu' => 190] as $k => $max) {
            if (property_exists($hw, $k)) {
                $out[$k] = $this->string($hw, $k, 'hardware.' . $k, $max, false);
            }
        }
        if (property_exists($hw, 'uuid')) {
            if (!is_string($hw->uuid) || ($hw->uuid !== '' && !SystemUuid::wellFormed(trim($hw->uuid)))) {
                $this->error('import.error.invalid', ['field' => 'hardware.uuid']);
            } else {
                // Placeholders (all zeros, all F, known dummies) count as no UUID.
                $uuid = SystemUuid::normalize($hw->uuid);
                if ($uuid !== '') {
                    $out['uuid'] = $uuid;
                }
            }
        }
        if (property_exists($hw, 'ram_mb')) {
            $v = $hw->ram_mb;
            if (!is_int($v) || $v < 1 || $v > 16777216) {
                $this->error('import.error.invalid', ['field' => 'hardware.ram_mb']);
            } else {
                $out['ram_mb'] = $v;
            }
        }
        if (property_exists($hw, 'disks')) {
            $out['storage'] = $this->disks($hw->disks);
        }

        if (property_exists($root, 'os')) {
            if (!is_object($root->os)) {
                $this->error('import.error.invalid', ['field' => 'os']);
            } else {
                $this->countUnknown($root->os, self::OS_KEYS);
                if (property_exists($root->os, 'name')) {
                    $out['os_name'] = $this->string($root->os, 'name', 'os.name', 100, false);
                }
                if (property_exists($root->os, 'version')) {
                    $out['os_version'] = $this->string($root->os, 'version', 'os.version', 64, false);
                }
            }
        }

        if (property_exists($root, 'software')) {
            $out['software'] = $this->software($root->software);
        }
        return $out;
    }

    private function countUnknown(object $obj, array $known): void
    {
        foreach (array_keys(get_object_vars($obj)) as $k) {
            if (!in_array($k, $known, true)) {
                $this->ignored++;
            }
        }
    }

    private function string(object $obj, string $key, string $label, int $max, bool $required): string
    {
        $v = $obj->{$key} ?? null;
        if ($v === null && !$required) {
            return '';
        }
        if (!is_string($v)) {
            $this->error($required ? 'import.error.required' : 'import.error.invalid', ['field' => $label]);
            return '';
        }
        $v = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $v));
        if ($required && $v === '') {
            $this->error('import.error.required', ['field' => $label]);
        } elseif (mb_strlen($v) > $max) {
            $this->error('import.error.too_long', ['field' => $label, 'max' => $max]);
        }
        return $v;
    }

    private function dateTime(mixed $v): string
    {
        $re = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})$/';
        if (!is_string($v) || !preg_match($re, $v)) {
            $this->error('import.error.date', ['field' => 'scanned_at']);
            return '';
        }
        try {
            $dt = new DateTimeImmutable($v);
        } catch (\Exception) {
            $this->error('import.error.date', ['field' => 'scanned_at']);
            return '';
        }
        if ($dt->getTimestamp() > time() + 86400 || (int) $dt->format('Y') < 2000) {
            $this->error('import.error.date', ['field' => 'scanned_at']);
            return '';
        }
        return $dt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    private function disks(mixed $disks): string
    {
        if (!is_array($disks) || count($disks) > 32) {
            $this->error('import.error.invalid', ['field' => 'hardware.disks']);
            return '';
        }
        $parts = [];
        foreach ($disks as $i => $d) {
            $label = 'hardware.disks[' . $i . ']';
            if (!is_object($d)) {
                $this->error('import.error.invalid', ['field' => $label]);
                continue;
            }
            $this->countUnknown($d, self::DISK_KEYS);
            $type = is_string($d->type ?? null) ? strtolower(trim($d->type)) : '';
            $size = $d->size_gb ?? null;
            if (!isset(self::DISK_TYPES[$type])) {
                $this->error('import.error.invalid', ['field' => $label . '.type']);
                continue;
            }
            if (!(is_int($size) || is_float($size)) || $size <= 0 || $size > 1000000) {
                $this->error('import.error.invalid', ['field' => $label . '.size_gb']);
                continue;
            }
            $parts[] = self::DISK_TYPES[$type] . ' ' . rtrim(rtrim(number_format((float) $size, 1, '.', ''), '0'), '.') . ' GB';
        }
        return implode(', ', $parts);
    }

    /** @return list<array{name:string,version:string,vendor:string}> */
    private function software(mixed $list): array
    {
        if (!is_array($list) || count($list) > 5000) {
            $this->error('import.error.invalid', ['field' => 'software']);
            return [];
        }
        $out = [];
        foreach ($list as $i => $item) {
            $label = 'software[' . $i . ']';
            if (!is_object($item)) {
                $this->error('import.error.invalid', ['field' => $label]);
                continue;
            }
            $this->countUnknown($item, self::SOFTWARE_KEYS);
            $out[] = [
                'name'    => $this->string($item, 'name', $label . '.name', 190, true),
                'version' => $this->string($item, 'version', $label . '.version', 64, false),
                'vendor'  => $this->string($item, 'vendor', $label . '.vendor', 190, false),
            ];
            if (count($this->errors) > 50) {
                break;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------ apply

    /**
     * Existing devices for this scan: 1. manufacturer + serial number, 2. system UUID, 3. none.
     * Several matches are returned so the user can choose.
     */
    public static function matches(array $data): array
    {
        $bySerial = Device::findByManufacturerSerial($data['manufacturer'], $data['serial_no']);
        if ($bySerial) {
            return $bySerial;
        }
        return Device::findByUuid($data['uuid'] ?? '');
    }

    /** Fields of the parsed data that would change on the device: field => [old, new]. */
    public static function diff(array $device, array $data): array
    {
        $out = [];
        foreach (self::DEVICE_FIELDS as $f) {
            if (!array_key_exists($f, $data) || in_array($f, ['manufacturer', 'serial_no'], true)) {
                continue;
            }
            $old = $device[$f] === null ? '' : (string) $device[$f];
            $new = (string) $data[$f];
            if ($old !== $new) {
                $out[$f] = [$old, $new];
            }
        }
        if (array_key_exists('software', $data)) {
            $old = Software::count((int) $device['id']);
            $new = count($data['software']);
            $out['software'] = [(string) $old, (string) $new];
        }
        return $out;
    }

    /** Update an existing device with scan data. Manufacturer and serial number stay as they are. */
    public static function applyUpdate(int $deviceId, array $data, ?int $userId): void
    {
        Db::transaction(static function () use ($deviceId, $data, $userId): void {
            $fields = array_intersect_key($data, array_flip(array_diff(self::DEVICE_FIELDS, ['manufacturer', 'serial_no'])));
            Device::update($deviceId, $fields, $userId);
            if (array_key_exists('software', $data)) {
                Software::replace($deviceId, $data['software'], $userId);
            }
            History::add($deviceId, $userId, '_import', null, $data['scanned_at']);
        });
    }

    /** Create a new device from scan data plus the choices made in the preview. */
    public static function applyCreate(array $device, array $data, ?int $userId): int
    {
        return Db::transaction(static function () use ($device, $data, $userId): int {
            foreach (self::DEVICE_FIELDS as $f) {
                if (array_key_exists($f, $data)) {
                    $device[$f] = $data[$f];
                }
            }
            $id = Device::create($device, $userId);
            if (array_key_exists('software', $data)) {
                Software::replace($id, $data['software'], $userId);
            }
            History::add($id, $userId, '_import', null, $data['scanned_at']);
            return $id;
        });
    }
}
