<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::check()) {
            $this->redirigir('inicio');
        }
        $this->vista('auth/login', ['error' => null, 'login' => ''], null);
    }

    public function ingresar(): void
    {
        $this->verificarCsrf();
        $login = (string) $this->entrada('login', '');
        $clave = (string) ($_POST['clave'] ?? '');

        if ($login !== '' && $clave !== '' && Auth::intentar($login, $clave)) {
            $this->redirigir(Auth::empresaId() ? 'inicio' : 'empresas/seleccionar');
        }
        usleep(500000); // frena intentos repetidos
        $this->vista('auth/login', ['error' => 'Usuario o contraseña incorrectos.', 'login' => $login], null);
    }

    public function salir(): void
    {
        $this->verificarCsrf();
        Auth::salir();
        $this->redirigir('login');
    }
}
