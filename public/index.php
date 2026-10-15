<?php
// Controlador frontal: todas las peticiones entran por acá.
define('BASE_PATH', dirname(__DIR__));

// Ninguna página del sistema se puede guardar en caché: si el proxy del hosting
// (nginx) la guarda, todos reciben el mismo formulario y el login falla.
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Accel-Expires: 0');

require BASE_PATH . '/app/Core/bootstrap.php';
$router = require BASE_PATH . '/app/rutas.php';

$router->despachar($_SERVER['REQUEST_METHOD'], $_GET['r'] ?? '');
