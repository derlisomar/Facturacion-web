<?php
namespace App\Core;

use PDO;

abstract class Model
{
    protected static function db(): PDO
    {
        return Database::pdo();
    }

    protected static function todos(string $sql, array $params = []): array
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected static function uno(string $sql, array $params = []): ?array
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    protected static function valor(string $sql, array $params = [])
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected static function ejecutar(string $sql, array $params = []): int
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
