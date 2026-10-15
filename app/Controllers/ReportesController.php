<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Helpers\Exportador;
use App\Models\CentroReportes;
use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Reporte;

class ReportesController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->requiereEmpresa();
        $desde = $this->fecha($this->entrada('desde'), date('Y-m-01'));
        $hasta = $this->fecha($this->entrada('hasta'), date('Y-m-t'));
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $this->vista('reportes/index', [
            'titulo'      => 'Centro de reportes',
            'grupos'      => CentroReportes::grupos(),
            'desde'       => $desde,
            'hasta'       => $hasta,
            'resumen'     => Reporte::resumen($empresaId, $desde, $hasta),
            'pendiente'   => Cobro::resumenPendiente($empresaId),
            'metodos'     => Reporte::cobradoPorMetodo($empresaId, $desde, $hasta),
            'porDia'      => Reporte::ventasPorDia($empresaId, $desde, $hasta),
            'porEmpresa'  => count(Auth::empresasPermitidas()) > 1 ? Reporte::porEmpresa(Auth::empresasPermitidas(), $desde, $hasta) : [],
            'clientes'    => Reporte::topClientes($empresaId, $desde, $hasta),
            'productos'   => Reporte::topProductos($empresaId, $desde, $hasta),
        ]);
    }

    /** Un reporte en pantalla (con las primeras filas) y los botones de Excel y PDF. */
    public function ver(): void
    {
        [$clave, $def, $filtros, $empresas] = $this->preparar();
        $this->vista('reportes/ver', [
            'titulo'   => $def['nombre'],
            'clave'    => $clave,
            'def'      => $def,
            'filtros'  => $filtros,
            'opcionesEmpresa' => $this->opcionesEmpresa(),
            'doc'      => CentroReportes::documento($clave, $empresas, $filtros, Auth::usuario()['nombre'] ?? ''),
            'max'      => 300,
        ]);
    }

    public function excel(): void
    {
        [$clave, $def, $filtros, $empresas] = $this->preparar();
        if (!class_exists(\ZipArchive::class)) {
            http_response_code(500);
            exit('Para descargar en Excel hay que activar la extensión zip de PHP: en XAMPP abrí php.ini, quitá el ; de la línea ;extension=zip y reiniciá Apache.');
        }
        $doc = CentroReportes::documento($clave, $empresas, $filtros, Auth::usuario()['nombre'] ?? '');
        $this->descargar(Exportador::xlsx($doc), $this->archivo($clave, $filtros, 'xlsx'),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function pdf(): void
    {
        [$clave, $def, $filtros, $empresas] = $this->preparar();
        $doc = CentroReportes::documento($clave, $empresas, $filtros, Auth::usuario()['nombre'] ?? '');
        // inline: el navegador lo abre y desde ahí se imprime o se guarda
        $this->descargar(Exportador::pdf($doc), $this->archivo($clave, $filtros, 'pdf'), 'application/pdf', true);
    }

    /** @return array{0: string, 1: array, 2: array, 3: array} clave, definición, filtros y empresas [id => nombre] */
    private function preparar(): array
    {
        $empresaId = $this->requiereEmpresa();
        $clave = (string) $this->entrada('clave', '');
        $def = CentroReportes::definicion($clave);
        if ($def === null) {
            http_response_code(404);
            $this->vista('errores/404', ['titulo' => 'No encontrado']);
            exit;
        }
        $desde = $this->fecha($this->entrada('desde'), date('Y-m-01'));
        $hasta = $this->fecha($this->entrada('hasta'), date('Y-m-d'));
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $estado = (string) $this->entrada('estado', 'EMITIDA');
        $filtros = [
            'desde'   => $desde,
            'hasta'   => $hasta,
            'estado'  => in_array($estado, ['EMITIDA', 'ANULADA', ''], true) ? $estado : 'EMITIDA',
            'empresa' => (string) $this->entrada('empresa', (string) $empresaId),
        ];

        $opciones = $this->opcionesEmpresa();
        if ($filtros['empresa'] === 'todas' && count($opciones) > 1) {
            $empresas = $opciones;
        } elseif (isset($opciones[(int) $filtros['empresa']])) {
            $empresas = [(int) $filtros['empresa'] => $opciones[(int) $filtros['empresa']]];
        } else {
            $filtros['empresa'] = (string) $empresaId;
            $empresas = [$empresaId => $opciones[$empresaId] ?? ''];
        }
        return [$clave, $def, $filtros, $empresas];
    }

    /** Empresas que el usuario puede ver: [id => nombre]. */
    private function opcionesEmpresa(): array
    {
        $opciones = [];
        foreach (Auth::empresasPermitidas() as $id) {
            if ($e = Empresa::buscar((int) $id)) {
                $opciones[(int) $id] = $e['nombre_fantasia'] ?: $e['razon_social'];
            }
        }
        return $opciones;
    }

    private function archivo(string $clave, array $filtros, string $extension): string
    {
        return $clave . '_' . str_replace('-', '', $filtros['desde']) . '_' . str_replace('-', '', $filtros['hasta']) . '.' . $extension;
    }

    private function descargar(string $contenido, string $nombre, string $tipo, bool $enLinea = false): void
    {
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($contenido));
        echo $contenido;
        exit;
    }

    private function fecha($valor, string $defecto): string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : $defecto;
    }
}
