<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Empresa;
use App\Models\Usuario;

class UsuariosController extends Controller
{
    public function index(): void
    {
        $this->requiereRol('ADMIN');
        $this->vista('usuarios/index', ['titulo' => 'Usuarios', 'usuarios' => Usuario::listar()]);
    }

    public function formulario(): void
    {
        $this->requiereRol('ADMIN');
        $id = (int) $this->entrada('id', 0);
        $usuario = $id ? Usuario::buscar($id) : null;
        $this->vista('usuarios/form', [
            'titulo'      => $usuario ? 'Editar usuario' : 'Nuevo usuario',
            'usuario'     => $usuario ?? ['id' => null, 'nombre' => '', 'usuario' => '', 'email' => '', 'cedula' => '',
                                          'rol' => 'FACTURADOR', 'activo' => true],
            'asignadas'   => $id ? Usuario::idsEmpresas($id) : [],
            'empresas'    => Empresa::listar(),
        ]);
    }

    public function guardar(): void
    {
        $this->requiereRol('ADMIN');
        $this->verificarCsrf();
        $id = (int) $this->entrada('id', 0) ?: null;
        $rol = (string) $this->entrada('rol', 'FACTURADOR');
        $datos = [
            'nombre'   => (string) $this->entrada('nombre', ''),
            'usuario'  => (string) $this->entrada('usuario', ''),
            'email'    => (string) $this->entrada('email', ''),
            'cedula'   => (string) $this->entrada('cedula', ''),
            'rol'      => in_array($rol, ['ADMIN', 'FACTURADOR', 'CONSULTA'], true) ? $rol : 'FACTURADOR',
            'activo'   => $id === null || !empty($_POST['activo']),
            'clave'    => (string) ($_POST['clave'] ?? ''),
            'empresas' => array_map('intval', (array) ($_POST['empresas'] ?? [])),
        ];
        $error = null;
        if ($datos['nombre'] === '' || $datos['usuario'] === '') {
            $error = 'Nombre y usuario son obligatorios.';
        } elseif (Usuario::existeUsuario($datos['usuario'], $id)) {
            $error = 'Ese nombre de usuario ya existe.';
        } elseif ($id === null && strlen($datos['clave']) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($id !== null && $datos['clave'] !== '' && strlen($datos['clave']) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        }
        if ($error) {
            flash('error', $error);
            $this->redirigir($id ? 'usuarios/editar' : 'usuarios/nuevo', $id ? ['id' => $id] : []);
        }
        Usuario::guardar($datos, $id);
        flash('ok', 'Usuario guardado. Los cambios de empresas se aplican en su próximo ingreso.');
        $this->redirigir('usuarios');
    }
}
