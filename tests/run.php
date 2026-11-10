<?php

declare(strict_types=1);

// Own test runner, no external packages: php tests/run.php
// DB tests run only when BESTANDIO_TEST_DB_* environment variables are set.
// WARNING: DB tests drop all tables in that database.

require dirname(__DIR__) . '/app/bootstrap.php';

use Bestandio\Core\Config;
use Bestandio\Core\Csrf;
use Bestandio\Core\Csv;
use Bestandio\Core\Clock;
use Bestandio\Core\Db;
use Bestandio\Core\DeviceCode;
use Bestandio\Core\HttpException;
use Bestandio\Core\I18n;
use Bestandio\Core\Migrator;
use Bestandio\Core\Qr;
use Bestandio\Core\Session;
use Bestandio\Core\Router;
use Bestandio\Models\Device;
use Bestandio\Models\History;
use Bestandio\Models\LabelTemplate;
use Bestandio\Models\LoginThrottle;
use Bestandio\Models\NamedList;
use Bestandio\Models\ScanImport;
use Bestandio\Models\Settings;
use Bestandio\Models\Software;
use Bestandio\Models\User;

$tests = [];
$test = static function (string $name, callable $fn) use (&$tests): void {
    $tests[] = [$name, $fn];
};
// DB tests are always registered; without a test database they are counted as skipped, not hidden.
$dbTests = [];
$dbTest = static function (string $name, callable $fn) use (&$dbTests): void {
    $dbTests[] = [$name, $fn];
};
$assert = static function (bool $cond, string $msg = 'assertion failed'): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
};
$phpFiles = static function (string $dir): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);
    return $out;
};

// ---------------------------------------------------------------- unit tests

$test('e() escapes HTML special characters', function () use ($assert) {
    $assert(e('<a href="x">\'&') === '&lt;a href=&quot;x&quot;&gt;&#039;&amp;');
    $assert(e(null) === '' && e(5) === '5');
});

$test('CSV neutralises formula cells', function () use ($assert) {
    foreach (['=1+1', '+49', '-2', '@SUM(A1)', "\tx", "\rx"] as $v) {
        $assert(Csv::cell($v) === "'" . $v, 'not neutralised: ' . json_encode($v));
    }
    $assert(Csv::cell('Laptop') === 'Laptop');
    $assert(Csv::cell('') === '');
    $assert(Csv::line(['a"b', '=x', null]) === "\"a\"\"b\";\"'=x\";\"\"\r\n");
});

$test('i18n: DE, EN and TR have identical keys and no empty texts', function () use ($assert) {
    $t = I18n::texts();
    $de = array_keys($t['de']);
    sort($de);
    foreach (['en', 'tr'] as $l) {
        $k = array_keys($t[$l]);
        sort($k);
        $assert($k === $de, "keys differ in $l: " . implode(',', array_merge(array_diff($de, $k), array_diff($k, $de))));
        foreach ($t[$l] as $key => $text) {
            $assert(is_string($text) && trim($text) !== '', "empty text $l.$key");
        }
    }
    $assert(array_keys($t) === ['de', 'en', 'tr']);
});

$test('i18n: every literal key used in code exists', function () use ($assert, $phpFiles) {
    $t = I18n::texts()['de'];
    $missing = [];
    foreach ($phpFiles(BESTANDIO_ROOT . '/app') as $file) {
        preg_match_all("/(?:\\bt\\(|flash\\('\\w+', |error\\(\\d+, |HttpException\\(\\d+, )'([a-z_]+\\.[a-z0-9_.]*[a-z0-9_])'/", (string) file_get_contents($file), $m);
        foreach ($m[1] as $key) {
            if (!isset($t[$key])) {
                $missing[] = $key;
            }
        }
    }
    $assert(!$missing, 'missing keys: ' . implode(', ', array_unique($missing)));
    foreach (array_merge(Device::STATUSES) as $s) {
        $assert(isset($t['status.' . $s]));
    }
    foreach (User::ROLES as $r) {
        $assert(isset($t['role.' . $r]));
    }
    foreach (['inventory_no', 'category', 'manufacturer', 'model', 'serial_no', 'location', 'status', 'purchase_date', 'warranty_until', 'note', 'updated_at'] as $f) {
        $assert(isset($t['device.' . $f]), "device.$f");
    }
});

$test('i18n: Accept-Language negotiation', function () use ($assert) {
    $assert(I18n::negotiate('de-DE,de;q=0.9,en;q=0.8') === 'de');
    $assert(I18n::negotiate('tr-TR,tr;q=0.9') === 'tr');
    $assert(I18n::negotiate('fr-FR,en;q=0.5') === 'en');
    $assert(I18n::negotiate('fr-FR,tr;q=0.8,de;q=0.9') === 'de');
    $assert(I18n::negotiate('') === I18n::FALLBACK);
});

$test('Router matches parameters and reports 404/405', function () use ($assert) {
    $r = new Router();
    $r->get('/devices/{id}', ['X', 'show']);
    $r->post('/devices/{id}', ['X', 'update']);
    [$h, $p] = $r->match('GET', '/devices/42');
    $assert($h === ['X', 'show'] && $p === ['id' => '42']);
    foreach ([['GET', '/devices/0'], ['GET', '/devices/abc'], ['GET', '/nope']] as [$m, $path]) {
        try {
            $r->match($m, $path);
            $assert(false, "matched $path");
        } catch (HttpException $ex) {
            $assert($ex->status === 404);
        }
    }
    try {
        $r->match('DELETE', '/devices/1');
        $assert(false);
    } catch (HttpException $ex) {
        $assert($ex->status === 405);
    }
});

$test('Every route handler exists', function () use ($assert) {
    foreach (Bestandio\Core\App::router()->routes() as [, $pattern, [$class, $method]]) {
        $assert(method_exists($class, $method), "$pattern -> $class::$method");
    }
});

$test('Migrator splits SQL files', function () use ($assert) {
    $parts = Migrator::split("-- comment\nCREATE TABLE a (x INT);\n\nCREATE TABLE b (\n y INT\n);\n");
    $assert(count($parts) === 2 && str_starts_with($parts[1], 'CREATE TABLE b'));
    $assert(count(Migrator::split((string) file_get_contents(BESTANDIO_ROOT . '/migrations/001_init.sql'))) === 6);
    $assert(array_keys(Migrator::files()) === ['001', '002', '003', '004', '005', '006']);
});

$test('Validation helpers', function () use ($assert) {
    $assert(Device::validDate('2026-02-28') && !Device::validDate('2026-02-30') && !Device::validDate('28.02.2026'));
    $assert(User::validUsername('admin') && User::validUsername('a.b-c_d') && !User::validUsername('ab') && !User::validUsername('a b'));
    $assert(User::passwordErrors('short', 'short') === ['user.error.password_short']);
    $assert(User::passwordErrors('elevenchars', 'elevenchars') === ['user.error.password_short'], '11 chars must be rejected');
    $assert(User::passwordErrors('twelve-chars', 'twelve-charz') === ['user.error.password_mismatch']);
    $assert(User::passwordErrors('twelve-chars', 'twelve-chars') === []);
});

$test('Request input: invalid UTF-8 and arrays are rejected', function () use ($assert) {
    $assert(Bestandio\Core\Request::clean(" Büro ") === 'Büro');
    $assert(Bestandio\Core\Request::clean("B\xFCro") === null && Bestandio\Core\Request::clean(['x']) === null);
    $assert(Device::fromInput(['inventory_no' => "A\xFC"])['inventory_no'] === '');
});

$test('Minimum versions: PHP 8.3, MySQL 8.4, MariaDB 10.11', function () use ($assert) {
    foreach (['8.4.0' => true, '8.4.11' => true, '9.1.0' => true, '8.0.40' => false, '8.3.99' => false,
        '10.11.19-MariaDB' => true, '11.4.2-MariaDB-log' => true, '10.6.20-MariaDB' => false, '10.4.32-MariaDB' => false, 'abc' => false] as $v => $ok) {
        $assert(Db::versionOk($v) === $ok, "db version $v");
    }
    $assert(Db::phpOk('8.3.0') && Db::phpOk('8.5.12') && !Db::phpOk('8.2.29'));
});

$test('System UUID: canonical form, placeholders count as empty', function () use ($assert) {
    $validScan = ['format' => 'bestandio-scan', 'version' => 1, 'scanned_at' => '2026-11-10T19:30:00Z', 'hardware' => ['manufacturer' => 'Dell', 'serial_number' => 'H7K2LM3']];
    $assert(Bestandio\Core\SystemUuid::normalize(' 4c4c4544-0042-3510-8051-b4c04f4a3533 ') === '4C4C4544-0042-3510-8051-B4C04F4A3533');
    foreach (['00000000-0000-0000-0000-000000000000', 'FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF', '03000200-0400-0500-0006-000700080009', ''] as $p) {
        $assert(Bestandio\Core\SystemUuid::normalize($p) === '', "placeholder $p");
    }
    [$d] = ScanImport::parse(json_encode(array_replace_recursive($validScan, ['hardware' => ['uuid' => '4c4c4544-0042-3510-8051-b4c04f4a3533']])));
    $assert($d['uuid'] === '4C4C4544-0042-3510-8051-B4C04F4A3533');
    [$d] = ScanImport::parse(json_encode(array_replace_recursive($validScan, ['hardware' => ['uuid' => '03000200-0400-0500-0006-000700080009']])));
    $assert($d !== null && !isset($d['uuid']), 'placeholder uuid dropped');
    [$d, $err] = ScanImport::parse(json_encode(array_replace_recursive($validScan, ['hardware' => ['uuid' => 'not-a-uuid']])));
    $assert($d === null && $err[0][0] === 'import.error.invalid');
});

$test('Version comes only from the VERSION file; sample data lives in tests/fixtures', function () use ($assert) {
    $assert(BESTANDIO_VERSION === trim((string) file_get_contents(BESTANDIO_ROOT . '/VERSION')));
    $assert((bool) preg_match('/^\d+\.\d+\.\d+$/', BESTANDIO_VERSION), 'semantic version');
    $assert(is_file(BESTANDIO_ROOT . '/tests/fixtures/sample-data.sql') && !is_file(BESTANDIO_ROOT . '/docs/sample-data.sql'));
});

$test('PWA: manifest, icons, service worker caches static files only, CSP', function () use ($assert) {
    $m = json_decode((string) file_get_contents(BESTANDIO_ROOT . '/public/manifest.json'), true);
    $assert(is_array($m), 'manifest is JSON');
    foreach (['name', 'short_name', 'start_url', 'display', 'icons', 'background_color', 'theme_color'] as $k) {
        $assert(!empty($m[$k]), "manifest.$k");
    }
    $assert($m['display'] === 'standalone');
    $sizes = [];
    foreach ($m['icons'] as $i) {
        $file = BESTANDIO_ROOT . '/public/' . $i['src'];
        $assert(is_file($file), 'icon ' . $i['src']);
        if ($i['type'] === 'image/png') {
            [$w, $h] = getimagesize($file);
            $assert($i['sizes'] === "{$w}x{$h}", 'icon size ' . $i['src']);
            $sizes[] = $i['sizes'] . '/' . ($i['purpose'] ?? 'any');
        }
    }
    $assert(in_array('192x192/any', $sizes, true) && in_array('512x512/any', $sizes, true) && in_array('512x512/maskable', $sizes, true));
    [$w, $h] = getimagesize(BESTANDIO_ROOT . '/public/assets/icons/apple-touch-icon.png');
    $assert($w === 180 && $h === 180, 'apple-touch-icon 180');
    $sw = (string) file_get_contents(BESTANDIO_ROOT . '/public/sw.js');
    $assert(str_contains($sw, "scopePath + 'assets/'") && str_contains($sw, "indexOf('text/html') === -1"), 'sw: assets only, no HTML');
    $assert(!preg_match('/cache\.(addAll|add)\(/', $sw), 'sw: no precaching of pages');
    $csp = Bestandio\Core\App::CSP;
    $assert(str_contains($csp, "worker-src 'self'") && str_contains($csp, "manifest-src 'self'") && !str_contains($csp, 'unsafe'));
    $assert(str_contains($csp, "default-src 'none'") && str_contains($csp, "script-src 'self'") && str_contains($csp, "style-src 'self'"));
});

$test('CSRF token check', function () use ($assert) {
    $_SESSION = [];
    $assert(!Csrf::valid('x'), 'no token in session');
    $tok = Csrf::token();
    $assert(strlen($tok) === 64 && Csrf::valid($tok));
    $assert(!Csrf::valid(null) && !Csrf::valid('') && !Csrf::valid(substr($tok, 1)) && !Csrf::valid([$tok]));
    $_SESSION = [];
});

$test('Views: every output goes through e(), no inline style/script, no external URLs', function () use ($assert, $phpFiles) {
    foreach ($phpFiles(BESTANDIO_ROOT . '/app/Views') as $file) {
        $src = (string) file_get_contents($file);
        $name = basename(dirname($file)) . '/' . basename($file);
        $assert(preg_match_all('/<\?=(?!\s*e\()/', $src) === 0, "raw output in $name");
        $assert(!preg_match('/(?<![.\w-])(?:echo|print)(?![\w.-])\s*[(\'"$]/', $src), "echo/print in $name");
        $assert(!preg_match('/\sstyle\s*=|<style\b|\son[a-z]+\s*=/i', $src), "inline style/handler in $name");
        $assert(!preg_match('/<script(?![^>]*\ssrc=)/i', $src), "inline script in $name");
        $assert(!preg_match('#(?:https?:)?//[a-z0-9-]+\.[a-z]#i', $src), "external URL in $name");
    }
});

$test('Device codes: 1000 generated, no duplicates, only alphabet, random_int not uniqid', function () use ($assert, $phpFiles) {
    $codes = [];
    for ($i = 0; $i < 1000; $i++) {
        $codes[] = DeviceCode::generate();
    }
    $assert(count(array_unique($codes)) === 1000, 'duplicates');
    foreach ($codes as $c) {
        $assert(strlen($c) === 10 && strspn($c, DeviceCode::ALPHABET) === 10, "bad code $c");
    }
    foreach (array_merge($phpFiles(BESTANDIO_ROOT . '/app'), $phpFiles(BESTANDIO_ROOT . '/migrations')) as $file) {
        $assert(stripos((string) file_get_contents($file), 'uniqid') === false, 'uniqid in ' . basename($file));
    }
    $assert(DeviceCode::normalize(' 7kx2-m9qp ta ') === '7KX2M9QPTA');
    $assert(!DeviceCode::valid('7KX2M9QPT0') && !DeviceCode::valid('7KX2M9QPT') && DeviceCode::valid('7KX2M9QPTA'));
});

$test('QR encoder: structure, version, error correction Q, SVG', function () use ($assert) {
    $qr = Qr::encode('7KX2M9QPTA', Qr::ECC_Q);
    $assert($qr->version() === 1 && $qr->size() === 21, 'version 1 for a device code');
    // Finder patterns in three corners: dark ring, light ring, dark 3x3 centre.
    foreach ([[0, 0], [14, 0], [0, 14]] as [$ox, $oy]) {
        $assert($qr->dark($ox, $oy) && $qr->dark($ox + 6, $oy + 6) && !$qr->dark($ox + 1, $oy + 1) && $qr->dark($ox + 3, $oy + 3));
    }
    $assert($qr->dark(8, $qr->size() - 8), 'dark module');
    // Timing pattern alternates.
    for ($i = 8; $i < 13; $i++) {
        $assert($qr->dark($i, 6) === ($i % 2 === 0));
    }
    // Format bits (copy 1) decode to level Q (bits 11).
    $bits = 0;
    $pos = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];
    foreach ($pos as $i => [$x, $y]) {
        $bits |= ($qr->dark($x, $y) ? 1 : 0) << $i;
    }
    $assert((($bits ^ 0x5412) >> 13) === 3, 'ECC level Q in format bits');
    $svg = $qr->svg();
    $assert(str_contains($svg, 'viewBox="0 0 29 29"') && str_contains($svg, '<path d="M'));
    // Longer byte-mode content selects higher versions.
    $assert(Qr::encode(str_repeat('x', 86), Qr::ECC_Q)->version() === 7 && Qr::encode(str_repeat('x', 87), Qr::ECC_Q)->version() === 8, 'byte capacity 7-Q = 86');
    $assert(Qr::encode('https://example.invalid/' . str_repeat('a', 300), Qr::ECC_L)->version() > 10);
});

$test('Session expires after 2 h idle and 12 h absolute', function () use ($assert) {
    $t0 = 1_800_000_000;
    $s = ['uid' => 1, 'created_at' => $t0, 'last_seen' => $t0];
    $assert(!Session::expired($s, $t0 + 7200));
    $assert(Session::expired($s, $t0 + 7201), 'idle limit');
    $s['last_seen'] = $t0 + 43000;
    $assert(!Session::expired($s, $t0 + 43200));
    $assert(Session::expired($s, $t0 + 43201), 'absolute limit');
    $assert(!Session::expired(['lang' => 'de'], $t0 + 999999), 'anonymous sessions never expire');
    // enforceLifetime with the clock moved forward.
    Clock::set($t0);
    $_SESSION = ['uid' => 7, 'created_at' => $t0, 'last_seen' => $t0, 'lang' => 'tr'];
    Clock::set($t0 + 3600);
    Session::enforceLifetime();
    $assert(($_SESSION['uid'] ?? null) === 7 && $_SESSION['last_seen'] === $t0 + 3600, 'activity extends');
    Clock::set($t0 + 3600 + 7201);
    @Session::enforceLifetime();
    $assert(!isset($_SESSION['uid']) && $_SESSION['lang'] === 'tr' && $_SESSION['flash'][0]['key'] === 'login.expired', 'signed out after idle');
    Clock::set(null);
    $_SESSION = [];
});

$test('Label templates: page sizes, dpi and QR module rounding to whole printer dots', function () use ($assert) {
    $b = LabelTemplate::BUILTIN;
    $assert(LabelTemplate::pageSize($b['single_54x101']) === [101.0, 54.0], 'landscape turns the page');
    $assert(LabelTemplate::pageSize($b['single_103x199']) === [103.0, 199.0]);
    $assert(LabelTemplate::pageSize($b['sheet_a4_3x8']) === [210.0, 297.0] && LabelTemplate::labelSize($b['sheet_a4_3x8']) === [70.0, 36.0]);
    $assert(LabelTemplate::pageSize(['orientation' => 'landscape'] + $b['sheet_a4_3x8']) === [297.0, 210.0]);
    $assert(LabelTemplate::perSheet($b['sheet_a4_3x8']) === 24 && LabelTemplate::perSheet($b['single_50x25']) === 1);
    foreach ($b as $key => $tpl) {
        [$w, $h] = LabelTemplate::labelSize($tpl);
        $l = Bestandio\Controllers\LabelController::layout($w, $h, $tpl['dpi']);
        $dotsPerModule = $l['qr'] / 29 * $tpl['dpi'] / 25.4;
        $assert(abs($dotsPerModule - round($dotsPerModule)) < 1e-9 && $dotsPerModule >= 1, "whole dots for $key: $dotsPerModule");
        $assert($l['qr'] <= min($w, $h), "QR fits on $key");
        $assert($l['vertical'] === ($h > $w * 1.15), "orientation $key");
    }
    $assert(abs(Bestandio\Controllers\LabelController::layout(101.0, 54.0, 300)['qr'] - 46.651) < 0.001, 'QR on 54x101 landscape');
});

$test('Label templates: validation, fit on the sheet, built-in names', function () use ($assert) {
    $in = static fn (array $t): array => array_map(static fn ($v) => is_float($v) || is_int($v) ? (string) $v : $v, $t);
    [$v, $err] = LabelTemplate::validate(['name' => 'Bogen'] + $in(LabelTemplate::BUILTIN['sheet_a4_3x8']));
    $assert($err === [], 'built-in sheet is valid: ' . json_encode($err));
    $assert($v['sheet_cols'] === 3 && $v['width_mm'] === 70.0 && $v['margin_top'] === 4.5);
    [, $err] = LabelTemplate::validate(['name' => 'x', 'width_mm' => '71'] + $in(LabelTemplate::BUILTIN['sheet_a4_3x8']));
    $assert(($err['width_mm'] ?? '') === 'tpl.error.too_wide', json_encode($err));
    [, $err] = LabelTemplate::validate(['name' => 'x', 'sheet_rows' => '9'] + $in(LabelTemplate::BUILTIN['sheet_a4_3x8']));
    $assert(($err['height_mm'] ?? '') === 'tpl.error.too_high', json_encode($err));
    [, $err] = LabelTemplate::validate(['name' => 'x', 'orientation' => 'landscape', 'sheet_cols' => '4', 'width_mm' => '74.25', 'height_mm' => '25'] + $in(LabelTemplate::BUILTIN['sheet_a4_3x8']));
    $assert($err === [], 'landscape A4 is 297 mm wide: ' . json_encode($err));
    [$v, $err] = LabelTemplate::validate(['name' => 'Rolle', 'kind' => 'single', 'width_mm' => '62,5', 'height_mm' => '29', 'dpi' => '203', 'orientation' => 'portrait', 'sheet_cols' => '99']);
    $assert($err === [] && $v['width_mm'] === 62.5 && $v['sheet_cols'] === null, 'single: comma decimal, sheet fields ignored');
    [, $err] = LabelTemplate::validate(['name' => '', 'kind' => 'other', 'width_mm' => '5', 'height_mm' => 'x', 'dpi' => '50']);
    $assert(isset($err['name'], $err['kind'], $err['width_mm'], $err['height_mm'], $err['dpi']), json_encode($err));
    $assert(LabelTemplate::displayName(['builtin_key' => 'sheet_a4_3x8'] + LabelTemplate::BUILTIN['sheet_a4_3x8']) === 'A4 3 × 8 · 70 × 36 mm');
    $assert(str_starts_with(LabelTemplate::displayName(['builtin_key' => 'single_54x101'] + LabelTemplate::BUILTIN['single_54x101']), '54 × 101 mm, '));
    $assert(LabelTemplate::displayName(['builtin_key' => null, 'name' => '<b>Eigen</b>']) === '<b>Eigen</b>');
    $assert(LabelTemplate::deleteBlocker(['builtin_key' => 'single_50x25', 'is_default' => false]) === 'tpl.error.builtin');
    $assert(LabelTemplate::deleteBlocker(['builtin_key' => null, 'is_default' => true]) === 'tpl.error.default');
    $assert(LabelTemplate::deleteBlocker(['builtin_key' => null, 'is_default' => false]) === null);
});

$test('CSV separator follows the UI language', function () use ($assert) {
    $assert(Csv::separatorFor('de') === ';' && Csv::separatorFor('tr') === ';' && Csv::separatorFor('en') === ',');
    $assert(Csv::line(['a', 'b'], ',') === "\"a\",\"b\"\r\n");
});

$test('Settings: company name only, length checked', function () use ($assert) {
    $assert(array_keys(Settings::FIELDS) === ['company_name']);
    [$v, $err] = Settings::validate(['company_name' => '  ARFEP  ', 'sheet_cols' => '3']);
    $assert($err === [] && $v === ['company_name' => 'ARFEP']);
    [, $err] = Settings::validate(['company_name' => str_repeat('x', 101)]);
    $assert(isset($err['company_name']));
});

$validScan = [
    'format' => 'bestandio-scan', 'version' => 1, 'scanned_at' => '2026-11-10T19:30:00Z',
    'hardware' => ['manufacturer' => 'LENOVO', 'model' => '21MC0012GE', 'serial_number' => 'PF4ABC12', 'cpu' => 'Intel Core Ultra 7',
        'ram_mb' => 32768, 'disks' => [['type' => 'nvme', 'size_gb' => 1024], ['type' => 'HDD', 'size_gb' => 2000.5]]],
    'os' => ['name' => 'Windows 11 Pro', 'version' => '10.0.26100'],
    'software' => [['name' => '7-Zip', 'version' => '24.08', 'vendor' => 'Igor Pavlov'], ['name' => 'Firefox']],
];

$test('Import: valid data, unknown fields ignored, no user or host name kept', function () use ($assert, $validScan) {
    $in = $validScan;
    $in['hostname'] = 'PC-BUCHHALTUNG';
    $in['hardware']['username'] = 'mmuster';
    $in['software'][0]['install_path'] = 'C:\\x';
    [$d, $err, $ignored] = ScanImport::parse(json_encode($in));
    $assert($err === [] && $d !== null, json_encode($err));
    $assert($ignored === 3, "ignored $ignored");
    $assert($d['manufacturer'] === 'LENOVO' && $d['serial_no'] === 'PF4ABC12' && $d['ram_mb'] === 32768);
    $assert($d['storage'] === 'NVMe 1024 GB, HDD 2000.5 GB', $d['storage']);
    $assert(count($d['software']) === 2 && $d['software'][1] === ['name' => 'Firefox', 'version' => '', 'vendor' => '']);
    $flat = json_encode($d);
    $assert(!str_contains($flat, 'PC-BUCHHALTUNG') && !str_contains($flat, 'mmuster'), 'personal fields dropped');
    // Empty serial number (firmware placeholder) is accepted but never matched.
    [$d3, $err3] = ScanImport::parse(json_encode(array_replace_recursive($validScan, ['hardware' => ['serial_number' => '']])));
    $assert($err3 === [] && $d3['serial_no'] === '', 'empty serial accepted');
    // BOM tolerated.
    [$d2] = ScanImport::parse("\xEF\xBB\xBF" . json_encode($validScan));
    $assert($d2 !== null);
});

$test('Import: invalid input is rejected with the expected error', function () use ($assert, $validScan) {
    $cases = [
        'invalid JSON'   => ['{"format": "bestandio-scan",', 'import.error.json'],
        'wrong version'  => [json_encode(['version' => 2] + $validScan), 'import.error.version'],
        'version string' => [json_encode(['version' => '1'] + $validScan), 'import.error.version'],
        'wrong format'   => [json_encode(['format' => 'other'] + $validScan), 'import.error.format'],
        'too large'      => [str_repeat(' ', ScanImport::MAX_BYTES + 1), 'import.error.too_large'],
        'empty'          => ['  ', 'import.error.empty'],
        'no serial key'  => [json_encode(['hardware' => array_diff_key($validScan['hardware'], ['serial_number' => 1])] + $validScan), 'import.error.required'],
        'serial number'  => [json_encode(array_replace_recursive($validScan, ['hardware' => ['serial_number' => 123]])), 'import.error.required'],
        'ram as string'  => [json_encode(array_replace_recursive($validScan, ['hardware' => ['ram_mb' => '16 GB']])), 'import.error.invalid'],
        'bad disk type'  => [json_encode(array_replace_recursive($validScan, ['hardware' => ['disks' => [['type' => 'floppy', 'size_gb' => 1]]]])), 'import.error.invalid'],
        'future date'    => [json_encode(['scanned_at' => '2099-01-01T00:00:00Z'] + $validScan), 'import.error.date'],
        'date w/o zone'  => [json_encode(['scanned_at' => '2026-11-10T19:30:00'] + $validScan), 'import.error.date'],
        'too long'       => [json_encode(array_replace_recursive($validScan, ['hardware' => ['model' => str_repeat('m', 101)]])), 'import.error.too_long'],
        'too deep'       => [str_repeat('[', 20) . str_repeat(']', 20), 'import.error.json'],
        'root array'     => ['[1,2]', 'import.error.structure'],
    ];
    foreach ($cases as $name => [$json, $key]) {
        [$d, $err] = ScanImport::parse($json);
        $assert($d === null && in_array($key, array_column($err, 0), true), "$name: expected $key, got " . json_encode($err));
    }
});

$test('Import: HTML/script in fields is stored as text and escaped on output', function () use ($assert, $validScan) {
    $in = array_replace_recursive($validScan, ['hardware' => ['model' => '<script>alert(1)</script>'], 'software' => [['name' => '<img src=x onerror=alert(1)>']]]);
    [$d, $err] = ScanImport::parse(json_encode($in));
    $assert($err === [] && $d['model'] === '<script>alert(1)</script>');
    $assert(e($d['model']) === '&lt;script&gt;alert(1)&lt;/script&gt;' && !str_contains(e($d['software'][0]['name']), '<img'));
});

// ------------------------------------------------------------------ DB tests

$dbEnv = [
    'host' => getenv('BESTANDIO_TEST_DB_HOST'),
    'port' => getenv('BESTANDIO_TEST_DB_PORT') ?: 3306,
    'name' => getenv('BESTANDIO_TEST_DB_NAME'),
    'user' => getenv('BESTANDIO_TEST_DB_USER'),
    'pass' => getenv('BESTANDIO_TEST_DB_PASS') ?: '',
];
$withDb = $dbEnv['host'] && $dbEnv['name'] && $dbEnv['user'];

// The DB tests drop every table: refuse anything that is not clearly a test database.
if ($withDb && !str_contains(strtolower((string) $dbEnv['name']), 'test')) {
    fwrite(STDERR, "Refusing to run DB tests: database name must contain 'test'.
");
    exit(2);
}

$dbTest('DB: migrations from scratch', function () use ($assert, $dbEnv) {
    $pdo = Db::connect($dbEnv);
    $assert(Db::versionSupported($pdo), 'db version');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    Db::set($pdo);
    Config::set(['db' => $dbEnv, 'app' => []]);
    // First only 001 with existing devices, then the rest: the devices must receive a code.
    $tmp = sys_get_temp_dir() . '/bestandio-mig-' . bin2hex(random_bytes(4));
    mkdir($tmp);
    copy(BESTANDIO_ROOT . '/migrations/001_init.sql', $tmp . '/001_init.sql');
    $assert((new Migrator($pdo, $tmp))->migrate() === ['001']);
    unlink($tmp . '/001_init.sql');
    rmdir($tmp);
    $pdo->exec("INSERT INTO categories (name) VALUES ('Alt')");
    $pdo->exec("INSERT INTO locations (name) VALUES ('Alt')");
    $pdo->exec("INSERT INTO devices (inventory_no, category_id, location_id) VALUES ('OLD-1', 1, 1), ('OLD-2', 1, 1)");
    $m = new Migrator($pdo);
    $assert($m->migrate() === ['002', '003', '004', '005', '006'], 'remaining migrations');
    $assert($m->migrate() === [] && $m->pending() === []);
    $assert(count($pdo->query('SHOW TABLES')->fetchAll()) === 10);
    $codes = $pdo->query('SELECT code FROM devices')->fetchAll(PDO::FETCH_COLUMN);
    $assert(count($codes) === 2 && DeviceCode::valid($codes[0]) && DeviceCode::valid($codes[1]) && $codes[0] !== $codes[1], 'codes for existing devices');
    $pdo->exec('DELETE FROM devices');
    $pdo->exec('DELETE FROM categories');
    $pdo->exec('DELETE FROM locations');
    $pdo->exec('ALTER TABLE categories AUTO_INCREMENT = 1');
    $pdo->exec('ALTER TABLE locations AUTO_INCREMENT = 1');
});

$dbTest('DB: users and login throttle', function () use ($assert) {
    $id = User::create('admin', 'correct horse battery', 'admin');
    $u = User::findByUsername('ADMIN');
    $assert($u !== null && (int) $u['id'] === $id && password_verify('correct horse battery', $u['password_hash']));
    $assert(User::activeAdmins() === 1);
    $assert(!LoginThrottle::blocked('admin', '10.0.0.1'));
    for ($i = 0; $i < LoginThrottle::MAX_PER_USER; $i++) {
        LoginThrottle::fail('admin', '10.0.0.1');
    }
    $assert(LoginThrottle::blocked('Admin', '10.0.0.2'), 'user block');
    LoginThrottle::clear('admin');
    $assert(!LoginThrottle::blocked('admin', '10.0.0.3'));
});

$dbTest('DB: devices, history, duplicates, search', function () use ($assert) {
    $cats = new NamedList('categories');
    $locs = new NamedList('locations');
    $c1 = $cats->create('Laptop');
    $c2 = $cats->create('Drucker');
    $l1 = $locs->create('Büro 1');
    $uid = (int) User::findByUsername('admin')['id'];

    $d = Device::fromInput(['inventory_no' => 'INV-001', 'category_id' => (string) $c1, 'location_id' => (string) $l1,
        'status' => 'in_use', 'manufacturer' => 'Lenovo', 'model' => 'T14', 'serial_no' => 'SN1', 'purchase_date' => '2026-01-15']);
    $assert(Device::validate($d) === [], 'valid device');
    $id = Device::create($d, $uid);
    $assert(count(History::forDevice($id)) === 1);

    $dup = Device::fromInput(['inventory_no' => 'INV-001', 'category_id' => (string) $c1, 'location_id' => (string) $l1, 'status' => 'stock', 'serial_no' => 'SN1']);
    $assert((Device::validate($dup)['inventory_no'] ?? '') === 'device.error.inventory_taken');
    $assert(Device::serialDuplicates('SN1') === ['INV-001'] && Device::serialDuplicates('SN1', $id) === []);

    $bad = Device::fromInput(['inventory_no' => '', 'category_id' => '999', 'location_id' => '0', 'status' => 'x', 'purchase_date' => '2026-13-01']);
    $assert(array_keys(Device::validate($bad)) === ['inventory_no', 'category_id', 'location_id', 'status', 'purchase_date']);

    $d2 = $d;
    $d2['category_id'] = $c2;
    $d2['status'] = 'repair';
    $d2['note'] = 'Display';
    $assert(Device::update($id, $d2, $uid) === 3);
    $h = History::forDevice($id);
    $fields = array_column($h, 'field');
    sort($fields);
    $assert($fields === ['_created', 'category', 'note', 'status'], implode(',', $fields));
    $catRow = array_values(array_filter($h, fn ($r) => $r['field'] === 'category'))[0];
    $assert($catRow['old_value'] === 'Laptop' && $catRow['new_value'] === 'Drucker' && $catRow['username'] === 'admin');
    $assert(Device::update($id, $d2, $uid) === 0, 'no-op update');

    Device::create(Device::fromInput(['inventory_no' => 'INV-002', 'category_id' => (string) $c1, 'location_id' => (string) $l1, 'status' => 'stock', 'note' => '100%']), $uid);
    $assert(Device::search(['q' => 'lenovo'], 'inventory_no', 'asc', 1, 25)['total'] === 1);
    $assert(Device::search(['q' => '%'], 'inventory_no', 'asc', 1, 25)['total'] === 1, 'LIKE wildcard escaped');
    $assert(Device::search(['status' => 'stock'], 'inventory_no', 'asc', 1, 25)['total'] === 1);
    $assert(Device::search(['category' => $c1], 'inventory_no', 'asc', 1, 25)['total'] === 1);
    $all = Device::search([], 'inventory_no', 'desc', 1, 1);
    $assert($all['total'] === 2 && count($all['rows']) === 1 && $all['rows'][0]['inventory_no'] === 'INV-002');
    $assert(count(iterator_to_array(Device::each([], 'bogus', 'x'))) === 2);

    $assert(!$cats->delete($c1), 'in-use category must not be deletable');
    $c3 = $cats->create('Leer');
    $assert($cats->delete($c3) && $cats->nameExists('Laptop') && !$cats->nameExists('Leer'));
});

$dbTest('DB: device code is set on create, unique and unchangeable', function () use ($assert) {
    $dev = Db::one('SELECT * FROM devices ORDER BY id LIMIT 1');
    $assert(DeviceCode::valid($dev['code']));
    $assert(Device::findByCode($dev['code'])['id'] === $dev['id'] && Device::findByCode('ZZZZZZZZZZ') === null);
    $assert(Device::update((int) $dev['id'], ['code' => 'AAAAAAAAAA', 'note' => 'x'], null) === 1);
    $assert(Device::find((int) $dev['id'])['code'] === $dev['code'], 'code must not change');
    $codes = Db::all('SELECT code FROM devices');
    $assert(count(array_unique(array_column($codes, 'code'))) === count($codes));
    $assert(Device::search(['q' => strtolower($dev['code'])], 'inventory_no', 'asc', 1, 25)['total'] === 1, 'search by code');
});

$dbTest('DB: import preview stores nothing, confirm creates/updates with history', function () use ($assert, $validScan) {
    $uid = (int) User::findByUsername('admin')['id'];
    $before = (int) Db::value('SELECT COUNT(*) FROM devices') . '/' . Db::value('SELECT COUNT(*) FROM device_history');
    [$data] = ScanImport::parse(json_encode($validScan));
    $assert(Device::findByManufacturerSerial('lenovo ', ' pf4abc12') === [], 'no match yet');
    $after = (int) Db::value('SELECT COUNT(*) FROM devices') . '/' . Db::value('SELECT COUNT(*) FROM device_history');
    $assert($before === $after, 'parse must not store anything');

    $base = Device::fromInput(['inventory_no' => 'IMP-1', 'category_id' => '1', 'location_id' => '1', 'status' => 'in_use']);
    $id = ScanImport::applyCreate($base, $data, $uid);
    $dev = Device::find($id);
    $assert($dev['manufacturer'] === 'LENOVO' && $dev['cpu'] === 'Intel Core Ultra 7' && (int) $dev['ram_mb'] === 32768 && $dev['os_name'] === 'Windows 11 Pro');
    $assert(count(Software::forDevice($id)) === 2);
    $fields = array_column(History::forDevice($id), 'field');
    sort($fields);
    $assert($fields === ['_created', '_import', 'software'], implode(',', $fields));

    $matches = Device::findByManufacturerSerial('lenovo', 'PF4ABC12');
    $assert(count($matches) === 1 && (int) $matches[0]['id'] === $id, 'match case-insensitive');
    $upd = array_replace_recursive($validScan, ['scanned_at' => '2026-11-10T20:30:00Z', 'hardware' => ['ram_mb' => 65536], 'os' => ['version' => '10.0.26200']]);
    $upd['software'] = [['name' => 'Firefox', 'version' => '140']];
    [$data2] = ScanImport::parse(json_encode($upd));
    $diff = ScanImport::diff($matches[0], $data2);
    $assert(array_keys($diff) === ['ram_mb', 'os_version', 'scanned_at', 'software'], implode(',', array_keys($diff)));
    $assert($diff['ram_mb'] === ['32768', '65536'] && $diff['software'] === ['2', '1']);
    ScanImport::applyUpdate($id, $data2, $uid);
    $dev = Device::find($id);
    $assert((int) $dev['ram_mb'] === 65536 && $dev['os_version'] === '10.0.26200' && $dev['inventory_no'] === 'IMP-1');
    $h = History::forDevice($id);
    $assert(count(array_filter($h, fn ($r) => $r['field'] === '_import')) === 2 && count(array_filter($h, fn ($r) => $r['field'] === 'ram_mb')) === 1);
    $assert(count(Software::forDevice($id)) === 1);
});

$dbTest('DB: matching order serial → UUID; same scan without serial twice gives one device', function () use ($assert) {
    $uid = (int) User::findByUsername('admin')['id'];
    $scan = ['format' => 'bestandio-scan', 'version' => 1, 'scanned_at' => '2026-11-10T20:00:00Z',
        'hardware' => ['manufacturer' => 'System manufacturer', 'serial_number' => '', 'uuid' => '34F241AC-21F5-6F54-50CF-30C599831023', 'ram_mb' => 16384]];
    [$d] = ScanImport::parse(json_encode($scan));
    $assert(ScanImport::matches($d) === [], 'first time: no match');
    $before = (int) Db::value('SELECT COUNT(*) FROM devices');
    $id = ScanImport::applyCreate(Device::fromInput(['inventory_no' => 'UUID-1', 'category_id' => '1', 'location_id' => '1', 'status' => 'in_use']), $d, $uid);
    [$d2] = ScanImport::parse(json_encode(array_replace_recursive($scan, ['hardware' => ['ram_mb' => 32768]])));
    $m = ScanImport::matches($d2);
    $assert(count($m) === 1 && (int) $m[0]['id'] === $id, 'second time: matched by UUID');
    ScanImport::applyUpdate($id, $d2, $uid);
    $assert((int) Db::value('SELECT COUNT(*) FROM devices') === $before + 1, 'exactly one device');
    $assert((int) Device::find($id)['ram_mb'] === 32768 && Device::find($id)['uuid'] === '34F241AC-21F5-6F54-50CF-30C599831023');
    // Serial number wins over UUID; a clone with the same UUID gives several matches to choose from.
    $clone = ScanImport::applyCreate(Device::fromInput(['inventory_no' => 'UUID-2', 'category_id' => '1', 'location_id' => '1', 'status' => 'stock']), $d, $uid);
    $assert(count(ScanImport::matches($d)) === 2, 'clones: user chooses');
    [$d3] = ScanImport::parse(json_encode(array_replace_recursive($scan, ['hardware' => ['manufacturer' => 'LENOVO', 'serial_number' => 'PF4ABC12']])));
    $bySerial = ScanImport::matches($d3);
    $assert(count($bySerial) === 1 && $bySerial[0]['serial_no'] === 'PF4ABC12', 'serial before uuid');
    $assert($clone > 0);
});

$dbTest('DB: label templates – migration keeps the old settings, one default per kind, built-in fixed', function () use ($assert) {
    $pdo = Db::pdo();
    // Run migration 006 again on a given state: old settings rows, with or without devices.
    $rerun = static function (array $settings, bool $devices) use ($pdo): array {
        $pdo->exec('DROP TABLE IF EXISTS label_templates');
        $pdo->exec("DELETE FROM schema_migrations WHERE version = '006'");
        $pdo->exec('DELETE FROM settings');
        $pdo->exec('DELETE FROM devices');
        if ($devices) {
            $pdo->exec("INSERT INTO categories (name) VALUES ('Mig') ON DUPLICATE KEY UPDATE name = name");
            $pdo->exec("INSERT INTO locations (name) VALUES ('Mig') ON DUPLICATE KEY UPDATE name = name");
            $pdo->exec("INSERT INTO devices (code, inventory_no, category_id, location_id) SELECT 'MIGR234567', 'MIG-1', MIN(c.id), MIN(l.id) FROM categories c, locations l");
        }
        $ins = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?)');
        foreach ($settings as $k => $v) {
            $ins->execute([$k, $v]);
        }
        (new Migrator($pdo))->migrate();
        $pdo->exec('DELETE FROM devices');
        return [LabelTemplate::all(), LabelTemplate::defaultFor('sheet'), LabelTemplate::defaultFor('single')];
    };

    // New installation: only the built-in templates.
    [$all, $sheet, $single] = $rerun([], false);
    $assert(count($all) === 6 && $sheet['builtin_key'] === 'sheet_a4_3x8' && $single['builtin_key'] === 'single_57x32', 'fresh');

    // Saved own layout: becomes own templates and stays the default.
    $old = ['company_name' => 'X', 'sheet_cols' => '2', 'sheet_rows' => '7', 'sheet_margin_top' => '10', 'sheet_margin_bottom' => '10',
        'sheet_margin_left' => '5', 'sheet_margin_right' => '5', 'sheet_gap_x' => '2', 'sheet_gap_y' => '3',
        'label_template' => 'custom', 'single_width' => '62', 'single_height' => '29', 'dpi' => '300'];
    [$all, $sheet, $single] = $rerun($old, true);
    $assert(count($all) === 8, 'two own templates');
    $assert($sheet['builtin_key'] === null && $sheet['sheet_cols'] === 2 && $sheet['sheet_rows'] === 7 && $sheet['width_mm'] === 99.0
        && $sheet['height_mm'] === 37.0 && $sheet['margin_left'] === 5.0 && $sheet['gap_y'] === 3.0 && $sheet['dpi'] === 300, json_encode($sheet));
    $assert($single['builtin_key'] === null && $single['width_mm'] === 62.0 && $single['height_mm'] === 29.0 && $single['dpi'] === 300, json_encode($single));
    [, $err] = LabelTemplate::validate(array_map(static fn ($v) => (string) $v, array_filter($sheet, static fn ($v) => !is_bool($v) && $v !== null)));
    $assert($err === [], 'migrated sheet passes validation: ' . json_encode($err));
    $assert(Settings::all() === ['company_name' => 'X'], 'company name untouched');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 13, 'old settings rows kept');

    // Old fixed template: maps to the built-in one; a changed own size is kept as an extra template.
    [$all, , $single] = $rerun(['label_template' => 'dymo_54x101', 'single_width' => '80', 'single_height' => '40', 'dpi' => '203'], true);
    $assert($single['builtin_key'] === 'single_54x101', 'dymo -> built-in 54x101');
    $assert(count(array_filter($all, static fn ($t) => $t['builtin_key'] === null && $t['width_mm'] === 80.0 && !$t['is_default'])) === 1, 'own 80x40 kept');

    // Default: one per kind; the database refuses a second one.
    $own = LabelTemplate::create(['name' => 'Eigen', 'kind' => 'single', 'width_mm' => 40.0, 'height_mm' => 20.0, 'dpi' => 300, 'orientation' => 'portrait']);
    LabelTemplate::setDefault(LabelTemplate::find($own));
    $assert(LabelTemplate::defaultFor('single')['id'] === $own);
    $assert((int) $pdo->query("SELECT COUNT(*) FROM label_templates WHERE kind = 'single' AND is_default = 1")->fetchColumn() === 1);
    $refused = false;
    try {
        $pdo->exec("UPDATE label_templates SET is_default = 1 WHERE kind = 'single'");
    } catch (PDOException $e) {
        $refused = true;
    }
    $assert($refused, 'unique default per kind');
    // Delete: default and built-in are kept; update never touches a built-in.
    LabelTemplate::delete($own);
    $builtin = LabelTemplate::defaultFor('sheet');
    $builtinId = (int) $pdo->query("SELECT id FROM label_templates WHERE builtin_key = 'single_50x25'")->fetchColumn();
    LabelTemplate::delete($builtinId);
    LabelTemplate::update($builtinId, ['name' => 'Hack', 'width_mm' => 99.0, 'height_mm' => 99.0, 'dpi' => 100, 'orientation' => 'portrait']);
    $b = LabelTemplate::find($builtinId);
    $assert(LabelTemplate::find($own) !== null && $b !== null && $b['width_mm'] === 50.0 && $b['name'] === '', 'default and built-in kept');
    LabelTemplate::setDefault($b);
    LabelTemplate::delete($own);
    $assert(LabelTemplate::find($own) === null && $builtin !== null, 'own non-default deleted');
    $rerun([], false);
});

$dbTest('DB: settings saved and read back', function () use ($assert) {
    [$v] = Settings::validate(['company_name' => 'ARFEP <IT>']);
    Settings::save($v);
    $assert(Settings::all() === ['company_name' => 'ARFEP <IT>']);
});

// ------------------------------------------------------------------- run

$skipped = [];
if ($withDb) {
    $tests = array_merge($tests, $dbTests);
} else {
    $skipped = $dbTests;
}
$passed = 0;
$failed = 0;
foreach ($tests as [$name, $fn]) {
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "  ok    $name\n");
    } catch (Throwable $ex) {
        $failed++;
        fwrite(STDOUT, "  FAIL  $name: " . $ex->getMessage() . "\n");
    }
}
foreach ($skipped as [$name]) {
    fwrite(STDOUT, "  SKIP  $name\n");
}
$total = count($tests) + count($skipped);
fwrite(STDOUT, sprintf(
    "\n%d/%d passed, %d failed, %d skipped%s\n",
    $passed,
    $total,
    $failed,
    count($skipped),
    $skipped === [] ? '' : ' (DB tests need BESTANDIO_TEST_DB_HOST/_PORT/_NAME/_USER/_PASS)'
));
exit($failed === 0 ? 0 : 1);
