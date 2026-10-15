<?php
use App\Core\Auth;

$nombresMes = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre'];
$nombresDia = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$mesNombre = $nombresMes[(int) date('n') - 1];
$hoyTexto = $nombresDia[(int) date('w')] . ' ' . date('d') . ' de ' . $mesNombre;
$anterior = (float) $panel['mes_anterior_monto'];
$variacion = $anterior > 0 ? round(((float) $panel['mes_monto'] - $anterior) / $anterior * 100) : null;
?>
<div class="encabezado">
    <div>
        <h1 class="titulo">Panel de rendimiento</h1>
        <p class="gris" style="margin:4px 0 0"><?= e(Auth::empresa()['nombre']) ?> · <?= e($hoyTexto) ?></p>
    </div>
    <a class="btn" href="<?= url('reportes/pdf', ['clave' => 'libro_ventas', 'desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')]) ?>" target="_blank"><i class="fas fa-file-pdf"></i> Libro de ventas del mes</a>
</div>

<!-- Lo más importante arriba: cuánto entró hoy y cuánto falta cobrar -->
<section class="dk-sec">
    <div class="dk-head"><span class="dk-badge">Hoy</span><span class="dk-sub">Lo vendido y cobrado hoy, y lo que queda por cobrar</span></div>
    <div class="dk-grid dk-grid-4">
        <a class="dk-tile" href="<?= url('facturas', ['desde' => date('Y-m-d'), 'hasta' => date('Y-m-d')]) ?>"><div class="dk-ico"><i class="fas fa-file-invoice"></i></div><div><div class="dk-lbl">Facturado hoy</div><div class="dk-val"><?= gs($panel['hoy_monto']) ?> ₲</div><div class="dk-note"><?= (int) $panel['hoy_cantidad'] ?> factura(s)</div></div></a>
        <div class="dk-tile"><div class="dk-ico"><i class="fas fa-hand-holding-dollar"></i></div><div><div class="dk-lbl">Cobrado hoy</div><div class="dk-val"><?= gs($panel['hoy_cobrado']) ?> ₲</div><div class="dk-note">Efectivo <?= gs($cobros['efectivo_hoy']) ?> · Transf. <?= gs($cobros['transferencia_hoy']) ?></div></div></div>
        <a class="dk-tile" href="<?= url('seguimiento') ?>"><div class="dk-ico dk-ico-amb"><i class="fas fa-calendar-check"></i></div><div><div class="dk-lbl">Por cobrar</div><div class="dk-val"><?= gs($pendiente['saldo']) ?> ₲</div><div class="dk-note"><?= (int) $pendiente['cantidad'] ?> factura(s) con fecha de pago</div></div></a>
        <a class="dk-tile" href="<?= url('seguimiento') ?>"><div class="dk-ico dk-ico-rojo"><i class="fas fa-clock"></i></div><div><div class="dk-lbl">Vencido sin cobrar</div><div class="dk-val <?= $pendiente['vencidas'] ? 'dk-rojo' : '' ?>"><?= gs($panel['vencido_monto']) ?> ₲</div><div class="dk-note"><?= (int) $pendiente['vencidas'] ?> pasaron la fecha · <?= gs($cobros['semana_monto']) ?> ₲ vencen en 7 días</div></div></a>
    </div>
</section>

<!-- El mes: cuánto se vendió, a quién y cómo se cobró -->
<section class="dk-sec vio">
    <div class="dk-head"><span class="dk-badge">Este mes</span><span class="dk-sub"><?= $mesNombre . ' ' . date('Y') ?> · acumulado al día de hoy</span></div>
    <div class="dk-grid dk-grid-4">
        <div class="dk-tile"><div class="dk-ico"><i class="fas fa-sack-dollar"></i></div><div><div class="dk-lbl">Ingresos del mes</div><div class="dk-val"><?= gs($panel['mes_monto']) ?> ₲</div>
            <div class="dk-note"><?php if ($variacion === null): ?>Sin mes anterior para comparar<?php else: ?><span class="<?= $variacion >= 0 ? 'dk-verde' : 'dk-rojo' ?>"><?= $variacion >= 0 ? '↑' : '↓' ?> <?= abs($variacion) ?>%</span> vs. mes anterior<?php endif; ?></div></div></div>
        <a class="dk-tile" href="<?= url('facturas') ?>"><div class="dk-ico"><i class="fas fa-receipt"></i></div><div><div class="dk-lbl">Facturas emitidas</div><div class="dk-val"><?= (int) $mes['cantidad'] ?></div><div class="dk-note"><?= (int) $mes['anuladas'] ?> anulada(s)</div></div></a>
        <div class="dk-tile"><div class="dk-ico"><i class="fas fa-scale-balanced"></i></div><div><div class="dk-lbl">Promedio por factura</div><div class="dk-val"><?= gs($mes['cantidad'] ? $mes['monto'] / $mes['cantidad'] : 0) ?> ₲</div><div class="dk-note">Ticket promedio del mes</div></div></div>
        <a class="dk-tile" href="<?= url('clientes') ?>"><div class="dk-ico"><i class="fas fa-users"></i></div><div><div class="dk-lbl">Clientes atendidos</div><div class="dk-val"><?= (int) $clientesMes ?></div><div class="dk-note">de <?= (int) $clientes ?> registrados</div></div></a>
    </div>
    <div class="dk-charts">
        <div class="dk-chart"><h4>Ingresos por día</h4><div id="graficoDias" style="height:260px"></div></div>
        <div class="dk-dos">
            <div class="dk-chart"><h4>Cobrado por método de pago</h4><div id="graficoMetodo" style="height:250px"></div></div>
            <div class="dk-chart"><h4>Ventas por modalidad</h4><div id="graficoModalidad" style="height:250px"></div></div>
        </div>
    </div>
</section>

<!-- Cobranza diaria -->
<section class="dk-sec ver">
    <div class="dk-head"><span class="dk-badge">Cobranza</span><span class="dk-sub">Lo que entró en <?= $mesNombre ?>, día por día</span></div>
    <div class="dk-grid">
        <a class="dk-tile" href="<?= url('reportes/ver', ['clave' => 'cobros', 'desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')]) ?>"><div class="dk-ico"><i class="fas fa-money-bill-wave"></i></div><div><div class="dk-lbl">En efectivo</div><div class="dk-val"><?= gs($cobros['efectivo_mes']) ?> ₲</div><div class="dk-note"><?= (int) $cobros['efectivo_cantidad'] ?> cobro(s) en el mes</div></div></a>
        <a class="dk-tile" href="<?= url('reportes/ver', ['clave' => 'cobros', 'desde' => date('Y-m-01'), 'hasta' => date('Y-m-d')]) ?>"><div class="dk-ico"><i class="fas fa-building-columns"></i></div><div><div class="dk-lbl">Por transferencia</div><div class="dk-val"><?= gs($cobros['transferencia_mes']) ?> ₲</div><div class="dk-note"><?= (int) $cobros['transferencia_cantidad'] ?> cobro(s) en el mes</div></div></a>
    </div>
    <div class="dk-charts">
        <div class="dk-chart"><h4>Cobrado por día: efectivo y transferencia</h4><div id="graficoCobros" style="height:240px"></div></div>
    </div>
</section>

<div class="dos-columnas">
    <div class="tarjeta">
        <h2><i class="fas fa-trophy acento"></i> Mejores clientes del mes</h2>
        <table class="tabla">
            <thead><tr><th>Cliente</th><th class="der">Facturas</th><th class="der">Total</th></tr></thead>
            <tbody>
            <?php foreach ($mejores as $c): ?>
                <tr><td><?= e($c['cliente']) ?></td><td class="der"><?= (int) $c['facturas'] ?></td><td class="der"><?= gs($c['monto']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$mejores): ?><tr><td colspan="3" class="vacio">Todavía no hay ventas este mes.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="tarjeta">
        <h2><i class="fas fa-clock-rotate-left acento"></i> Últimas facturas</h2>
        <table class="tabla">
            <thead><tr><th>Número</th><th class="ocultar-movil">Fecha</th><th>Cliente</th><th class="der">Total</th></tr></thead>
            <tbody>
            <?php foreach ($ultimas as $f): ?>
                <tr class="<?= $f['estado'] === 'ANULADA' ? 'anulada' : '' ?>">
                    <td><a href="<?= url('facturas/ver', ['id' => $f['id']]) ?>"><?= e($f['numero_completo']) ?></a></td>
                    <td class="ocultar-movil"><?= fecha($f['fecha']) ?></td>
                    <td><?= e($f['razon_social_cliente'] ?? '— hoja anulada —') ?></td>
                    <td class="der"><?= gs($f['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ultimas): ?><tr><td colspan="4" class="vacio">Todavía no hay facturas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<section class="dk-sec">
    <div class="dk-head"><span class="dk-badge">Tendencia</span><span class="dk-sub">Facturado en los últimos 6 meses</span></div>
    <div class="dk-chart"><div id="graficoMeses" style="height:240px"></div></div>
</section>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
<script>
(function () {
    if (!window.ApexCharts) return;
    const datos = <?= json_encode([
        'dias'        => array_map(fn($d) => ['x' => $d['dia'], 'y' => (int) $d['monto']], $dias),
        'meses'       => array_map(fn($m) => ['x' => $m['mes'], 'y' => (int) $m['monto']], $meses),
        'modalidades' => array_map(fn($m) => ['x' => $m['modalidad'], 'y' => (int) $m['monto']], $modalidades),
        'cobros'      => array_map(fn($d) => ['x' => $d['dia'], 'e' => (int) $d['efectivo'], 't' => (int) $d['transferencia']], $cobrosDias),
        'metodos'     => array_map(fn($m) => ['x' => ucfirst(strtolower($m['metodo_pago'])), 'y' => (int) $m['monto']], $metodos),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const gs = (v) => Math.round(v).toLocaleString('es-PY');
    let graficos = [];

    function dibujar() {
        graficos.forEach((g) => g.destroy());
        const oscuro = document.documentElement.getAttribute('data-theme') === 'dark';
        const texto = oscuro ? '#9ca3af' : '#6b7280';
        const grilla = oscuro ? '#374151' : '#f3f4f6';
        const base = {
            chart: { toolbar: { show: false }, fontFamily: 'Outfit, sans-serif', background: 'transparent', foreColor: texto },
            dataLabels: { enabled: false },
            grid: { borderColor: grilla, strokeDashArray: 4 },
            tooltip: { theme: oscuro ? 'dark' : 'light', y: { formatter: (v) => 'Gs. ' + gs(v) } },
            yaxis: { labels: { formatter: (v) => gs(v) } },
        };
        const sinDatos = (lista) => lista.length ? lista : [{ x: 'Sin datos', y: 0 }];

        graficos = [
            new ApexCharts(document.querySelector('#graficoDias'), { ...base,
                series: [{ name: 'Ingresos', data: datos.dias }],
                chart: { ...base.chart, type: 'area', height: 260 },
                colors: ['#3b82f6'], stroke: { curve: 'smooth', width: 3 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .4, opacityTo: .05, stops: [0, 90, 100] } },
                xaxis: { axisBorder: { show: false }, axisTicks: { show: false } },
            }),
            new ApexCharts(document.querySelector('#graficoModalidad'), { ...base,
                series: [{ name: 'Vendido', data: sinDatos(datos.modalidades) }],
                chart: { ...base.chart, type: 'bar', height: 250 },
                colors: ['#10b981', '#3b82f6', '#f59e0b'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '40%', distributed: true } }, legend: { show: false },
            }),
            new ApexCharts(document.querySelector('#graficoMetodo'), { ...base,
                series: datos.metodos.length ? datos.metodos.map((m) => m.y) : [1],
                labels: datos.metodos.length ? datos.metodos.map((m) => m.x) : ['Sin cobros'],
                chart: { ...base.chart, type: 'donut', height: 250 },
                colors: datos.metodos.length ? ['#8b5cf6', '#f59e0b', '#3b82f6'] : [grilla],
                plotOptions: { pie: { donut: { size: '65%' } } }, stroke: { show: false },
                legend: { position: 'bottom' },
                tooltip: { ...base.tooltip, enabled: datos.metodos.length > 0 },
            }),
            new ApexCharts(document.querySelector('#graficoCobros'), { ...base,
                series: [
                    { name: 'Efectivo', data: datos.cobros.map((d) => ({ x: d.x, y: d.e })) },
                    { name: 'Transferencia', data: datos.cobros.map((d) => ({ x: d.x, y: d.t })) },
                ],
                chart: { ...base.chart, type: 'bar', height: 240, stacked: true },
                colors: ['#10b981', '#3b82f6'], plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                legend: { position: 'top', horizontalAlign: 'right' },
                xaxis: { axisBorder: { show: false }, axisTicks: { show: false } },
            }),
            new ApexCharts(document.querySelector('#graficoMeses'), { ...base,
                series: [{ name: 'Facturado', data: datos.meses }],
                chart: { ...base.chart, type: 'bar', height: 240 },
                colors: ['#6366f1'], plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            }),
        ];
        graficos.forEach((g) => g.render());
    }
    dibujar();
    window.addEventListener('cambio-tema', dibujar);
})();
</script>
