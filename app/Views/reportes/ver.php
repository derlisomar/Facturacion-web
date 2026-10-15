<?php
use App\Helpers\Exportador;

$params = ['clave' => $clave, 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta'], 'estado' => $filtros['estado'], 'empresa' => $filtros['empresa']];
$conFechas = empty($def['sin_fechas']);
?>
<div class="encabezado">
    <div>
        <a href="<?= url('reportes') ?>" class="volver"><i class="fas fa-arrow-left"></i> Centro de reportes</a>
        <h1 class="titulo"><?= e($def['nombre']) ?></h1>
        <p class="gris" style="margin:4px 0 0"><?= e($def['desc']) ?></p>
    </div>
    <div class="botones-consulta">
        <a class="btn btn-excel" href="<?= url('reportes/excel', $params) ?>"><i class="fas fa-file-excel"></i> Descargar Excel</a>
        <a class="btn btn-pdf" href="<?= url('reportes/pdf', $params) ?>" target="_blank"><i class="fas fa-file-pdf"></i> Descargar PDF</a>
    </div>
</div>

<form class="tarjeta" method="get" action="<?= url('reportes/ver') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="reportes/ver"><?php endif; ?>
    <input type="hidden" name="clave" value="<?= e($clave) ?>">
    <?php if ($conFechas): ?>
    <div class="rangos">
        <?php foreach (['hoy' => 'Hoy', 'ayer' => 'Ayer', '7' => 'Últimos 7 días', 'mes' => 'Este mes', 'mespas' => 'Mes pasado', 'anio' => 'Este año'] as $k => $t): ?>
            <button type="button" class="chip" data-rango="<?= $k ?>"><?= $t ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="filtros" style="margin:0">
        <?php if ($conFechas): ?>
            <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
            <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
        <?php endif; ?>
        <?php if (count($opcionesEmpresa) > 1): ?>
        <label>Empresa
            <select name="empresa">
                <?php foreach ($opcionesEmpresa as $id => $nombre): ?>
                    <option value="<?= (int) $id ?>" <?= $filtros['empresa'] === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
                <option value="todas" <?= $filtros['empresa'] === 'todas' ? 'selected' : '' ?>>Todas (una hoja por empresa)</option>
            </select>
        </label>
        <?php endif; ?>
        <?php if (!empty($def['estado'])): ?>
        <label>Estado
            <select name="estado">
                <option value="EMITIDA" <?= $filtros['estado'] === 'EMITIDA' ? 'selected' : '' ?>>No anuladas</option>
                <option value="ANULADA" <?= $filtros['estado'] === 'ANULADA' ? 'selected' : '' ?>>Anuladas</option>
                <option value="" <?= $filtros['estado'] === '' ? 'selected' : '' ?>>Todas</option>
            </select>
        </label>
        <?php endif; ?>
        <button class="btn btn-primario">Ver reporte</button>
    </div>
</form>

<p class="gris"><?= e($doc['subtitulo']) ?></p>

<?php foreach ($doc['secciones'] as $s):
    $cantidad = count($s['filas']);
    $totales = [];
    foreach ($s['columnas'] as $i => $c) {
        if (!empty($c['total'])) {
            $totales[$i] = array_sum(array_map(fn($f) => (float) ($f[$i] ?? 0), $s['filas']));
        }
    }
?>
<div class="tarjeta seccion-reporte">
    <div class="seccion-cabecera">
        <h2><?= e($s['titulo']) ?></h2>
        <span class="gris"><?= number_format($cantidad, 0, ',', '.') ?> filas</span>
    </div>
    <div class="tabla-scroll">
    <table class="tabla">
        <thead><tr>
            <?php foreach ($s['columnas'] as $c): ?><th class="<?= $c['tipo'] !== 'texto' ? 'der' : '' ?>"><?= e($c['t']) ?></th><?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach (array_slice($s['filas'], 0, $max) as $fila): ?>
            <tr>
                <?php foreach ($s['columnas'] as $i => $c): ?>
                    <td class="<?= $c['tipo'] !== 'texto' ? 'der' : '' ?>"><?= e(Exportador::formato($fila[$i] ?? '', $c['tipo'])) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cantidad): ?><tr><td colspan="<?= count($s['columnas']) ?>" class="vacio">Sin datos para los filtros elegidos.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($totales && $cantidad): ?>
        <tfoot><tr>
            <?php foreach ($s['columnas'] as $i => $c): ?>
                <td class="<?= $c['tipo'] !== 'texto' ? 'der' : '' ?>"><?= isset($totales[$i]) ? e(Exportador::formato($totales[$i], $c['tipo'])) : ($i === 0 ? 'TOTAL' : '') ?></td>
            <?php endforeach; ?>
        </tr></tfoot>
        <?php endif; ?>
    </table>
    </div>
    <?php if ($cantidad > $max): ?>
        <p class="aviso-filas">Se muestran las primeras <?= $max ?> filas. El Excel y el PDF incluyen todas (<?= number_format($cantidad, 0, ',', '.') ?>).</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<script>
document.querySelectorAll('[data-rango]').forEach((b) => b.addEventListener('click', () => {
    const h = new Date(), f = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const s = new Date(h); let desde, hasta = f(h);
    switch (b.dataset.rango) {
        case 'hoy': desde = hasta; break;
        case 'ayer': s.setDate(h.getDate() - 1); desde = hasta = f(s); break;
        case '7': s.setDate(h.getDate() - 6); desde = f(s); break;
        case 'mes': desde = f(new Date(h.getFullYear(), h.getMonth(), 1)); break;
        case 'mespas': desde = f(new Date(h.getFullYear(), h.getMonth() - 1, 1)); hasta = f(new Date(h.getFullYear(), h.getMonth(), 0)); break;
        case 'anio': desde = f(new Date(h.getFullYear(), 0, 1)); break;
    }
    const form = b.closest('form');
    form.desde.value = desde; form.hasta.value = hasta;
    form.submit();
}));
</script>
