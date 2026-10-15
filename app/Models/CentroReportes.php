<?php
namespace App\Models;

use App\Core\Model;

/**
 * Centro de reportes: cada reporte devuelve columnas y filas por empresa, y el Exportador
 * los convierte en pantalla, Excel o PDF. Para agregar un reporte: una entrada en definiciones()
 * y un case en filas().
 */
class CentroReportes extends Model
{
    public static function grupos(): array
    {
        return [
            ['nombre' => 'Ventas', 'icono' => 'fa-chart-line', 'color' => 'azul', 'reportes' => [
                'libro_ventas' => ['nombre' => 'Libro de ventas', 'desc' => 'Todas las facturas con exenta, gravadas, IVA 5 % y 10 %. Sirve para la declaración de IVA.', 'estado' => true],
                'ventas_dia'   => ['nombre' => 'Ventas por día', 'desc' => 'Cantidad de facturas e importes de cada día.'],
                'ventas_mes'   => ['nombre' => 'Resumen mensual de IVA', 'desc' => 'Gravadas e IVA por mes, listo para pasar al formulario 120.'],
                'por_cliente'  => ['nombre' => 'Ventas por cliente', 'desc' => 'Cuánto compró cada cliente en el período, de mayor a menor.'],
                'por_producto' => ['nombre' => 'Ventas por producto o servicio', 'desc' => 'Cantidades e importes vendidos de cada producto y servicio.'],
                'por_condicion'=> ['nombre' => 'Ventas por condición y método', 'desc' => 'Contado en efectivo, por transferencia y con fecha de pago.'],
            ]],
            ['nombre' => 'Cobros', 'icono' => 'fa-hand-holding-dollar', 'color' => 'verde', 'reportes' => [
                'cobros'     => ['nombre' => 'Cobros recibidos', 'desc' => 'Todo lo cobrado en el período, por fecha de cobro y método.'],
                'pendientes' => ['nombre' => 'Facturas pendientes de cobro', 'desc' => 'Facturas con fecha de pago que todavía tienen saldo, con días de atraso.', 'sin_fechas' => true],
            ]],
            ['nombre' => 'Stock', 'icono' => 'fa-boxes-stacked', 'color' => 'ambar', 'reportes' => [
                'stock'       => ['nombre' => 'Stock actual valorizado', 'desc' => 'Existencias de los productos físicos, valor a precio de venta y alertas de mínimo.', 'sin_fechas' => true],
                'movimientos' => ['nombre' => 'Movimientos de stock', 'desc' => 'Entradas, ventas, anulaciones y ajustes de cada producto.'],
            ]],
            ['nombre' => 'Control', 'icono' => 'fa-shield-halved', 'color' => 'rojo', 'reportes' => [
                'anuladas' => ['nombre' => 'Facturas y hojas anuladas', 'desc' => 'Qué se anuló, cuándo y por qué.'],
            ]],
        ];
    }

    public static function definicion(string $clave): ?array
    {
        foreach (self::grupos() as $g) {
            if (isset($g['reportes'][$clave])) {
                return $g['reportes'][$clave] + ['grupo' => $g['nombre']];
            }
        }
        return null;
    }

    /**
     * Arma el documento que entiende el Exportador.
     * $empresas: [id => nombre]; con más de una, cada empresa sale en su propia hoja o sección.
     */
    public static function documento(string $clave, array $empresas, array $f, string $usuario): array
    {
        $def = self::definicion($clave);
        $secciones = [];
        foreach ($empresas as $id => $nombre) {
            [$columnas, $filas] = self::filas($clave, (int) $id, $f);
            $secciones[] = ['titulo' => $nombre, 'columnas' => $columnas, 'filas' => $filas];
        }
        $subtitulo = empty($def['sin_fechas'])
            ? 'Del ' . date('d/m/Y', strtotime($f['desde'])) . ' al ' . date('d/m/Y', strtotime($f['hasta']))
            : 'Al ' . date('d/m/Y');
        if (!empty($def['estado'])) {
            $subtitulo .= ' · ' . (['EMITIDA' => 'No anuladas', 'ANULADA' => 'Anuladas', '' => 'Todas'][$f['estado']] ?? 'Todas');
        }
        return [
            'negocio'   => count($empresas) === 1 ? reset($empresas) : config('app.nombre'),
            'titulo'    => $def['nombre'],
            'subtitulo' => $subtitulo,
            'generado'  => date('d/m/Y H:i'),
            'usuario'   => $usuario,
            'secciones' => $secciones,
        ];
    }

    private static function c(string $titulo, string $tipo = 'texto', bool $total = false): array
    {
        return ['t' => $titulo, 'tipo' => $tipo, 'total' => $total];
    }

    /** @return array{0: array, 1: array} columnas y filas (cada fila es una lista en el orden de las columnas) */
    private static function filas(string $clave, int $empresaId, array $f): array
    {
        $p = ['e' => $empresaId, 'd' => $f['desde'], 'h' => $f['hasta']];
        $sinAnular = fn(string $col) => "CASE WHEN f.estado = 'ANULADA' THEN 0 ELSE $col END";

        switch ($clave) {
            case 'libro_ventas':
                $estado = in_array($f['estado'], ['EMITIDA', 'ANULADA'], true) ? 'AND f.estado = :est' : '';
                if ($estado) {
                    $p['est'] = $f['estado'];
                }
                $filas = self::filasNum(
                    "SELECT to_char(f.fecha, 'DD/MM/YYYY'), f.numero_completo, COALESCE(f.ruc_cliente, ''),
                            COALESCE(f.razon_social_cliente, '(hoja anulada)'),
                            CASE f.condicion WHEN 'CONTADO' THEN 'Contado' ELSE 'Con fecha de pago' END,
                            COALESCE(initcap(f.metodo_pago), ''),
                            {$sinAnular('f.total_exenta')}, {$sinAnular('f.total_gravada5')}, {$sinAnular('f.total_gravada10')},
                            {$sinAnular('f.total_iva5')}, {$sinAnular('f.total_iva10')}, {$sinAnular('f.total')},
                            CASE f.estado WHEN 'ANULADA' THEN 'Anulada' ELSE 'Emitida' END
                     FROM factura f
                     WHERE f.empresa_id = :e AND f.fecha BETWEEN :d AND :h $estado
                     ORDER BY f.fecha, f.numero_completo",
                    $p
                );
                return [[
                    self::c('Fecha'), self::c('Número'), self::c('RUC'), self::c('Razón social'), self::c('Condición'), self::c('Método'),
                    self::c('Exenta', 'dinero', true), self::c('Gravada 5%', 'dinero', true), self::c('Gravada 10%', 'dinero', true),
                    self::c('IVA 5%', 'dinero', true), self::c('IVA 10%', 'dinero', true), self::c('Total', 'dinero', true), self::c('Estado'),
                ], $filas];

            case 'ventas_dia':
                $filas = self::filasNum(
                    "SELECT to_char(fecha, 'DD/MM/YYYY'), COUNT(*), SUM(total_exenta), SUM(total_gravada5 + total_gravada10),
                            SUM(total_iva5 + total_iva10), SUM(total)
                     FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha BETWEEN :d AND :h
                     GROUP BY fecha ORDER BY fecha",
                    $p
                );
                return [[
                    self::c('Fecha'), self::c('Facturas', 'entero', true), self::c('Exenta', 'dinero', true),
                    self::c('Gravadas', 'dinero', true), self::c('IVA', 'dinero', true), self::c('Total', 'dinero', true),
                ], $filas];

            case 'ventas_mes':
                $filas = self::filasNum(
                    "SELECT to_char(date_trunc('month', fecha), 'MM/YYYY'), COUNT(*), SUM(total_exenta),
                            SUM(total_gravada5), SUM(total_iva5), SUM(total_gravada10), SUM(total_iva10),
                            SUM(total_iva5 + total_iva10), SUM(total)
                     FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha BETWEEN :d AND :h
                     GROUP BY date_trunc('month', fecha) ORDER BY date_trunc('month', fecha)",
                    $p
                );
                return [[
                    self::c('Mes'), self::c('Facturas', 'entero', true), self::c('Exenta', 'dinero', true),
                    self::c('Gravada 5%', 'dinero', true), self::c('IVA 5%', 'dinero', true),
                    self::c('Gravada 10%', 'dinero', true), self::c('IVA 10%', 'dinero', true),
                    self::c('IVA total', 'dinero', true), self::c('Total', 'dinero', true),
                ], $filas];

            case 'por_cliente':
                $filas = self::filasNum(
                    "SELECT COALESCE(ruc_cliente, ''), razon_social_cliente, COUNT(*), SUM(total_iva5 + total_iva10), SUM(total),
                            to_char(MAX(fecha), 'DD/MM/YYYY')
                     FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha BETWEEN :d AND :h
                     GROUP BY ruc_cliente, razon_social_cliente ORDER BY SUM(total) DESC",
                    $p
                );
                return [[
                    self::c('RUC'), self::c('Razón social'), self::c('Facturas', 'entero', true), self::c('IVA', 'dinero', true),
                    self::c('Total', 'dinero', true), self::c('Última compra'),
                ], $filas];

            case 'por_producto':
                $filas = self::filasNum(
                    "SELECT COALESCE(p.codigo_corto, p.codigo, ''), COALESCE(p.descripcion, d.descripcion),
                            CASE p.tipo WHEN 'P' THEN 'Producto' WHEN 'S' THEN 'Servicio' ELSE '' END,
                            SUM(d.cantidad), SUM(d.subtotal)
                     FROM factura_detalle d
                     JOIN factura f ON f.id = d.factura_id
                     LEFT JOIN producto p ON p.id = d.producto_id
                     WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND f.fecha BETWEEN :d AND :h
                     GROUP BY 1, 2, 3 ORDER BY SUM(d.subtotal) DESC",
                    $p
                );
                return [[
                    self::c('Código'), self::c('Descripción'), self::c('Tipo'),
                    self::c('Cantidad', 'cantidad', true), self::c('Total', 'dinero', true),
                ], $filas];

            case 'por_condicion':
                $filas = self::filasNum(
                    "SELECT CASE condicion WHEN 'CONTADO' THEN 'Contado' ELSE 'Con fecha de pago' END,
                            COALESCE(initcap(metodo_pago), '(se define al cobrar)'), COUNT(*), SUM(total)
                     FROM factura WHERE empresa_id = :e AND estado = 'EMITIDA' AND fecha BETWEEN :d AND :h
                     GROUP BY 1, 2 ORDER BY 1, 2",
                    $p
                );
                return [[
                    self::c('Condición'), self::c('Método'), self::c('Facturas', 'entero', true), self::c('Total', 'dinero', true),
                ], $filas];

            case 'cobros':
                $filas = self::filasNum(
                    "SELECT to_char(c.fecha, 'DD/MM/YYYY'), f.numero_completo, COALESCE(f.ruc_cliente, ''), f.razon_social_cliente,
                            CASE f.condicion WHEN 'CONTADO' THEN 'Contado' ELSE 'Con fecha de pago' END,
                            initcap(c.metodo_pago), COALESCE(c.referencia, ''), c.monto
                     FROM cobro c JOIN factura f ON f.id = c.factura_id
                     WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND c.fecha BETWEEN :d AND :h
                     ORDER BY c.fecha, f.numero_completo",
                    $p
                );
                return [[
                    self::c('Fecha de cobro'), self::c('Factura'), self::c('RUC'), self::c('Cliente'), self::c('Condición'),
                    self::c('Método'), self::c('Referencia'), self::c('Monto', 'dinero', true),
                ], $filas];

            case 'pendientes':
                $filas = self::filasNum(
                    "SELECT f.numero_completo, to_char(f.fecha, 'DD/MM/YYYY'), COALESCE(f.ruc_cliente, ''), f.razon_social_cliente,
                            to_char(f.fecha_pago, 'DD/MM/YYYY'), GREATEST(current_date - f.fecha_pago, 0),
                            f.total, s.cobrado, s.saldo
                     FROM factura f JOIN v_factura_saldo s ON s.factura_id = f.id
                     WHERE f.empresa_id = :e AND f.estado = 'EMITIDA' AND f.condicion = 'CONTADO_FECHA' AND s.saldo > 0
                     ORDER BY f.fecha_pago, f.numero_completo",
                    ['e' => $empresaId]
                );
                return [[
                    self::c('Factura'), self::c('Fecha'), self::c('RUC'), self::c('Cliente'), self::c('Fecha de pago'),
                    self::c('Días de atraso', 'entero'), self::c('Total', 'dinero', true), self::c('Cobrado', 'dinero', true),
                    self::c('Saldo', 'dinero', true),
                ], $filas];

            case 'stock':
                $filas = self::filasNum(
                    "SELECT COALESCE(p.codigo_corto, p.codigo, ''), p.descripcion,
                            p.stock_actual, p.stock_minimo, p.precio_venta, round(p.stock_actual * p.precio_venta),
                            CASE WHEN p.stock_actual <= 0 THEN 'Sin stock'
                                 WHEN p.stock_actual <= p.stock_minimo THEN 'Bajo mínimo' ELSE 'Normal' END
                     FROM producto p
                     WHERE p.empresa_id = :e AND p.tipo = 'P' AND p.activo
                     ORDER BY p.descripcion",
                    ['e' => $empresaId]
                );
                return [[
                    self::c('Código'), self::c('Producto'), self::c('Stock', 'cantidad', true),
                    self::c('Mínimo', 'cantidad'), self::c('Precio', 'dinero'), self::c('Valorizado', 'dinero', true), self::c('Estado'),
                ], $filas];

            case 'movimientos':
                $filas = self::filasNum(
                    "SELECT to_char(m.fecha, 'DD/MM/YYYY HH24:MI'), p.descripcion,
                            CASE m.tipo WHEN 'ENTRADA' THEN 'Entrada' WHEN 'SALIDA_VENTA' THEN 'Venta'
                                        WHEN 'ANULACION' THEN 'Anulación' ELSE 'Ajuste' END,
                            m.cantidad, m.stock_resultante, COALESCE(f.numero_completo, ''), COALESCE(u.nombre, ''),
                            COALESCE(m.observacion, '')
                     FROM movimiento_stock m
                     JOIN producto p ON p.id = m.producto_id
                     LEFT JOIN factura f ON f.id = m.factura_id
                     LEFT JOIN usuario u ON u.id = m.usuario_id
                     WHERE p.empresa_id = :e AND m.fecha::date BETWEEN :d AND :h
                     ORDER BY m.fecha, m.id",
                    $p
                );
                return [[
                    self::c('Fecha'), self::c('Producto'), self::c('Movimiento'), self::c('Cantidad', 'cantidad'),
                    self::c('Stock resultante', 'cantidad'), self::c('Factura'), self::c('Usuario'), self::c('Observación'),
                ], $filas];

            case 'anuladas':
                $filas = self::filasNum(
                    "SELECT to_char(f.fecha, 'DD/MM/YYYY'), f.numero_completo, COALESCE(f.ruc_cliente, ''),
                            COALESCE(f.razon_social_cliente, '(hoja anulada sin usar)'), f.total,
                            COALESCE(f.motivo_anulacion, ''), COALESCE(to_char(f.anulado_en, 'DD/MM/YYYY HH24:MI'), '')
                     FROM factura f
                     WHERE f.empresa_id = :e AND f.estado = 'ANULADA' AND f.fecha BETWEEN :d AND :h
                     ORDER BY f.fecha, f.numero_completo",
                    $p
                );
                return [[
                    self::c('Fecha'), self::c('Número'), self::c('RUC'), self::c('Cliente'), self::c('Total', 'dinero'),
                    self::c('Motivo'), self::c('Anulada el'),
                ], $filas];
        }
        return [[self::c('Sin datos')], []];
    }

    /** Filas como listas por posición (varias columnas se llaman igual, p. ej. "sum"). */
    private static function filasNum(string $sql, array $params): array
    {
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_NUM);
    }
}
