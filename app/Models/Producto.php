<?php
namespace App\Models;

use App\Core\Model;

class Producto extends Model
{
    public const PRODUCTO = 'P';
    public const SERVICIO = 'S';

    public static function buscar(int $id, int $empresaId): ?array
    {
        return self::uno('SELECT * FROM producto WHERE id = ? AND empresa_id = ?', [$id, $empresaId]);
    }

    public static function listar(int $empresaId, string $busqueda, string $tipo, int $pagina, int $porPagina = 25): array
    {
        $params = ['emp' => $empresaId];
        $where = 'WHERE p.empresa_id = :emp';
        if ($busqueda !== '') {
            $where .= ' AND (p.descripcion ILIKE :q OR p.codigo ILIKE :q OR p.codigo_corto ILIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }
        if ($tipo === self::PRODUCTO || $tipo === self::SERVICIO) {
            $where .= ' AND p.tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        $total = (int) self::valor("SELECT COUNT(*) FROM producto p $where", $params);
        $filas = self::todos(
            "SELECT p.* FROM producto p
             $where ORDER BY p.descripcion LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina),
            $params
        );
        return ['filas' => $filas, 'total' => $total];
    }

    public static function autocompletar(int $empresaId, string $q): array
    {
        return self::todos(
            'SELECT id, codigo_corto, descripcion, tipo, precio_venta, iva, stock_actual FROM producto
             WHERE empresa_id = :emp AND activo AND (descripcion ILIKE :q OR codigo ILIKE :q OR codigo_corto ILIKE :q)
             ORDER BY (codigo_corto = :exacto) DESC, descripcion LIMIT 15',
            ['emp' => $empresaId, 'q' => '%' . $q . '%', 'exacto' => $q]
        );
    }

    public static function guardar(int $empresaId, array $d, ?int $id = null): int
    {
        $params = [
            'emp'          => $empresaId,
            'codigo_corto' => $d['codigo_corto'] ?: null,
            'descripcion'  => $d['descripcion'],
            'tipo'         => $d['tipo'],
            'precio_venta' => $d['precio_venta'],
            'iva'          => $d['iva'],
            'stock_minimo' => $d['stock_minimo'],
            'activo'       => $d['activo'] ? 'true' : 'false',
        ];
        if ($id === null) {
            $id = (int) self::valor(
                'INSERT INTO producto (empresa_id, codigo_corto, descripcion, tipo, precio_venta, iva, stock_minimo, activo)
                 VALUES (:emp, :codigo_corto, :descripcion, :tipo, :precio_venta, :iva, :stock_minimo, :activo)
                 RETURNING id',
                $params
            );
            if ($d['tipo'] === self::PRODUCTO && $d['stock_inicial'] > 0) {
                self::moverStock($id, $d['stock_inicial'], 'ENTRADA', null, $d['usuario_id'], 'Stock inicial');
            }
            return $id;
        }
        $params['id'] = $id;
        self::ejecutar(
            'UPDATE producto SET codigo_corto = :codigo_corto,
             descripcion = :descripcion, tipo = :tipo, precio_venta = :precio_venta, iva = :iva,
             stock_minimo = :stock_minimo, activo = :activo WHERE id = :id AND empresa_id = :emp',
            $params
        );
        return $id;
    }

    /**
     * Registra un movimiento y actualiza el stock. Debe llamarse dentro de una transacción
     * cuando forma parte de una operación mayor (factura, anulación).
     * Devuelve el stock resultante.
     */
    public static function moverStock(int $productoId, float $cantidad, string $tipo, ?int $facturaId, ?int $usuarioId, ?string $obs = null): float
    {
        $stock = (float) self::valor(
            'UPDATE producto SET stock_actual = stock_actual + ? WHERE id = ? RETURNING stock_actual',
            [$cantidad, $productoId]
        );
        self::ejecutar(
            'INSERT INTO movimiento_stock (producto_id, tipo, cantidad, stock_resultante, factura_id, usuario_id, observacion)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$productoId, $tipo, $cantidad, $stock, $facturaId, $usuarioId, $obs]
        );
        return $stock;
    }

    /** Ajuste manual o entrada de mercadería desde la pantalla de productos. */
    public static function ajustarStock(int $productoId, int $empresaId, float $cantidad, string $tipo, int $usuarioId, string $obs): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $producto = self::uno('SELECT * FROM producto WHERE id = ? AND empresa_id = ? FOR UPDATE', [$productoId, $empresaId]);
            if ($producto === null || $producto['tipo'] !== self::PRODUCTO) {
                throw new \DomainException('Solo los productos físicos llevan stock.');
            }
            self::moverStock($productoId, $cantidad, $tipo, null, $usuarioId, $obs);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function movimientos(int $productoId): array
    {
        return self::todos(
            'SELECT m.*, u.nombre AS usuario, f.numero_completo FROM movimiento_stock m
             LEFT JOIN usuario u ON u.id = m.usuario_id
             LEFT JOIN factura f ON f.id = m.factura_id
             WHERE m.producto_id = ? ORDER BY m.fecha DESC, m.id DESC LIMIT 200',
            [$productoId]
        );
    }

    /** Catálogo activo de la empresa para las tarjetas de la pantalla de venta (son pocos, se cargan todos). */
    public static function paraVenta(int $empresaId): array
    {
        return self::todos(
            'SELECT id, codigo_corto, descripcion, tipo, precio_venta, iva, stock_actual
             FROM producto p WHERE p.empresa_id = ? AND p.activo
             ORDER BY p.descripcion',
            [$empresaId]
        );
    }

    /** Oculta productos de esta empresa (quedan inactivos; las facturas viejas no cambian). */
    public static function desactivarVarios(int $empresaId, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        return self::ejecutar("UPDATE producto SET activo = false WHERE empresa_id = ? AND id IN ($marcas)", array_merge([$empresaId], $ids));
    }

    /** Código sugerido para un producto nuevo: el último código numérico de la empresa + 1. */
    public static function siguienteCodigo(int $empresaId): string
    {
        $ultimo = self::valor(
            "SELECT MAX(codigo_corto::bigint) FROM producto WHERE empresa_id = ? AND codigo_corto ~ '^[0-9]{1,15}$'",
            [$empresaId]
        );
        return (string) ($ultimo ? (int) $ultimo + 1 : 1);
    }

    public static function contar(int $empresaId): int
    {
        return (int) self::valor('SELECT COUNT(*) FROM producto WHERE empresa_id = ? AND activo', [$empresaId]);
    }

    public static function stockBajo(int $empresaId): array
    {
        return self::todos(
            "SELECT id, descripcion, stock_actual, stock_minimo FROM producto
             WHERE empresa_id = ? AND activo AND tipo = 'P' AND stock_actual <= stock_minimo
             ORDER BY descripcion LIMIT 10",
            [$empresaId]
        );
    }
}
