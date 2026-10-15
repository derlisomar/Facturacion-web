<?php
namespace App\Core;

class Router
{
    private array $rutas = [];

    public function get(string $ruta, array $accion): void
    {
        $this->rutas['GET'][trim($ruta, '/')] = $accion;
    }

    public function post(string $ruta, array $accion): void
    {
        $this->rutas['POST'][trim($ruta, '/')] = $accion;
    }

    public function despachar(string $metodo, string $ruta): void
    {
        $ruta = trim($ruta, '/');
        if ($ruta === '') {
            $ruta = Auth::check() ? 'inicio' : 'login';
        }

        $accion = $this->rutas[$metodo][$ruta] ?? null;
        if ($accion === null) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Página no encontrada'], Auth::check() ? 'layout' : null);
            return;
        }

        [$clase, $metodoControlador] = $accion;
        try {
            (new $clase())->$metodoControlador();
        } catch (\Throwable $e) {
            error_log((string) $e);
            http_response_code(500);
            echo '<div style="font-family:Arial;padding:20px">';
            if ($e instanceof \PDOException && in_array($e->getCode(), ['42P01', '42703', '42883'], true)) {
                echo '<h2>La base de datos configurada no es la del sistema nuevo</h2>'
                   . '<p>La base que figura en <b>config/config.php</b> no tiene las tablas del sistema nuevo '
                   . '(puede ser la base vieja del Java). Creá una base vacía, ejecutá en ella '
                   . '<b>database/instalar.sql</b> y poné su nombre en config.php.</p>';
            } elseif ($e instanceof \PDOException && $e->getCode() !== '23505') {
                echo '<h2>No se pudo conectar o consultar la base de datos</h2>'
                   . '<p>Revisá en <b>config/config.php</b> el nombre de la base, el usuario y la contraseña, '
                   . 'que PostgreSQL esté encendido y que la base tenga las tablas (database/schema.sql).</p>';
            } else {
                echo '<h2>Ocurrió un error inesperado</h2>';
            }
            if (config('app.debug')) {
                // Solo mensaje y ubicación: el stack trace completo podría mostrar contraseñas
                echo '<pre style="white-space:pre-wrap">' . e(get_class($e) . ': ' . $e->getMessage()
                    . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
            }
            echo '</div>';
        }
    }
}
