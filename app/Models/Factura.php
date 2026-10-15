<?php
namespace App\Models;

use App\Core\Model;
use App\Helpers\NumeroALetras;
use PDOException;

class Factura extends Model
{
    public const CONTADO = 'CONTADO';
    public const CONTADO_FECHA = 'CONTADO_FECHA';

    /** @param int|int[] $empresas empresa activa o lista de empresas permitidas */
    /** Largo máximo de la descripción del detalle (500; 200 si la base todavía no se actualizó). */
    public static function largoDescripcion(): int
    {
        static $largo = null;
        return $largo ??= (int) (self::valor(
            "SELECT character_maximum_length FROM information_schema.columns
             WHERE table_name = 'factura_detalle' AND column_name = 'descripcion' LIMIT 1"
        ) ?: 200);
    }

    public static function buscar(int $id, $empresas): ?array
    {
        $empresas = array_map('intval', (array) $empresas);
        if (!$empresas) {
            return null;
        }
        $marcas = implode(',', array_fill(0, count($empresas), '?'));
        return self::uno(
            "SELECT f.*, t.numero AS timbrado_numero, t.vigencia_desde, t.vigencia_hasta, u.nombre AS usuario,
                    s.cobrado, s.saldo, COALESCE(e.nombre_fantasia, e.razon_social) AS empresa_nombre
             FROM factura f
             JOIN empresa e ON e.id = f.empresa_id
             JOIN timbrado t ON t.id = f.timbrado_id
             LEFT JOIN usuario u ON u.id = f.usuario_id
             JOIN v_factura_saldo s ON s.factura_id = f.id
             WHERE f.id = ? AND f.empresa_id IN ($marcas)",
            array_merge([$id], $empresas)
        );
    }

    /** Detalle de varias facturas a la vez, agrupado por factura (para la consulta). */
    public static function detallesDe(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $agrupado = [];
        foreach (self::todos("SELECT * FROM factura_detalle WHERE factura_id IN ($marcas) ORDER BY id", array_map('intval', $ids)) as $d) {
            $agrupado[$d['factura_id']][] = $d;
        }
        return $agrupado;
    }

    public static function detalle(int $facturaId): array
    {
        return self::todos('SELECT * FROM factura_detalle WHERE factura_id = ? ORDER BY id', [$facturaId]);
    }

    /**
     * Consulta de facturas. $empresas = ids de empresas a incluir.
     * $porPagina = 0 trae todo (para imprimir el listado o exportar).
     */
    public static function listar(array $empresas, array $filtros, int $pagina = 1, int $porPagina = 30): array
    {
        $empresas = array_map('intval', $empresas) ?: [0];
        $params = array_merge($empresas, [$filtros['desde'], $filtros['hasta']]);
        $where = 'f.empresa_id IN (' . implode(',', array_fill(0, count($empresas), '?')) . ') AND f.fecha BETWEEN ? AND ?';
        if ($filtros['busqueda'] !== '') {
            $where .= ' AND (f.razon_social_cliente ILIKE ? OR f.ruc_cliente ILIKE ? OR f.numero_completo ILIKE ?)';
            $q = '%' . $filtros['busqueda'] . '%';
            array_push($params, $q, $q, $q);
        }
        if (in_array($filtros['condicion'], [self::CONTADO, self::CONTADO_FECHA], true)) {
            $where .= ' AND f.condicion = ?';
            $params[] = $filtros['condicion'];
        }
        if (in_array($filtros['metodo'], ['EFECTIVO', 'TRANSFERENCIA'], true)) {
            $where .= ' AND f.metodo_pago = ?';
            $params[] = $filtros['metodo'];
        }
        if (in_array($filtros['estado'], ['EMITIDA', 'ANULADA'], true)) {
            $where .= ' AND f.estado = ?';
            $params[] = $filtros['estado'];
        }
        $totales = self::uno(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(f.total) FILTER (WHERE f.estado = 'EMITIDA'), 0) AS monto,
                    COALESCE(SUM(f.total_iva5 + f.total_iva10) FILTER (WHERE f.estado = 'EMITIDA'), 0) AS iva
             FROM factura f WHERE $where",
            $params
        );
        $limite = $porPagina > 0 ? " LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina) : '';
        $filas = self::todos(
            "SELECT f.*, s.saldo, COALESCE(e.nombre_fantasia, e.razon_social) AS empresa_nombre, e.color AS empresa_color,
                    (SELECT COUNT(*) FROM factura_detalle d WHERE d.factura_id = f.id) AS items
             FROM factura f
             JOIN v_factura_saldo s ON s.factura_id = f.id
             JOIN empresa e ON e.id = f.empresa_id
             WHERE $where ORDER BY f.fecha DESC, f.numero DESC" . $limite,
            $params
        );
        return ['filas' => $filas, 'total' => (int) $totales['cantidad'], 'monto' => (int) $totales['monto'], 'iva' => (int) $totales['iva']];
    }

    /**
     * Graba una factura completa en una sola transacción:
     * cabecera + detalle + descuento de stock (solo productos físicos) + cobro si es contado.
     *
     * $d = [fecha, numero, cliente_id, condicion, metodo_pago, fecha_pago, observacion,
     *       items => [[producto_id, descripcion, cantidad, precio_unitario], ...]]
     *
     * @throws \DomainException con un mensaje para mostrar al usuario
     */
    public static function crear(int $empresaId, int $usuarioId, array $d): int
    {
        $errores = self::validar($d);
        if ($errores) {
            throw new \DomainException(implode(' ', $errores));
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $empresa = Empresa::buscar($empresaId);
            $timbrado = Timbrado::vigente($empresaId, $d['fecha']);
            if ($timbrado === null) {
                throw new \DomainException('No hay un timbrado activo y vigente para la fecha ' . fecha($d['fecha']) . '.');
            }
            $numero = (int) $d['numero'];
            if ($numero < $timbrado['numero_desde'] || $numero > $timbrado['numero_hasta']) {
                throw new \DomainException("El número $numero está fuera del rango del timbrado ({$timbrado['numero_desde']} a {$timbrado['numero_hasta']}).");
            }
            if ($usada = self::numeroUsado($empresaId, Timbrado::numeroCompleto($timbrado, $numero))) {
                throw new \DomainException(self::mensajeNumeroUsado($usada));
            }

            $cliente = self::uno('SELECT * FROM cliente WHERE id = ?', [$d['cliente_id']]);
            if ($cliente === null) {
                throw new \DomainException('Seleccioná un cliente.');
            }

            // Calcular líneas con los datos del producto (tipo e IVA salen de la base, no del navegador)
            $lineas = [];
            $totales = ['exenta' => 0, 'g5' => 0, 'g10' => 0];
            foreach ($d['items'] as $item) {
                $producto = self::uno(
                    'SELECT * FROM producto WHERE id = ? AND empresa_id = ? AND activo FOR UPDATE',
                    [$item['producto_id'], $empresaId]
                );
                if ($producto === null) {
                    throw new \DomainException('Uno de los productos ya no existe o está inactivo.');
                }
                $cantidad = (float) $item['cantidad'];
                $precio = (int) $item['precio_unitario'];
                $subtotal = (int) round($cantidad * $precio);

                if ($producto['tipo'] === Producto::PRODUCTO && !$empresa['permite_stock_negativo']
                    && $cantidad > (float) $producto['stock_actual'] - self::cantidadYaUsada($lineas, (int) $producto['id'])) {
                    throw new \DomainException('Stock insuficiente de "' . $producto['descripcion'] . '" (disponible: ' . cantidad($producto['stock_actual']) . ').');
                }

                $iva = (int) $producto['iva'];
                $totales[$iva === 10 ? 'g10' : ($iva === 5 ? 'g5' : 'exenta')] += $subtotal;
                $lineas[] = [
                    'producto'        => $producto,
                    'descripcion'     => mb_substr($item['descripcion'] !== '' ? $item['descripcion'] : $producto['descripcion'], 0, self::largoDescripcion()),
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precio,
                    'iva'             => $iva,
                    'subtotal'        => $subtotal,
                ];
            }

            $total = $totales['exenta'] + $totales['g5'] + $totales['g10'];
            $iva5 = (int) round($totales['g5'] / 21);
            $iva10 = (int) round($totales['g10'] / 11);
            $esContado = $d['condicion'] === self::CONTADO;

            $facturaId = (int) self::valor(
                'INSERT INTO factura (empresa_id, timbrado_id, numero, numero_completo, fecha, cliente_id, ruc_cliente,
                    razon_social_cliente, direccion_cliente, telefono_cliente, condicion, metodo_pago, fecha_pago,
                    total_exenta, total_gravada5, total_gravada10, total_iva5, total_iva10, total, monto_letras,
                    observacion, usuario_id)
                 VALUES (:emp, :tim, :num, :numc, :fecha, :cli, :ruc, :razon, :dir, :tel, :cond, :metodo, :fpago,
                    :exenta, :g5, :g10, :iva5, :iva10, :total, :letras, :obs, :usu)
                 RETURNING id',
                [
                    'emp'    => $empresaId,
                    'tim'    => $timbrado['id'],
                    'num'    => $numero,
                    'numc'   => Timbrado::numeroCompleto($timbrado, $numero),
                    'fecha'  => $d['fecha'],
                    'cli'    => $cliente['id'],
                    'ruc'    => $cliente['ruc'],
                    'razon'  => $cliente['razon_social'],
                    'dir'    => $cliente['direccion'],
                    'tel'    => $cliente['telefono'] ?: $cliente['celular'],
                    'cond'   => $d['condicion'],
                    'metodo' => $esContado ? $d['metodo_pago'] : null,
                    'fpago'  => $esContado ? null : $d['fecha_pago'],
                    'exenta' => $totales['exenta'],
                    'g5'     => $totales['g5'],
                    'g10'    => $totales['g10'],
                    'iva5'   => $iva5,
                    'iva10'  => $iva10,
                    'total'  => $total,
                    'letras' => NumeroALetras::convertir($total),
                    'obs'    => $d['observacion'] ?: null,
                    'usu'    => $usuarioId,
                ]
            );

            foreach ($lineas as $l) {
                self::ejecutar(
                    'INSERT INTO factura_detalle (factura_id, producto_id, descripcion, cantidad, precio_unitario, iva, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$facturaId, $l['producto']['id'], $l['descripcion'], $l['cantidad'], $l['precio_unitario'], $l['iva'], $l['subtotal']]
                );
                if ($l['producto']['tipo'] === Producto::PRODUCTO) {
                    Producto::moverStock((int) $l['producto']['id'], -$l['cantidad'], 'SALIDA_VENTA', $facturaId, $usuarioId);
                }
            }

            if ($esContado && $total > 0) {
                self::ejecutar(
                    'INSERT INTO cobro (factura_id, fecha, monto, metodo_pago, referencia, usuario_id) VALUES (?, ?, ?, ?, ?, ?)',
                    [$facturaId, $d['fecha'], $total, $d['metodo_pago'], ($d['referencia'] ?? '') !== '' ? $d['referencia'] : null, $usuarioId]
                );
            }

            $db->commit();
            return $facturaId;
        } catch (PDOException $e) {
            $db->rollBack();
            if ($e->getCode() === '23505') {
                throw new \DomainException('El número de factura ' . $d['numero'] . ' ya fue utilizado. Cada número se usa una sola vez.');
            }
            throw $e;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function validar(array $d): array
    {
        $errores = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['fecha'])) {
            $errores[] = 'La fecha no es válida.';
        }
        if ((int) $d['numero'] <= 0) {
            $errores[] = 'Ingresá el número de la hoja de factura.';
        }
        if (!in_array($d['condicion'], [self::CONTADO, self::CONTADO_FECHA], true)) {
            $errores[] = 'Elegí la condición de venta.';
        }
        if ($d['condicion'] === self::CONTADO && !in_array($d['metodo_pago'], ['EFECTIVO', 'TRANSFERENCIA'], true)) {
            $errores[] = 'Elegí efectivo o transferencia.';
        }
        if ($d['condicion'] === self::CONTADO_FECHA) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['fecha_pago'])) {
                $errores[] = 'Indicá la fecha de pago.';
            } elseif ($d['fecha_pago'] < $d['fecha']) {
                $errores[] = 'La fecha de pago no puede ser anterior a la fecha de la factura.';
            }
        }
        if (empty($d['items'])) {
            $errores[] = 'Agregá al menos un producto o servicio.';
        }
        foreach ($d['items'] as $item) {
            if ((float) $item['cantidad'] <= 0 || (int) $item['precio_unitario'] < 0) {
                $errores[] = 'Revisá cantidades y precios.';
                break;
            }
        }
        return $errores;
    }

    private static function cantidadYaUsada(array $lineas, int $productoId): float
    {
        $usado = 0.0;
        foreach ($lineas as $l) {
            if ((int) $l['producto']['id'] === $productoId) {
                $usado += $l['cantidad'];
            }
        }
        return $usado;
    }

    /** Anula una factura emitida: la marca, devuelve el stock y queda fuera del seguimiento. */
    /** @param int[] $empresas empresas en las que el usuario puede anular */
    public static function anular(int $facturaId, array $empresas, int $usuarioId, string $motivo): void
    {
        $db = self::db();
        $db->beginTransaction();
        try {
            $factura = self::uno('SELECT * FROM factura WHERE id = ? FOR UPDATE', [$facturaId]);
            if ($factura !== null && !in_array((int) $factura['empresa_id'], array_map('intval', $empresas), true)) {
                $factura = null;
            }
            if ($factura === null || $factura['estado'] !== 'EMITIDA') {
                throw new \DomainException('La factura no existe o ya está anulada.');
            }
            self::ejecutar(
                "UPDATE factura SET estado = 'ANULADA', motivo_anulacion = ?, anulado_en = now() WHERE id = ?",
                [$motivo, $facturaId]
            );
            $detalle = self::todos(
                "SELECT d.producto_id, d.cantidad FROM factura_detalle d JOIN producto p ON p.id = d.producto_id
                 WHERE d.factura_id = ? AND p.tipo = 'P'",
                [$facturaId]
            );
            foreach ($detalle as $l) {
                Producto::moverStock((int) $l['producto_id'], (float) $l['cantidad'], 'ANULACION', $facturaId, $usuarioId, $motivo);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Registra una hoja preimpresa que se arruinó, para no dejar huecos en la numeración. */
    /** Factura (emitida o anulada) que ya usa ese número completo en la empresa, o null si está libre. */
    public static function numeroUsado(int $empresaId, string $numeroCompleto): ?array
    {
        return self::uno(
            "SELECT id, numero_completo, fecha, estado, razon_social_cliente FROM factura
             WHERE empresa_id = ? AND numero_completo = ? LIMIT 1",
            [$empresaId, $numeroCompleto]
        );
    }

    public static function mensajeNumeroUsado(array $f): string
    {
        $detalle = $f['estado'] === 'ANULADA' ? 'registrada como anulada' : 'emitida a ' . ($f['razon_social_cliente'] ?: 'otro cliente');
        return 'La factura N.º ' . $f['numero_completo'] . ' ya existe (' . $detalle . ' el ' . fecha($f['fecha']) . '). '
            . 'Cada número se usa una sola vez: revisá la hoja que está en la impresora.';
    }

    public static function registrarHojaAnulada(int $empresaId, int $usuarioId, string $fechaHoja, int $numero, string $motivo): void
    {
        $timbrado = Timbrado::vigente($empresaId, $fechaHoja);
        if ($timbrado === null) {
            throw new \DomainException('No hay un timbrado vigente para esa fecha.');
        }
        if ($numero < $timbrado['numero_desde'] || $numero > $timbrado['numero_hasta']) {
            throw new \DomainException('El número está fuera del rango del timbrado.');
        }
        if ($usada = self::numeroUsado($empresaId, Timbrado::numeroCompleto($timbrado, $numero))) {
            throw new \DomainException(self::mensajeNumeroUsado($usada));
        }
        try {
            self::ejecutar(
                "INSERT INTO factura (empresa_id, timbrado_id, numero, numero_completo, fecha, condicion, estado,
                    motivo_anulacion, anulado_en, monto_letras, usuario_id)
                 VALUES (?, ?, ?, ?, ?, 'CONTADO', 'ANULADA', ?, now(), '', ?)",
                [$empresaId, $timbrado['id'], $numero, Timbrado::numeroCompleto($timbrado, $numero), $fechaHoja, $motivo, $usuarioId]
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new \DomainException("El número $numero ya está registrado.");
            }
            throw $e;
        }
    }

    public static function resumenMes(int $empresaId): array
    {
        return self::uno(
            "SELECT COUNT(*) FILTER (WHERE estado = 'EMITIDA') AS cantidad,
                    COALESCE(SUM(total) FILTER (WHERE estado = 'EMITIDA'), 0) AS monto,
                    COUNT(*) FILTER (WHERE estado = 'ANULADA') AS anuladas
             FROM factura WHERE empresa_id = ? AND fecha >= date_trunc('month', current_date)",
            [$empresaId]
        );
    }

    public static function ultimas(int $empresaId, int $limite = 8): array
    {
        return self::todos(
            'SELECT id, numero_completo, fecha, razon_social_cliente, total, estado, condicion FROM factura
             WHERE empresa_id = ? ORDER BY id DESC LIMIT ' . $limite,
            [$empresaId]
        );
    }
}
