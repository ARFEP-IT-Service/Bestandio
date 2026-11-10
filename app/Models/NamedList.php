<?php

declare(strict_types=1);

namespace Bestandio\Models;

use Bestandio\Core\Db;
use InvalidArgumentException;
use PDOException;

/** Maintainable lists: categories and locations. */
final class NamedList
{
    public const KINDS = [
        'categories' => 'category_id',
        'locations'  => 'location_id',
    ];

    private string $table;
    private string $column;

    public function __construct(string $kind)
    {
        if (!isset(self::KINDS[$kind])) {
            throw new InvalidArgumentException('Unknown list.');
        }
        $this->table = $kind;
        $this->column = self::KINDS[$kind];
    }

    public function all(): array
    {
        return Db::all(
            "SELECT t.id, t.name, (SELECT COUNT(*) FROM devices d WHERE d.{$this->column} = t.id) AS used
             FROM {$this->table} t ORDER BY t.name"
        );
    }

    /** @return array<int,string> id => name */
    public function options(): array
    {
        $out = [];
        foreach (Db::all("SELECT id, name FROM {$this->table} ORDER BY name") as $row) {
            $out[(int) $row['id']] = $row['name'];
        }
        return $out;
    }

    public function find(int $id): ?array
    {
        return Db::one("SELECT id, name FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        return Db::value("SELECT id FROM {$this->table} WHERE name = ? AND id <> ?", [$name, $exceptId]) !== null;
    }

    public function create(string $name): int
    {
        Db::run("INSERT INTO {$this->table} (name) VALUES (?)", [$name]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        Db::run("UPDATE {$this->table} SET name = ? WHERE id = ?", [$name, $id]);
    }

    /** Delete if no device uses the entry. */
    public function delete(int $id): bool
    {
        if ((int) Db::value("SELECT COUNT(*) FROM devices WHERE {$this->column} = ?", [$id]) > 0) {
            return false;
        }
        try {
            Db::run("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        } catch (PDOException) {
            return false;
        }
        return true;
    }
}
