<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cliente;

class ClientesController extends Controller
{
    public function index(): void
    {
        $this->requiereEmpresa();
        $busqueda = (string) $this->entrada('q', '');
        $pagina = max(1, (int) $this->entrada('pagina', 1));
        $resultado = Cliente::listar($busqueda, $pagina);
        $this->vista('clientes/index', [
            'titulo'   => 'Clientes',
            'clientes' => $resultado['filas'],
            'total'    => $resultado['total'],
            'pagina'   => $pagina,
            'busqueda' => $busqueda,
        ]);
    }

    public function formulario(): void
    {
        $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $id = (int) $this->entrada('id', 0);
        $cliente = $id ? Cliente::buscar($id) : null;
        $this->vista('clientes/form', [
            'titulo'  => $cliente ? 'Editar cliente' : 'Nuevo cliente',
            'cliente' => $cliente ?? ['id' => null, 'ruc' => (string) $this->entrada('ruc', ''),
                                      'razon_social' => mb_strtoupper((string) $this->entrada('nombre', '')), 'direccion' => '', 'telefono' => '',
                                      'celular' => '', 'email' => '', 'activo' => true],
        ]);
    }

    public function guardar(): void
    {
        $this->requiereEmpresa();
        $this->requiereRol('ADMIN', 'FACTURADOR');
        $this->verificarCsrf();
        $id = (int) $this->entrada('id', 0) ?: null;
        [$datos, $error] = $this->datos($id);
        if ($error) {
            flash('error', $error);
            $this->redirigir($id ? 'clientes/editar' : 'clientes/nuevo', $id ? ['id' => $id] : []);
        }
        Cliente::guardar($datos, $id);
        flash('ok', 'Cliente guardado.');
        $this->redirigir('clientes');
    }

    /** JSON para el buscador de la pantalla de facturación. */
    public function buscar(): void
    {
        $this->requiereEmpresa();
        $this->json(Cliente::autocompletar((string) $this->entrada('q', '')));
    }

    /** Alta rápida desde la pantalla de facturación (JSON). */
    public function crearRapido(): void
    {
        $this->requiereEmpresa();
        if (!Auth::puedeFacturar()) {
            $this->json(['error' => 'Sin permiso.'], 403);
        }
        $this->verificarCsrf();
        [$datos, $error] = $this->datos(null);
        if ($error) {
            $this->json(['error' => $error], 422);
        }
        $id = Cliente::guardar($datos);
        $this->json(Cliente::buscar($id));
    }

    private function datos(?int $id): array
    {
        $datos = [
            'ruc'          => strtoupper((string) $this->entrada('ruc', '')),
            'razon_social' => mb_strtoupper((string) $this->entrada('razon_social', '')),
            'direccion'    => (string) $this->entrada('direccion', ''),
            'telefono'     => (string) $this->entrada('telefono', ''),
            'celular'      => (string) $this->entrada('celular', ''),
            'email'        => (string) $this->entrada('email', ''),
            'activo'       => $id === null || !empty($_POST['activo']),
        ];
        if ($datos['ruc'] === '' || $datos['razon_social'] === '') {
            return [$datos, 'RUC/CI y razón social son obligatorios.'];
        }
        if (Cliente::existeRuc($datos['ruc'], $id)) {
            return [$datos, 'Ya existe un cliente con el RUC ' . $datos['ruc'] . '.'];
        }
        return [$datos, null];
    }
}
