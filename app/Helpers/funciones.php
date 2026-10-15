<?php
use App\Core\Config;
use App\Core\Csrf;

function config(string $clave, $defecto = null)
{
    return Config::get($clave, $defecto);
}

/** Escapa texto para mostrarlo en HTML. Usalo SIEMPRE en las vistas. */
function e($valor): string
{
    $texto = (string) $valor;
    if (!mb_check_encoding($texto, 'UTF-8')) {
        // PostgreSQL en Windows devuelve sus mensajes en Windows-1252; sin esto el texto queda vacío
        $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url_base(): string
{
    $base = (string) config('app.url', '');
    if ($base === '') {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return rtrim($base, '/');
}

function url(string $ruta = '', array $params = []): string
{
    $ruta = trim($ruta, '/');
    if (config('app.pretty_urls', true)) {
        $destino = url_base() . '/' . $ruta;
        return $params ? $destino . '?' . http_build_query($params) : $destino;
    }
    return url_base() . '/index.php?' . http_build_query(['r' => $ruta] + $params);
}

function asset(string $ruta): string
{
    $archivo = BASE_PATH . '/public/assets/' . ltrim($ruta, '/');
    $version = is_file($archivo) ? filemtime($archivo) : 0;
    return url_base() . '/assets/' . ltrim($ruta, '/') . '?v=' . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Formato guaraníes: 1.250.000 */
function gs($monto): string
{
    return number_format((float) $monto, 0, ',', '.');
}

function cantidad($valor): string
{
    $valor = (float) $valor;
    return floor($valor) == $valor ? number_format($valor, 0, ',', '.') : number_format($valor, 2, ',', '.');
}

function fecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '';
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flashes(): array
{
    $mensajes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $mensajes;
}

/** Convierte "1.250.000" o "1250000" en entero. */
function entero($valor): int
{
    return (int) preg_replace('/[^\d-]/', '', (string) $valor);
}

/** Convierte "2,5" o "2.5" en número decimal. */
function decimal($valor): float
{
    $valor = str_replace(' ', '', (string) $valor);
    if (strpos($valor, ',') !== false) {
        $valor = str_replace(['.', ','], ['', '.'], $valor);
    }
    return (float) $valor;
}
