<?php
namespace App\Models;

use App\Core\Model;

class Cliente extends Model
{
    public static function buscar(int $id): ?array
    {
        return self::uno('SELECT * FROM cliente WHERE id = ?', [$id]);
    }

    public static function listar(string $busqueda, int $pagina, int $porPagina = 25): array
    {
        $params = [];
        $where = 'WHERE 1=1';
        if ($busqueda !== '') {
            $where .= ' AND (razon_social ILIKE :q OR ruc ILIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }
        $total = (int) self::valor("SELECT COUNT(*) FROM cliente $where", $params);
        $filas = self::todos(
            "SELECT * FROM cliente $where ORDER BY razon_social LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina),
            $params
        );
        return ['filas' => $filas, 'total' => $total];
    }

    /** Búsqueda rápida para la pantalla de facturación. */
    public static function autocompletar(string $q): array
    {
        return self::todos(
            'SELECT id, ruc, razon_social, direccion, telefono FROM cliente
             WHERE activo AND (razon_social ILIKE :q OR ruc ILIKE :q)
             ORDER BY (ruc = :exacto) DESC, razon_social LIMIT ' . ($q === '' ? 60 : 15),
            ['q' => '%' . $q . '%', 'exacto' => $q]
        );
    }

    public static function existeRuc(string $ruc, ?int $exceptoId = null): bool
    {
        return (bool) self::valor('SELECT 1 FROM cliente WHERE ruc = ? AND id <> ?', [$ruc, $exceptoId ?? 0]);
    }

    public static function guardar(array $d, ?int $id = null): int
    {
        $params = [
            'ruc'          => $d['ruc'],
            'razon_social' => $d['razon_social'],
            'direccion'    => $d['direccion'] ?: null,
            'telefono'     => $d['telefono'] ?: null,
            'celular'      => $d['celular'] ?: null,
            'email'        => $d['email'] ?: null,
            'activo'       => $d['activo'] ? 'true' : 'false',
        ];
        if ($id === null) {
            return (int) self::valor(
                'INSERT INTO cliente (ruc, razon_social, direccion, telefono, celular, email, activo)
                 VALUES (:ruc, :razon_social, :direccion, :telefono, :celular, :email, :activo) RETURNING id',
                $params
            );
        }
        $params['id'] = $id;
        self::ejecutar(
            'UPDATE cliente SET ruc = :ruc, razon_social = :razon_social, direccion = :direccion, telefono = :telefono,
             celular = :celular, email = :email, activo = :activo WHERE id = :id',
            $params
        );
        return $id;
    }

    public static function contar(): int
    {
        return (int) self::valor('SELECT COUNT(*) FROM cliente WHERE activo');
    }
}
