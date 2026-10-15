<?php
// Copiá este archivo como config.php y completá los datos de tu instalación.
// config.php NO debe subirse a ningún repositorio ni quedar dentro de public/.
return [
    'app' => [
        'nombre'      => 'Sistema de Facturación',
        'url'         => '',          // vacío = se detecta solo. Ej: 'https://midominio.com/facturacion'
        'pretty_urls' => true,        // false si el servidor no tiene mod_rewrite (usa index.php?r=...)
        'timezone'    => 'America/Asuncion',
        'debug'       => false,       // true solo en desarrollo: muestra los errores en pantalla
    ],
    'db' => [
        'host'    => 'localhost',
        'port'    => 5432,
        'nombre'  => 'facturacion',
        'usuario' => 'postgres',
        'clave'   => '',
        'sslmode' => '',            // en la nube (Neon, Supabase, Render, etc.) poné 'require'
    ],
];
