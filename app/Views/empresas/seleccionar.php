<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegir empresa · <?= e(config('app.nombre')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <?php require __DIR__ . '/../parciales/tema.php'; ?>
    <link rel="preload" href="<?= url_base() ?>/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="pantalla-login">
<div class="sel-columna">
<header class="sel-marca">
    <img class="sel-logo logo-claro" src="<?= asset('img/logo-claro.png') ?>" alt="Dobi Soluciones Informáticas">
    <img class="sel-logo logo-oscuro" src="<?= asset('img/logo-oscuro.png') ?>" alt="Dobi Soluciones Informáticas">
    <h2>Software de gestión de facturas</h2>
</header>
<div class="login-caja ancho">
    <button type="button" class="boton-icono boton-tema" title="Modo claro u oscuro"><i class="fas fa-moon"></i><i class="fas fa-sun"></i></button>
    <h1>¿Con qué empresa vas a trabajar?</h1>
    <?php foreach (flashes() as $f): ?><div class="alerta alerta-<?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div><?php endforeach; ?>
    <?php if (!$empresas): ?>
        <div class="alerta alerta-error">Tu usuario no tiene empresas asignadas. Pedile al administrador que te asigne una.</div>
    <?php endif; ?>
    <div class="empresas-grid">
        <?php foreach ($empresas as $emp): ?>
            <form method="post" action="<?= url('empresas/seleccionar') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="empresa_id" value="<?= (int) $emp['id'] ?>">
                <button type="submit" class="empresa-tarjeta" style="--c: <?= e($emp['color']) ?>">
                    <i class="fas fa-building"></i>
                    <strong><?= e($emp['nombre_fantasia'] ?: $emp['razon_social']) ?></strong>
                    <span>RUC <?= e($emp['ruc']) ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
