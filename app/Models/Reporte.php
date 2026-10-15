<?php
namespace App\Models;

use App\Core\Model;

class Reporte extends Model
{
    public static function resumen(int $empresaId, string $desde, string $hasta): array
    {
        return self::uno(
            "SELECT COUNT(*) FILTER (WHERE estado = 'EMITIDA') AS cantidad,
                    COALESCE(SUM(total) FILTER (WHERE estado = 'EMITIDA'), 0) AS monto,
                    COALESCE(SUM(total_iva5 + total_iva10) FILTER (WHERE estado = 'EMITIDA'), 0) AS iva,
                    COUNT(*) FILTER (WHERE estado = 'ANULADA') AS anuladas,
                    COALESCE(SUM(total) FILTER (WHERE estado = 'EMITIDA' AND condicion = 'CONTADO_FECHA'), 0) AS monto_con_fecha
             FROM factura WHERE empresa_id = ? AND fecha BETWEEN ? AND ?",
            [$empresaId, $desde, $hasta]
        );
    }

    /** Lo que efectivamente entró en caja/banco en el período (por fecha de cobro). */
    public static function cobradoPorMetodo(int $empresaId, string $desde, string $hasta): array
    {
        return self::todos(
            "SELECT c.metodo_pago, COUNT(*) AS cantidad, SUM(c.monto) AS monto
             FROM cobro c JOIN factura f ON f.id = c.factura_id
             WHERE f.empresa_id = ? AND f.estado = 'EMITIDA' AND c.fecha BETWEEN ? AND ?
             GROUP BY c.metodo_pago ORDER BY monto DESC",
            [$empresaId, $desde, $hasta]
        );
    }

    public static function ventasPorDia(int $empresaId, string $desde, string $hasta): array
    {
        return self::todos(
            "SELECT fecha, COUNT(*) AS cantidad, SUM(total) AS monto FROM factura
             WHERE empresa_id = ? AND estado = 'EMITIDA' AND fecha BETWEEN ? AND ?
             GROUP BY fecha ORDER BY fecha",
            [$empresaId, $desde, $hasta]
        );
    }

    public static function porEmpresa(array $empresaIds, string $desde, string $hasta): array
    {
        if (!$empresaIds) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($empresaIds), '?'));
        return self::todos(
            "SELECT e.id, COALESCE(e.nombre_fantasia, e.razon_social) AS nombre, e.color,
                    COUNT(f.id) AS cantidad, COALESCE(SUM(f.total), 0) AS monto
             FROM empresa e
             LEFT JOIN factura f ON f.empresa_id = e.id AND f.estado = 'EMITIDA' AND f.fecha BETWEEN ? AND ?
             WHERE e.id IN ($marcas)
             GROUP BY e.id ORDER BY e.id",
            array_merge([$desde, $hasta], $empresaIds)
        );
    }

    public static function topClientes(int $empresaId, string $desde, string $hasta, int $limite = 10): array
    {
        return self::todos(
            "SELECT ruc_cliente, razon_social_cliente, COUNT(*) AS cantidad, SUM(total) AS monto
             FROM factura WHERE empresa_id = ? AND estado = 'EMITIDA' AND fecha BETWEEN ? AND ?
             GROUP BY ruc_cliente, razon_social_cliente ORDER BY monto DESC LIMIT $limite",
            [$empresaId, $desde, $hasta]
        );
    }

    public static function topProductos(int $empresaId, string $desde, string $hasta, int $limite = 10): array
    {
        return self::todos(
            "SELECT COALESCE(p.descripcion, d.descripcion) AS descripcion, p.tipo,
                    SUM(d.cantidad) AS cantidad, SUM(d.subtotal) AS monto
             FROM factura_detalle d
             JOIN factura f ON f.id = d.factura_id
             LEFT JOIN producto p ON p.id = d.producto_id
             WHERE f.empresa_id = ? AND f.estado = 'EMITIDA' AND f.fecha BETWEEN ? AND ?
             GROUP BY COALESCE(p.descripcion, d.descripcion), p.tipo ORDER BY monto DESC LIMIT $limite",
            [$empresaId, $desde, $hasta]
        );
    }

    // ---------- Panel de inicio ----------

    /** Números del día, del mes (con el mes anterior al mismo día para comparar) y deuda vencida. */
    public static function panel(int $empresaId): array
    {
        return self::uno(
            "SELECT
                (SELECT COUNT(*) FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha = current_date) AS hoy_cantidad,
                (SELECT COALESCE(SUM(total), 0) FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha = current_date) AS hoy_monto,
                (SELECT COALESCE(SUM(c.monto), 0) FROM cobro c JOIN factura f ON f.id = c.factura_id
                  WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND c.fecha = current_date) AS hoy_cobrado,
                (SELECT COALESCE(SUM(total), 0) FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA'
                  AND fecha >= date_trunc('month', current_date)) AS mes_monto,
                (SELECT COALESCE(SUM(total), 0) FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA'
                  AND fecha >= date_trunc('month', current_date) - interval '1 month'
                  AND fecha <= current_date - interval '1 month') AS mes_anterior_monto,
                (SELECT COALESCE(SUM(s.saldo), 0) FROM factura f JOIN v_factura_saldo s ON s.factura_id = f.id
                  WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND f.condicion = 'CONTADO_FECHA'
                  AND s.saldo > 0 AND f.fecha_pago < current_date) AS vencido_monto",
            ['e' => $empresaId]
        );
    }

    /** Mejores clientes del mes: los que más facturaron. */
    public static function mejoresClientes(int $empresaId, int $limite = 5): array
    {
        return self::todos(
            "SELECT razon_social_cliente AS cliente, COUNT(*) AS facturas, SUM(total) AS monto
             FROM factura WHERE empresa_id = ? AND estado = 'EMITIDA' AND fecha >= date_trunc('month', current_date)
             GROUP BY 1 ORDER BY 3 DESC LIMIT " . $limite,
            [$empresaId]
        );
    }

    /** Clientes distintos atendidos en el mes. */
    public static function clientesDelMes(int $empresaId): int
    {
        return (int) self::valor(
            "SELECT COUNT(DISTINCT cliente_id) FROM factura
             WHERE empresa_id = ? AND estado = 'EMITIDA' AND fecha >= date_trunc('month', current_date)",
            [$empresaId]
        );
    }

    /**
     * Cobranza: lo cobrado en efectivo y por transferencia (hoy y en el mes) y lo que vence en los próximos 7 días
     * de las facturas con fecha de pago. Cuenta todo cobro registrado: contado al facturar y cobros posteriores.
     */
    public static function cobros(int $empresaId): array
    {
        return self::uno(
            "SELECT
                COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'EFECTIVO'), 0) AS efectivo_mes,
                COUNT(*) FILTER (WHERE c.metodo_pago = 'EFECTIVO') AS efectivo_cantidad,
                COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'EFECTIVO' AND c.fecha = current_date), 0) AS efectivo_hoy,
                COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'TRANSFERENCIA'), 0) AS transferencia_mes,
                COUNT(*) FILTER (WHERE c.metodo_pago = 'TRANSFERENCIA') AS transferencia_cantidad,
                COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'TRANSFERENCIA' AND c.fecha = current_date), 0) AS transferencia_hoy,
                (SELECT COALESCE(SUM(s.saldo), 0) FROM factura f2 JOIN v_factura_saldo s ON s.factura_id = f2.id
                  WHERE f2.empresa_id = :e AND f2.estado = 'EMITIDA' AND f2.condicion = 'CONTADO_FECHA' AND s.saldo > 0
                  AND f2.fecha_pago BETWEEN current_date AND current_date + 7) AS semana_monto,
                (SELECT COUNT(*) FROM factura f2 JOIN v_factura_saldo s ON s.factura_id = f2.id
                  WHERE f2.empresa_id = :e AND f2.estado = 'EMITIDA' AND f2.condicion = 'CONTADO_FECHA' AND s.saldo > 0
                  AND f2.fecha_pago BETWEEN current_date AND current_date + 7) AS semana_cantidad
             FROM cobro c JOIN factura f ON f.id = c.factura_id
             WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND c.fecha >= date_trunc('month', current_date)",
            ['e' => $empresaId]
        );
    }

    /** Cobrado por día del mes actual, separado en efectivo y transferencia. */
    public static function cobrosPorDia(int $empresaId): array
    {
        return self::todos(
            "SELECT to_char(d, 'DD/MM') AS dia,
                    COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'EFECTIVO'), 0) AS efectivo,
                    COALESCE(SUM(c.monto) FILTER (WHERE c.metodo_pago = 'TRANSFERENCIA'), 0) AS transferencia
             FROM generate_series(date_trunc('month', current_date), current_date, interval '1 day') AS d
             LEFT JOIN (cobro c JOIN factura f ON f.id = c.factura_id AND f.empresa_id = ? AND f.estado = 'EMITIDA')
                    ON c.fecha = d::date
             GROUP BY d ORDER BY d",
            [$empresaId]
        );
    }

    /** Facturado de cada día del mes actual hasta hoy, con los días sin ventas en cero. */
    public static function diasDelMes(int $empresaId): array
    {
        return self::todos(
            "SELECT to_char(d, 'DD/MM') AS dia, COALESCE(SUM(f.total), 0) AS monto
             FROM generate_series(date_trunc('month', current_date), current_date, interval '1 day') AS d
             LEFT JOIN factura f ON f.fecha = d::date AND f.empresa_id = ? AND f.estado = 'EMITIDA'
             GROUP BY d ORDER BY d",
            [$empresaId]
        );
    }

    /** Facturado por mes en los últimos 6 meses (incluido el actual). */
    public static function ultimosMeses(int $empresaId): array
    {
        return self::todos(
            "SELECT to_char(m, 'MM/YYYY') AS mes, COALESCE(SUM(f.total), 0) AS monto, COUNT(f.id) AS cantidad
             FROM generate_series(date_trunc('month', current_date) - interval '5 months', date_trunc('month', current_date), interval '1 month') AS m
             LEFT JOIN factura f ON date_trunc('month', f.fecha) = m AND f.empresa_id = ? AND f.estado = 'EMITIDA'
             GROUP BY m ORDER BY m",
            [$empresaId]
        );
    }

    /** Ventas del mes por modalidad: contado efectivo, contado transferencia y con fecha de pago. */
    public static function modalidadesDelMes(int $empresaId): array
    {
        return self::todos(
            "SELECT CASE WHEN condicion = 'CONTADO_FECHA' THEN 'Con fecha de pago'
                         WHEN metodo_pago = 'TRANSFERENCIA' THEN 'Transferencia' ELSE 'Efectivo' END AS modalidad,
                    COUNT(*) AS cantidad, SUM(total) AS monto
             FROM factura WHERE empresa_id = ? AND estado = 'EMITIDA' AND fecha >= date_trunc('month', current_date)
             GROUP BY 1 ORDER BY 1",
            [$empresaId]
        );
    }
}
