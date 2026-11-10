<?php

// Bestandio: fixed device code for QR labels. Existing devices get a code here.

declare(strict_types=1);

use Bestandio\Core\DeviceCode;

return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE devices ADD COLUMN code CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER id');

    $update = $pdo->prepare('UPDATE devices SET code = ? WHERE id = ?');
    $used = [];
    foreach ($pdo->query('SELECT id FROM devices WHERE code IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        do {
            $code = DeviceCode::generate();
        } while (isset($used[$code]));
        $used[$code] = true;
        $update->execute([$code, $id]);
    }

    $pdo->exec('ALTER TABLE devices MODIFY code CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        ADD UNIQUE KEY uq_devices_code (code)');
};
