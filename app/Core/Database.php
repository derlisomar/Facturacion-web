<?php
namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                config('db.host'),
                (int) config('db.port', 5432),
                config('db.nombre')
            );
            if ($ssl = config('db.sslmode')) {
                $dsn .= ';sslmode=' . $ssl;   // en la nube suele ser 'require'
            }
            self::$pdo = new PDO($dsn, config('db.usuario'), config('db.clave'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET client_encoding TO 'UTF8'");
        }
        return self::$pdo;
    }
}
