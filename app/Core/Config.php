<?php
namespace App\Core;

class Config
{
    private static array $valores = [];

    public static function cargar(array $valores): void
    {
        self::$valores = $valores;
    }

    /** Lee un valor con notación de puntos: Config::get('db.host') */
    public static function get(string $clave, $defecto = null)
    {
        $valor = self::$valores;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }
}
