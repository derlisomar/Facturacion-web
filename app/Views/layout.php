<?php
use App\Core\Auth;
$empresa = Auth::empresa();
$usuario = Auth::usuario();
$ruta = trim($_GET['r'] ?? '', '/');
$activo = fn(string $prefijo) => str_starts_with($ruta, $prefijo) ? 'activo' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo ?? '') ?> · <?= e(config('app.nombre')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <?php require __DIR__ . '/parciales/tema.php'; ?>
    <link rel="preload" href="<?= url_base() ?>/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/sweetalert2/sweetalert2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script src="<?= asset('vendor/sweetalert2/sweetalert2.min.js') ?>"></script>
    <style>:root { --empresa: <?= e($empresa['color'] ?? '#6d28d9') ?>; }</style>
    <meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
</head>
<body>
<script>try { if (localStorage.getItem('menu') === 'chico') document.body.classList.add('menu-chico'); } catch (e) {}</script>
<aside class="sidebar">
    <div class="marca"><a href="<?= url('inicio') ?>"><img class="logo-claro" src="<?= asset('img/logo-claro.png') ?>" alt="Logo"><img class="logo-oscuro" src="<?= asset('img/logo-oscuro.png') ?>" alt="Logo"><img class="logo-mini" src="<?= asset('img/logo_mini.png') ?>" alt="Logo"></a></div>
    <nav>
        <a href="<?= url('inicio') ?>" class="<?= $activo('inicio') ?>"><i class="fas fa-house"></i> <span>Inicio</span></a>
        <div class="grupo">Factura</div>
        <?php if (Auth::puedeFacturar()): ?>
        <a href="<?= url('facturas/nueva') ?>" class="<?= $activo('facturas/nueva') ?>"><i class="fas fa-file-invoice"></i> <span>Facturar</span></a>
        <?php endif; ?>
        <a href="<?= url('facturas') ?>" class="<?= $ruta === 'facturas' || str_starts_with($ruta, 'facturas/ver') ? 'activo' : '' ?>"><i class="fas fa-magnifying-glass"></i> <span>Consulta</span></a>
        <a href="<?= url('seguimiento') ?>" class="<?= $activo('seguimiento') ?>"><i class="fas fa-calendar-check"></i> <span>Seguimiento</span></a>
        <div class="grupo">Registros</div>
        <a href="<?= url('clientes') ?>" class="<?= $activo('clientes') ?>"><i class="fas fa-users"></i> <span>Clientes</span></a>
        <a href="<?= url('padron') ?>" class="<?= $activo('padron') ?>"><i class="fas fa-id-card"></i> <span>Padrón RUC</span></a>
        <a href="<?= url('productos') ?>" class="<?= $activo('productos') ?>"><i class="fas fa-box"></i> <span>Productos</span></a>
        <a href="<?= url('reportes') ?>" class="<?= $activo('reportes') ?>"><i class="fas fa-chart-line"></i> <span>Reportes</span></a>
        <?php if (Auth::esAdmin()): ?>
        <div class="grupo">Configuración</div>
        <a href="<?= url('usuarios') ?>" class="<?= $activo('usuarios') ?>"><i class="fas fa-user-gear"></i> <span>Usuarios</span></a>
        <a href="<?= url('empresas/editar') ?>" class="<?= $activo('empresas/editar') ?>"><i class="fas fa-building"></i> <span>Empresa</span></a>
        <a href="<?= url('empresas/diseno') ?>" class="<?= $activo('empresas/diseno') ?>"><i class="fas fa-ruler-combined"></i> <span>Diseño de impresión</span></a>
        <?php endif; ?>
    </nav>
</aside>
<main>
    <header class="topbar">
        <div class="izquierda">
            <button type="button" class="boton-icono cuadrado" id="boton-menu" title="Achicar o agrandar el menú"><i class="fas fa-bars"></i></button>
            <div class="empresa-activa">
                <span class="punto"></span>
                <?= e($empresa['nombre'] ?? 'Sin empresa') ?>
                <?php if (count(Auth::empresasPermitidas()) > 1): ?>
                    <a href="<?= url('empresas/seleccionar') ?>" class="cambiar">Cambiar</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="acciones">
            <span class="fecha-hoy"><?= date('d/m/Y') ?></span>
            <button type="button" class="boton-icono boton-tema" title="Modo claro u oscuro"><i class="fas fa-moon"></i><i class="fas fa-sun"></i></button>
            <div class="perfil">
                <div class="nombre">
                    <strong><?= e($usuario['nombre'] ?? '') ?></strong>
                    <span><?= e(ucfirst(strtolower($usuario['rol'] ?? ''))) ?></span>
                </div>
                <div class="avatar"><?= e(mb_substr($usuario['nombre'] ?? '?', 0, 1)) ?></div>
                <form method="post" action="<?= url('salir') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="boton-icono" title="Cerrar sesión"><i class="fas fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
    </header>
    <section class="contenido">
        <?php $mensajes = flashes(); ?>
        <noscript><?php foreach ($mensajes as $f): ?><div class="alerta alerta-<?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div><?php endforeach; ?></noscript>
        <script>window.MENSAJES = <?= json_encode($mensajes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
        <?= $contenido ?>
    </section>
</main>
<div class="velo-menu" id="velo-menu"></div>
<div class="menu-mas" id="menu-mas">
    <?php if (Auth::puedeFacturar()): ?>
    <a href="<?= url('facturas/nueva') ?>"><span class="ico ico-fac"><i class="fas fa-file-invoice"></i></span><span><strong>Nueva factura</strong><small>Abrir la venta</small></span></a>
    <?php endif; ?>
    <a href="<?= url('clientes/nuevo') ?>"><span class="ico ico-cli"><i class="fas fa-user-plus"></i></span><span><strong>Nuevo cliente</strong><small>Registrar un cliente</small></span></a>
</div>
<nav class="barra-movil" aria-label="Navegación">
    <a href="<?= url('inicio') ?>" class="<?= $activo('inicio') ?>"><i class="fas fa-house"></i><span>Inicio</span></a>
    <a href="<?= url('productos') ?>" class="<?= $activo('productos') ?>"><i class="fas fa-box"></i><span>Productos</span></a>
    <a href="#" class="mas" id="boton-mas" title="Crear" aria-expanded="false"><span class="circulo"><i class="fas fa-plus"></i></span></a>
    <a href="<?= url('seguimiento') ?>" class="<?= $activo('seguimiento') ?>"><i class="fas fa-clock"></i><span>Seguimiento</span></a>
    <a href="<?= url('reportes') ?>" class="<?= $activo('reportes') ?>"><i class="fas fa-chart-line"></i><span>Reportes</span></a>
</nav>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
