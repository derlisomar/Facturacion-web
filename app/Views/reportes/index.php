<?php
$cobradoTotal = array_sum(array_column($metodos, 'monto'));
$maxEmpresa = max(array_merge([1], array_column($porEmpresa, 'monto')));
?>
<div class="encabezado">
    <div>
        <h1 class="titulo">Centro de reportes</h1>
        <p class="gris" style="margin:4px 0 0">Elegí un informe, filtrá por fechas y empresa, y descargalo en Excel o PDF.</p>
    </div>
    <form class="filtros" method="get" action="<?= url('reportes') ?>" style="margin:0">
        <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="reportes"><?php endif; ?>
        <label>Desde<input type="date" name="desde" value="<?= e($desde) ?>"></label>
        <label>Hasta<input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
        <button class="btn">Ver</button>
    </form>
</div>

<?php $colores = ['azul' => '#2563eb', 'verde' => '#059669', 'ambar' => '#d97706', 'rojo' => '#dc2626']; ?>
<?php foreach ($grupos as $g): ?>
<section class="grupo-reportes">
    <div class="grupo-cabecera">
        <span class="grupo-icono" style="--c: <?= $colores[$g['color']] ?>"><i class="fas <?= e($g['icono']) ?>"></i></span>
        <h2><?= e($g['nombre']) ?></h2>
        <span class="gris"><?= count($g['reportes']) ?> reportes</span>
    </div>
    <div class="tarjetas-reporte">
        <?php foreach ($g['reportes'] as $clave => $rep): ?>
            <a class="tarjeta-reporte" href="<?= url('reportes/ver', ['clave' => $clave, 'desde' => $desde, 'hasta' => $hasta]) ?>">
                <strong><?= e($rep['nombre']) ?></strong>
                <span><?= e($rep['desc']) ?></span>
                <em><i class="fas fa-file-excel"></i> Excel · <i class="fas fa-file-pdf"></i> PDF</em>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<h2 class="subtitulo-tablero">Tablero del período</h2>
<div class="kpis">
    <div class="kpi" style="--c:#2563eb"><span>Facturado</span><strong><?= gs($resumen['monto']) ?> ₲</strong>
        <small><?= (int) $resumen['cantidad'] ?> facturas · IVA <?= gs($resumen['iva']) ?></small></div>
    <div class="kpi" style="--c:#059669"><span>Cobrado en el período</span><strong><?= gs($cobradoTotal) ?> ₲</strong>
        <small><?php foreach ($metodos as $m) echo e(ucfirst(strtolower($m['metodo_pago']))) . ' ' . gs($m['monto']) . ' · '; ?></small></div>
    <a class="kpi" style="--c:#ef4444" href="<?= url('seguimiento') ?>"><span>Por cobrar (hoy)</span><strong><?= gs($pendiente['saldo']) ?> ₲</strong>
        <small><?= (int) $pendiente['cantidad'] ?> facturas · <?= (int) $pendiente['vencidas'] ?> vencidas</small></a>
    <div class="kpi" style="--c:#6b7280"><span>Anuladas</span><strong><?= (int) $resumen['anuladas'] ?></strong></div>
</div>

<div class="tarjeta">
    <h2>Facturado por día</h2>
    <div style="height:280px"><canvas id="graficoDias"></canvas></div>
</div>

<?php if ($porEmpresa): ?>
<div class="tarjeta">
    <h2>Comparación entre empresas</h2>
    <?php foreach ($porEmpresa as $e): ?>
        <div class="barra-fila">
            <span><?= e($e['nombre']) ?></span>
            <div class="barra"><div style="width: <?= round($e['monto'] / $maxEmpresa * 100) ?>%; background: <?= e($e['color']) ?>"></div></div>
            <strong><?= gs($e['monto']) ?> ₲</strong><small class="gris"><?= (int) $e['cantidad'] ?> fact.</small>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="dos-columnas">
    <div class="tarjeta">
        <h2>Mejores clientes</h2>
        <table class="tabla">
            <thead><tr><th>Cliente</th><th class="der">Facturas</th><th class="der">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr><td><?= e($c['razon_social_cliente']) ?><br><small class="gris"><?= e($c['ruc_cliente']) ?></small></td>
                    <td class="der"><?= (int) $c['cantidad'] ?></td><td class="der"><?= gs($c['monto']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?><tr><td colspan="3" class="vacio">Sin ventas en el período.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="tarjeta">
        <h2>Productos y servicios más vendidos</h2>
        <table class="tabla">
            <thead><tr><th>Descripción</th><th class="der">Cant.</th><th class="der">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr><td><?= e($p['descripcion']) ?> <?= $p['tipo'] === 'S' ? '<small class="gris">servicio</small>' : '' ?></td>
                    <td class="der"><?= cantidad($p['cantidad']) ?></td><td class="der"><?= gs($p['monto']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$productos): ?><tr><td colspan="3" class="vacio">Sin ventas en el período.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
<script>
(function () {
    const datos = <?= json_encode(array_map(fn($d) => ['f' => fecha($d['fecha']), 'm' => (int) $d['monto']], $porDia)) ?>;
    const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const color = css('--empresa') || '#2563eb';
    const grafico = new Chart(document.getElementById('graficoDias'), {
        type: 'bar',
        data: { labels: datos.map(d => d.f), datasets: [{ label: 'Facturado', data: datos.map(d => d.m), backgroundColor: color, borderRadius: 4 }] },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.y.toLocaleString('es-PY') + ' ₲' } } },
            scales: { y: { ticks: { callback: v => v.toLocaleString('es-PY') } }, x: { grid: { display: false } } }
        }
    });
    // Colores de ejes y grilla según el modo claro u oscuro
    const pintar = () => {
        grafico.options.scales.x.ticks.color = grafico.options.scales.y.ticks.color = css('--gris');
        grafico.options.scales.y.grid.color = css('--borde');
        grafico.update();
    };
    pintar();
    window.addEventListener('cambio-tema', pintar);
})();
</script>
