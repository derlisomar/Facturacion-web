<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\Timbrado;

class FacturasController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->requiereEmpresa();
        $filtros = $this->filtros();
        $pagina = max(1, (int) $this->entrada('pagina', 1));
        $resultado = Factura::listar($this->empresasDelFiltro($filtros, $empresaId), $filtros, $pagina);
        $this->vista('facturas/index', [
            'titulo'        => 'Consulta de facturas',
            'facturas'      => $resultado['filas'],
            'detalles'      => Factura::detallesDe(array_column($resultado['filas'], 'id')),
            'total'         => $resultado['total'],
            'monto'         => $resultado['monto'],
            'iva'           => $resultado['iva'],
            'pagina'        => $pagina,
            'filtros'       => $filtros,
            'variasEmpresas'=> count(Auth::empresasPermitidas()) > 1,
        ]);
    }

    /** Listado completo con los filtros actuales, en formato para imprimir (o guardar como PDF). */
    public function listado(): void
    {
        $empresaId = $this->requiereEmpresa();
        $filtros = $this->filtros();
        $resultado = Factura::listar($this->empresasDelFiltro($filtros, $empresaId), $filtros, 1, 0);
        $this->vista('facturas/listado', [
            'facturas' => $resultado['filas'],
            'total'    => $resultado['total'],
            'monto'    => $resultado['monto'],
            'iva'      => $resultado['iva'],
            'filtros'  => $filtros,
            'empresa'  => Auth::empresa(),
        ], null);
    }

    /** Exporta el listado a CSV, que Excel abre directamente. */
    public function exportar(): void
    {
        $empresaId = $this->requiereEmpresa();
        $filtros = $this->filtros();
        $resultado = Factura::listar($this->empresasDelFiltro($filtros, $empresaId), $filtros, 1, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="facturas_' . $filtros['desde'] . '_' . $filtros['hasta'] . '.csv"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
        fputcsv($salida, ['Empresa', 'Número', 'Fecha', 'RUC', 'Razón social', 'Condición', 'Método', 'Fecha de pago',
            'Exenta', 'Gravada 5%', 'Gravada 10%', 'IVA 5%', 'IVA 10%', 'Total', 'Saldo', 'Estado', 'Observación'], ';');
        foreach ($resultado['filas'] as $f) {
            fputcsv($salida, [
                $f['empresa_nombre'], $f['numero_completo'], fecha($f['fecha']), $f['ruc_cliente'], $f['razon_social_cliente'],
                $f['condicion'] === 'CONTADO' ? 'Contado' : 'Con fecha de pago', $f['metodo_pago'], fecha($f['fecha_pago']),
                $f['total_exenta'], $f['total_gravada5'], $f['total_gravada10'], $f['total_iva5'], $f['total_iva10'],
                $f['total'], $f['estado'] === 'EMITIDA' ? $f['saldo'] : 0, $f['estado'], $f['estado'] === 'ANULADA' ? $f['motivo_anulacion'] : $f['observacion'],
            ], ';');
        }
        fclose($salida);
        exit;
    }

    private function filtros(): array
    {
        $empresa = (string) $this->entrada('empresa', 'actual');
        return [
            'desde'     => $this->fechaValida($this->entrada('desde'), date('Y-m-01')),
            'hasta'     => $this->fechaValida($this->entrada('hasta'), date('Y-m-t')),
            'busqueda'  => (string) $this->entrada('q', ''),
            'condicion' => (string) $this->entrada('condicion', ''),
            'metodo'    => (string) $this->entrada('metodo', ''),
            'estado'    => (string) $this->entrada('estado', 'EMITIDA'),
            'empresa'   => $empresa === 'todas' ? 'todas' : 'actual',
        ];
    }

    private function empresasDelFiltro(array $filtros, int $empresaId): array
    {
        return $filtros['empresa'] === 'todas' ? Auth::empresasPermitidas() : [$empresaId];
    }

    public function nueva(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $timbrado = Timbrado::vigente($empresaId, date('Y-m-d'));
        $this->vista('facturas/nueva', [
            'titulo'          => 'Nueva factura',
            'timbrado'        => $timbrado,
            'numeroSugerido'  => $timbrado ? Timbrado::siguienteNumero($timbrado) : null,
            'empresa'         => Empresa::buscar($empresaId),
            'catalogo'        => Producto::paraVenta($empresaId),
        ]);
    }

    /** JSON: si el número de hoja ya fue usado en la empresa (para avisar mientras se escribe). */
    public function verificarNumero(): void
    {
        $empresaId = $this->requiereEmpresa();
        $timbrado = Timbrado::vigente($empresaId, date('Y-m-d'));
        $numero = (int) $this->entrada('numero', 0);
        if (!$timbrado || $numero <= 0) {
            $this->json(['usado' => null]);
        }
        $usada = Factura::numeroUsado($empresaId, Timbrado::numeroCompleto($timbrado, $numero));
        $this->json([
            'completo' => Timbrado::numeroCompleto($timbrado, $numero),
            'usado'    => $usada ? Factura::mensajeNumeroUsado($usada) : null,
            'fuera'    => $numero < $timbrado['numero_desde'] || $numero > $timbrado['numero_hasta'],
        ]);
    }

    /** Recibe la factura completa en JSON desde la pantalla de facturación. */
    public function guardar(): void
    {
        $empresaId = $this->requiereEmpresa();
        if (!Auth::puedeFacturar()) {
            $this->json(['error' => 'Sin permiso para facturar.'], 403);
        }
        $this->verificarCsrf();
        $entrada = json_decode((string) ($_POST['factura'] ?? ''), true);
        if (!is_array($entrada)) {
            $this->json(['error' => 'Datos de factura inválidos.'], 422);
        }

        $items = [];
        foreach ((array) ($entrada['items'] ?? []) as $item) {
            $items[] = [
                'producto_id'     => (int) ($item['producto_id'] ?? 0),
                'descripcion'     => trim((string) ($item['descripcion'] ?? '')),
                'cantidad'        => (float) ($item['cantidad'] ?? 0),
                'precio_unitario' => (int) ($item['precio_unitario'] ?? 0),
            ];
        }
        $datos = [
            'fecha'       => (string) ($entrada['fecha'] ?? ''),
            'numero'      => (int) ($entrada['numero'] ?? 0),
            'cliente_id'  => (int) ($entrada['cliente_id'] ?? 0),
            'condicion'   => (string) ($entrada['condicion'] ?? ''),
            'metodo_pago' => (string) ($entrada['metodo_pago'] ?? ''),
            'fecha_pago'  => (string) ($entrada['fecha_pago'] ?? ''),
            'referencia'  => mb_substr(trim((string) ($entrada['referencia'] ?? '')), 0, 100),
            'observacion' => trim((string) ($entrada['observacion'] ?? '')),
            'items'       => $items,
        ];

        try {
            $id = Factura::crear($empresaId, Auth::id(), $datos);
        } catch (\DomainException $e) {
            $this->json(['error' => $e->getMessage()], 422);
        }
        flash('ok', 'Factura guardada y enviada a la impresora.');
        $this->json(['id' => $id, 'imprimir' => url('facturas/imprimir', ['id' => $id]), 'ver' => url('facturas/ver', ['id' => $id])]);
    }

    public function ver(): void
    {
        $this->requiereEmpresa();
        $factura = Factura::buscar((int) $this->entrada('id', 0), Auth::empresasPermitidas());
        if ($factura === null) {
            flash('error', 'Factura no encontrada.');
            $this->redirigir('facturas');
        }
        $this->vista('facturas/ver', [
            'titulo'  => 'Factura ' . $factura['numero_completo'],
            'factura' => $factura,
            'detalle' => Factura::detalle((int) $factura['id']),
            'cobros'  => Cobro::deFactura((int) $factura['id']),
        ]);
    }

    public function imprimir(): void
    {
        $this->requiereEmpresa();
        $factura = Factura::buscar((int) $this->entrada('id', 0), Auth::empresasPermitidas());
        if ($factura === null || $factura['estado'] !== 'EMITIDA') {
            flash('error', 'Solo se pueden imprimir facturas emitidas.');
            $this->redirigir('facturas');
        }
        $empresa = Empresa::buscar((int) $factura['empresa_id']); // el formato es el de la empresa de la factura
        $this->vista('facturas/imprimir', [
            'factura' => $factura,
            'detalle' => Factura::detalle((int) $factura['id']),
            'formato' => Empresa::formato($empresa),
            'prueba'  => false,
            'embebido'=> (bool) $this->entrada('embebido'),  // se imprime desde un marco oculto de la misma pantalla
        ], null);
    }

    /** Imprime datos de ejemplo para calibrar la posición sobre la hoja preimpresa. */
    public function pruebaImpresion(): void
    {
        $this->requiereRol('ADMIN');
        $empresaId = $this->requiereEmpresa();
        $empresa = Empresa::buscar($empresaId);
        $factura = [
            'fecha' => date('Y-m-d'), 'razon_social_cliente' => 'CLIENTE DE PRUEBA S.A.', 'ruc_cliente' => '80000000-0',
            'direccion_cliente' => 'AVDA. EJEMPLO 1234', 'telefono_cliente' => '0981 000000', 'condicion' => 'CONTADO',
            'fecha_pago' => null, 'total_exenta' => 10000, 'total_gravada5' => 21000, 'total_gravada10' => 110000,
            'total_iva5' => 1000, 'total_iva10' => 10000, 'total' => 141000,
            'monto_letras' => 'CIENTO CUARENTA Y UN MIL GUARANÍES', 'observacion' => 'PEDIDO POR: JUAN PÉREZ',
        ];
        $detalle = [
            ['cantidad' => 2, 'descripcion' => 'PRODUCTO GRAVADO 10%', 'precio_unitario' => 55000, 'iva' => 10, 'subtotal' => 110000],
            ['cantidad' => 1, 'descripcion' => 'PRODUCTO GRAVADO 5%', 'precio_unitario' => 21000, 'iva' => 5, 'subtotal' => 21000],
            ['cantidad' => 1, 'descripcion' => 'SERVICIO EXENTO', 'precio_unitario' => 10000, 'iva' => 0, 'subtotal' => 10000],
        ];
        $this->vista('facturas/imprimir', [
            'factura' => $factura, 'detalle' => $detalle, 'formato' => Empresa::formato($empresa), 'prueba' => true,
            'fondo'   => $this->entrada('fondo') ? Empresa::fondo($empresaId) : null,
        ], null);
    }

    public function anular(): void
    {
        $this->requiereRol('ADMIN');
        $this->requiereEmpresa();
        $this->verificarCsrf();
        $id = (int) $this->entrada('id', 0);
        $motivo = (string) $this->entrada('motivo', '');
        if ($motivo === '') {
            flash('error', 'Indicá el motivo de la anulación.');
        } else {
            try {
                Factura::anular($id, Auth::empresasPermitidas(), Auth::id(), $motivo);
                flash('ok', 'Factura anulada. El stock de los productos fue devuelto.');
            } catch (\DomainException $e) {
                flash('error', $e->getMessage());
            }
        }
        if ($this->entrada('volver') === 'consulta') {
            // vuelve a la consulta con los mismos filtros
            parse_str((string) $this->entrada('filtros', ''), $params);
            unset($params['r']);
            $this->redirigir('facturas', $params);
        }
        $this->redirigir('facturas/ver', ['id' => $id]);
    }

    /** Registra una hoja preimpresa que se arruinó en la impresora. */
    public function hojaAnulada(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        try {
            Factura::registrarHojaAnulada(
                $empresaId,
                Auth::id(),
                $this->fechaValida($this->entrada('fecha'), date('Y-m-d')),
                entero($this->entrada('numero', 0)),
                (string) $this->entrada('motivo', 'Hoja dañada en impresión') ?: 'Hoja dañada en impresión'
            );
            flash('ok', 'Número registrado como anulado. Seguí con la hoja siguiente.');
        } catch (\DomainException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirigir('facturas/nueva');
    }

    private function fechaValida($valor, string $defecto): string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : $defecto;
    }
}
