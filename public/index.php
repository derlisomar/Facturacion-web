<?php
// Controlador frontal: todas las peticiones entran por acá.
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/bootstrap.php';
$router = require BASE_PATH . '/app/rutas.php';

$router->despachar($_SERVER['REQUEST_METHOD'], $_GET['r'] ?? '');
