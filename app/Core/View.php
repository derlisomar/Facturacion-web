<?php
namespace App\Core;

class View
{
    /** Renderiza app/Views/{vista}.php dentro del layout (o sin layout si es null). */
    public static function render(string $vista, array $datos = [], ?string $layout = 'layout'): void
    {
        $contenido = self::capturar($vista, $datos);
        if ($layout === null) {
            echo $contenido;
            return;
        }
        echo self::capturar($layout, $datos + ['contenido' => $contenido]);
    }

    private static function capturar(string $vista, array $datos): string
    {
        $archivo = BASE_PATH . '/app/Views/' . $vista . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException("Vista no encontrada: $vista");
        }
        extract($datos, EXTR_SKIP);
        ob_start();
        require $archivo;
        return ob_get_clean();
    }
}
