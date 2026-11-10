<?php

declare(strict_types=1);

namespace Bestandio\Core;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    /** Open a connection from a config array (keys host, port, name, user, pass). */
    public static function connect(array $c): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'] ?? '127.0.0.1',
            (int) ($c['port'] ?? 3306),
            $c['name'] ?? ''
        );
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        // Keep DB timestamps in the same zone as PHP. date('P') yields e.g. "+01:00".
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        $pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(Config::get('db', []));
        }
        return self::$pdo;
    }

    public static function set(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Run $fn in a transaction; joins an already running one. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    /** Check that the server is MySQL >= 8.0 or MariaDB >= 10.6. */
    public static function versionSupported(PDO $pdo): bool
    {
        $v = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        if (!preg_match('/^(\d+)\.(\d+)/', $v, $m)) {
            return false;
        }
        $num = (int) $m[1] * 100 + (int) $m[2];
        return stripos($v, 'mariadb') !== false ? $num >= 1006 : $num >= 800;
    }
}
