<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\Reporte;

class DashboardController extends Controller
{
    public function index(): void
    {
        $empresaId = $this->requiereEmpresa();
        $this->vista('dashboard/index', [
            'titulo'     => 'Inicio',
            'mes'        => Factura::resumenMes($empresaId),
            'pendiente'  => Cobro::resumenPendiente($empresaId),
            'productos'  => Producto::contar($empresaId),
            'clientes'   => Cliente::contar(),
            'ultimas'    => Factura::ultimas($empresaId),
            'mejores'    => Reporte::mejoresClientes($empresaId),
            'clientesMes'=> Reporte::clientesDelMes($empresaId),
            'panel'      => Reporte::panel($empresaId),
            'dias'       => Reporte::diasDelMes($empresaId),
            'meses'      => Reporte::ultimosMeses($empresaId),
            'modalidades'=> Reporte::modalidadesDelMes($empresaId),
            'cobros'     => Reporte::cobros($empresaId),
            'cobrosDias' => Reporte::cobrosPorDia($empresaId),
            'metodos'    => Reporte::cobradoPorMetodo($empresaId, date('Y-m-01'), date('Y-m-d')),
        ]);
    }
}
