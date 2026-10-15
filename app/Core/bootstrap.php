<?php
declare(strict_types=1);

spl_autoload_register(function (string $clase): void {
    if (strncmp($clase, 'App\\', 4) !== 0) {
        return;
    }
    $archivo = BASE_PATH . '/app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

$archivoConfig = BASE_PATH . '/config/config.php';
if (!is_file($archivoConfig)) {
    http_response_code(500);
    exit('Falta config/config.php. Copiá config/config.example.php y completá los datos de la base.');
}
App\Core\Config::cargar(require $archivoConfig);

require BASE_PATH . '/app/Helpers/funciones.php';

date_default_timezone_set(config('app.timezone', 'America/Asuncion'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Las sesiones se guardan dentro del sistema: en hosting compartido la carpeta
// por defecto de PHP puede no existir o no tener permisos, y el login falla.
$carpetaSesiones = BASE_PATH . '/storage/sesiones';
if (!is_dir($carpetaSesiones)) {
    @mkdir($carpetaSesiones, 0700, true);
}
if (is_dir($carpetaSesiones) && is_writable($carpetaSesiones)) {
    session_save_path($carpetaSesiones);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', '28800');
}

session_name('facturacion');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
