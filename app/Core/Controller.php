<?php
namespace App\Core;

abstract class Controller
{
    protected function vista(string $vista, array $datos = [], ?string $layout = 'layout'): void
    {
        View::render($vista, $datos, $layout);
    }

    protected function redirigir(string $ruta, array $params = []): void
    {
        header('Location: ' . url($ruta, $params));
        exit;
    }

    protected function json($datos, int $estado = 200): void
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requiereLogin(): void
    {
        if (!Auth::check()) {
            $this->redirigir('login');
        }
    }

    /** Exige sesión y una empresa activa; devuelve el id de la empresa. */
    protected function requiereEmpresa(): int
    {
        $this->requiereLogin();
        $empresaId = Auth::empresaId();
        if ($empresaId === null) {
            $this->redirigir('empresas/seleccionar');
        }
        return $empresaId;
    }

    protected function requiereRol(string ...$roles): void
    {
        $this->requiereLogin();
        if (!in_array(Auth::rol(), $roles, true)) {
            http_response_code(403);
            $this->vista('errores/403', ['titulo' => 'Sin permiso']);
            exit;
        }
    }

    protected function verificarCsrf(): void
    {
        if (!Csrf::valido($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('La sesión del formulario expiró. Volvé atrás y recargá la página.');
        }
    }

    protected function entrada(string $clave, $defecto = null)
    {
        $valor = $_POST[$clave] ?? $_GET[$clave] ?? $defecto;
        return is_string($valor) ? trim($valor) : $valor;
    }
}
