<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cobro;

class SeguimientoController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->requiereEmpresa();
        $filtro = (string) $this->entrada('filtro', 'todas');
        $busqueda = (string) $this->entrada('q', '');
        $this->vista('seguimiento/index', [
            'titulo'   => 'Seguimiento de cobros',
            'facturas' => Cobro::pendientes($empresaId, $filtro, $busqueda),
            'resumen'  => Cobro::resumenPendiente($empresaId),
            'filtro'   => $filtro,
            'busqueda' => $busqueda,
        ]);
    }

    public function cobrar(): void
    {
        $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        $fecha = (string) $this->entrada('fecha', date('Y-m-d'));
        $datos = [
            'monto'       => entero($this->entrada('monto', 0)),
            'fecha'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d'),
            'metodo_pago' => $this->entrada('metodo_pago') === 'TRANSFERENCIA' ? 'TRANSFERENCIA' : 'EFECTIVO',
            'referencia'  => (string) $this->entrada('referencia', ''),
        ];
        try {
            Cobro::registrar((int) $this->entrada('factura_id', 0), Auth::empresasPermitidas(), $datos, Auth::id());
            flash('ok', 'Cobro registrado.');
        } catch (\DomainException $e) {
            flash('error', $e->getMessage());
        }
        $volver = $this->entrada('volver') === 'factura' ? ['facturas/ver', ['id' => (int) $this->entrada('factura_id')]] : ['seguimiento', []];
        $this->redirigir(...$volver);
    }
}
