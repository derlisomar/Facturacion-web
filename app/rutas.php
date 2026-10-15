<?php
use App\Controllers\AuthController;
use App\Controllers\ClientesController;
use App\Controllers\DashboardController;
use App\Controllers\EmpresasController;
use App\Controllers\FacturasController;
use App\Controllers\PadronController;
use App\Controllers\ProductosController;
use App\Controllers\ReportesController;
use App\Controllers\SeguimientoController;
use App\Controllers\UsuariosController;
use App\Core\Router;

$r = new Router();

$r->get('login', [AuthController::class, 'formulario']);
$r->post('login', [AuthController::class, 'ingresar']);
$r->post('salir', [AuthController::class, 'salir']);

$r->get('inicio', [DashboardController::class, 'index']);

$r->get('empresas/seleccionar', [EmpresasController::class, 'seleccionar']);
$r->post('empresas/seleccionar', [EmpresasController::class, 'activar']);
$r->get('empresas/editar', [EmpresasController::class, 'editar']);
$r->post('empresas/guardar', [EmpresasController::class, 'guardar']);
$r->get('empresas/diseno', [EmpresasController::class, 'diseno']);
$r->post('empresas/diseno-guardar', [EmpresasController::class, 'guardarDiseno']);
$r->post('empresas/diseno-fondo', [EmpresasController::class, 'subirFondo']);
$r->post('empresas/diseno-fondo-borrar', [EmpresasController::class, 'borrarFondo']);
$r->post('empresas/timbrado', [EmpresasController::class, 'crearTimbrado']);
$r->post('empresas/timbrado-borrar', [EmpresasController::class, 'borrarTimbrado']);
$r->post('empresas/timbrado-estado', [EmpresasController::class, 'estadoTimbrado']);

$r->get('clientes', [ClientesController::class, 'index']);
$r->get('clientes/nuevo', [ClientesController::class, 'formulario']);
$r->get('clientes/editar', [ClientesController::class, 'formulario']);
$r->post('clientes/guardar', [ClientesController::class, 'guardar']);
$r->get('clientes/buscar', [ClientesController::class, 'buscar']);
$r->post('clientes/rapido', [ClientesController::class, 'crearRapido']);

$r->get('padron', [PadronController::class, 'index']);
$r->post('padron/importar', [PadronController::class, 'importar']);
$r->get('padron/ruc', [PadronController::class, 'ruc']);

$r->get('productos', [ProductosController::class, 'index']);
$r->get('productos/nuevo', [ProductosController::class, 'formulario']);
$r->get('productos/editar', [ProductosController::class, 'formulario']);
$r->post('productos/guardar', [ProductosController::class, 'guardar']);
$r->get('productos/buscar', [ProductosController::class, 'buscar']);
$r->post('productos/desactivar', [ProductosController::class, 'desactivar']);
$r->get('productos/stock', [ProductosController::class, 'kardex']);
$r->post('productos/stock', [ProductosController::class, 'ajustarStock']);

$r->get('facturas', [FacturasController::class, 'index']);
$r->get('facturas/listado', [FacturasController::class, 'listado']);
$r->get('facturas/exportar', [FacturasController::class, 'exportar']);
$r->get('facturas/nueva', [FacturasController::class, 'nueva']);
$r->get('facturas/verificar-numero', [FacturasController::class, 'verificarNumero']);
$r->post('facturas/guardar', [FacturasController::class, 'guardar']);
$r->get('facturas/ver', [FacturasController::class, 'ver']);
$r->get('facturas/imprimir', [FacturasController::class, 'imprimir']);
$r->get('facturas/prueba-impresion', [FacturasController::class, 'pruebaImpresion']);
$r->post('facturas/anular', [FacturasController::class, 'anular']);
$r->post('facturas/hoja-anulada', [FacturasController::class, 'hojaAnulada']);

$r->get('seguimiento', [SeguimientoController::class, 'index']);
$r->post('seguimiento/cobrar', [SeguimientoController::class, 'cobrar']);


$r->get('reportes', [ReportesController::class, 'index']);
$r->get('reportes/ver', [ReportesController::class, 'ver']);
$r->get('reportes/excel', [ReportesController::class, 'excel']);
$r->get('reportes/pdf', [ReportesController::class, 'pdf']);

$r->get('usuarios', [UsuariosController::class, 'index']);
$r->get('usuarios/nuevo', [UsuariosController::class, 'formulario']);
$r->get('usuarios/editar', [UsuariosController::class, 'formulario']);
$r->post('usuarios/guardar', [UsuariosController::class, 'guardar']);

return $r;
