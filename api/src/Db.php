<?php
declare(strict_types=1);

namespace App;

final class Db
{
    private static ?\PDO $pdo = null;

    /** Tests inject an in-memory SQLite connection. */
    public static function set(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '3306'),
                Config::require('DB_NAME'),
            );
            self::$pdo = new \PDO($dsn, Config::require('DB_USER'), Config::get('DB_PASS', ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    /** @return array<int,array> */
    public static function all(string $sql, array $args = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function exec(string $sql, array $args = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $args = []): int
    {
        self::exec($sql, $args);
        return (int) self::pdo()->lastInsertId();
    }
}
