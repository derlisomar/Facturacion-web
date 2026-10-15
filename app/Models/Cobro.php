<?php
namespace App\Models;

use App\Core\Model;

class Cobro extends Model
{
    public static function deFactura(int $facturaId): array
    {
        return self::todos(
            'SELECT c.*, u.nombre AS usuario FROM cobro c LEFT JOIN usuario u ON u.id = c.usuario_id
             WHERE c.factura_id = ? ORDER BY c.fecha, c.id',
            [$facturaId]
        );
    }

    /** Facturas con fecha de pago que todavía tienen saldo. */
    public static function pendientes(int $empresaId, string $filtro, string $busqueda): array
    {
        $params = ['emp' => $empresaId];
        $where = "f.empresa_id = :emp AND f.estado = 'EMITIDA' AND f.condicion = 'CONTADO_FECHA' AND s.saldo > 0";
        if ($filtro === 'vencidas') {
            $where .= ' AND f.fecha_pago < current_date';
        } elseif ($filtro === 'semana') {
            $where .= " AND f.fecha_pago BETWEEN current_date AND current_date + 7";
        }
        if ($busqueda !== '') {
            $where .= ' AND (f.razon_social_cliente ILIKE :q OR f.ruc_cliente ILIKE :q OR f.numero_completo ILIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }
        return self::todos(
            "SELECT f.id, f.numero_completo, f.fecha, f.fecha_pago, f.razon_social_cliente, f.ruc_cliente,
                    f.telefono_cliente, f.total, s.cobrado, s.saldo, (f.fecha_pago - current_date) AS dias
             FROM factura f JOIN v_factura_saldo s ON s.factura_id = f.id
             WHERE $where ORDER BY f.fecha_pago, f.numero",
            $params
        );
    }

    /** @param int[] $empresas empresas en las que el usuario puede cobrar */
    public static function registrar(int $facturaId, array $empresas, array $d, int $usuarioId): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $factura = self::uno('SELECT * FROM factura WHERE id = ? FOR UPDATE', [$facturaId]);
            if ($factura === null || $factura['estado'] !== 'EMITIDA'
                || !in_array((int) $factura['empresa_id'], array_map('intval', $empresas), true)) {
                throw new \DomainException('La factura no existe o está anulada.');
            }
            $saldo = (int) self::valor('SELECT saldo FROM v_factura_saldo WHERE factura_id = ?', [$facturaId]);
            if ($d['monto'] <= 0 || $d['monto'] > $saldo) {
                throw new \DomainException('El monto debe ser mayor a 0 y no superar el saldo (' . gs($saldo) . ').');
            }
            self::ejecutar(
                'INSERT INTO cobro (factura_id, fecha, monto, metodo_pago, referencia, usuario_id) VALUES (?, ?, ?, ?, ?, ?)',
                [$facturaId, $d['fecha'], $d['monto'], $d['metodo_pago'], $d['referencia'] ?: null, $usuarioId]
            );
            if ($d['monto'] === $saldo && empty($factura['metodo_pago'])) {
                self::ejecutar('UPDATE factura SET metodo_pago = ? WHERE id = ?', [$d['metodo_pago'], $facturaId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function resumenPendiente(int $empresaId): array
    {
        return self::uno(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(s.saldo), 0) AS saldo,
                    COUNT(*) FILTER (WHERE f.fecha_pago < current_date) AS vencidas
             FROM factura f JOIN v_factura_saldo s ON s.factura_id = f.id
             WHERE f.empresa_id = ? AND f.estado = 'EMITIDA' AND f.condicion = 'CONTADO_FECHA' AND s.saldo > 0",
            [$empresaId]
        );
    }
}
