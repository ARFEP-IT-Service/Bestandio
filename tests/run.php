<?php

declare(strict_types=1);

// Own test runner, no external packages: php tests/run.php
// DB tests run only when BESTANDIO_TEST_DB_* environment variables are set.
// WARNING: DB tests drop all tables in that database.

require dirname(__DIR__) . '/app/bootstrap.php';

use Bestandio\Core\Config;
use Bestandio\Core\Csrf;
use Bestandio\Core\Csv;
use Bestandio\Core\Db;
use Bestandio\Core\HttpException;
use Bestandio\Core\I18n;
use Bestandio\Core\Migrator;
use Bestandio\Core\Router;
use Bestandio\Models\Device;
use Bestandio\Models\History;
use Bestandio\Models\LoginThrottle;
use Bestandio\Models\NamedList;
use Bestandio\Models\User;

$tests = [];
$test = static function (string $name, callable $fn) use (&$tests): void {
    $tests[] = [$name, $fn];
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
        preg_match_all("/(?:\\bt\\(|flash\\('\\w+', |error\\(\\d+, |HttpException\\(\\d+, )'([a-z_]+\\.[a-z0-9_.]+)'/", (string) file_get_contents($file), $m);
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
});

$test('Validation helpers', function () use ($assert) {
    $assert(Device::validDate('2026-02-28') && !Device::validDate('2026-02-30') && !Device::validDate('28.02.2026'));
    $assert(User::validUsername('admin') && User::validUsername('a.b-c_d') && !User::validUsername('ab') && !User::validUsername('a b'));
    $assert(User::passwordErrors('short', 'short') === ['user.error.password_short']);
    $assert(User::passwordErrors('longenough1', 'longenough2') === ['user.error.password_mismatch']);
    $assert(User::passwordErrors('longenough1', 'longenough1') === []);
});

$test('Request input: invalid UTF-8 and arrays are rejected', function () use ($assert) {
    $assert(Bestandio\Core\Request::clean(" Büro ") === 'Büro');
    $assert(Bestandio\Core\Request::clean("B\xFCro") === null && Bestandio\Core\Request::clean(['x']) === null);
    $assert(Device::fromInput(['inventory_no' => "A\xFC"])['inventory_no'] === '');
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
        $assert(!preg_match('/\becho\b|\bprint\b/', $src), "echo/print in $name");
        $assert(!preg_match('/\sstyle\s*=|<style\b|\son[a-z]+\s*=/i', $src), "inline style/handler in $name");
        $assert(!preg_match('/<script(?![^>]*\ssrc=)/i', $src), "inline script in $name");
        $assert(!preg_match('#(?:https?:)?//[a-z0-9-]+\.[a-z]#i', $src), "external URL in $name");
    }
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

if ($withDb) {
    $test('DB: migrations from scratch', function () use ($assert, $dbEnv) {
        $pdo = Db::connect($dbEnv);
        $assert(Db::versionSupported($pdo), 'db version');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Db::set($pdo);
        Config::set(['db' => $dbEnv, 'app' => []]);
        $m = new Migrator($pdo);
        $assert($m->migrate() === ['001']);
        $assert($m->migrate() === [] && $m->pending() === []);
        $assert(count($pdo->query('SHOW TABLES')->fetchAll()) === 7);
    });

    $test('DB: users and login throttle', function () use ($assert) {
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

    $test('DB: devices, history, duplicates, search', function () use ($assert) {
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
}

// ------------------------------------------------------------------- run

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
$total = count($tests);
fwrite(STDOUT, sprintf("\n%d/%d passed%s\n", $passed, $total, $withDb ? '' : ' (DB tests skipped: BESTANDIO_TEST_DB_* not set)'));
exit($failed === 0 ? 0 : 1);
