<?php

declare(strict_types=1);

namespace Bestandio\Core;

use PDO;

/**
 * Applies migrations/NNN_name.sql or NNN_name.php in order, tracked in schema_migrations.
 * A PHP migration returns a callable that receives the PDO connection.
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir = BESTANDIO_ROOT . '/migrations')
    {
    }

    /** @return array<string,string> version => file path, sorted */
    public function available(): array
    {
        $out = [];
        foreach (glob($this->dir . '/*.{sql,php}', GLOB_BRACE) ?: [] as $file) {
            if (preg_match('/^(\d{3,})_[a-z0-9_]+\.(sql|php)$/', basename($file), $m)) {
                $out[$m[1]] = $file;
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->ensureTable();
        return $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function pending(): array
    {
        return array_values(array_diff(array_keys($this->available()), $this->applied()));
    }

    /** @return list<string> versions applied in this run */
    public function migrate(): array
    {
        $done = [];
        $files = $this->available();
        foreach ($this->pending() as $version) {
            if (str_ends_with($files[$version], '.php')) {
                $migration = require $files[$version];
                $migration($this->pdo);
            } else {
                foreach (self::split((string) file_get_contents($files[$version])) as $statement) {
                    $this->pdo->exec($statement);
                }
            }
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, name) VALUES (?, ?)');
            $stmt->execute([$version, basename($files[$version])]);
            $done[] = $version;
        }
        return $done;
    }

    /** Split an SQL file into statements: ';' at line end, '--' comment lines dropped. */
    public static function split(string $sql): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $lines[] = $line;
        }
        $parts = preg_split('/;\s*$/m', implode("\n", $lines));
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(16)  NOT NULL PRIMARY KEY,
                name       VARCHAR(190) NOT NULL,
                applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
