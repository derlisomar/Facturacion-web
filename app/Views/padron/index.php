<?php $estados = ['ACTIVO', 'SUSPENSION TEMPORAL', 'CANCELADO', 'BLOQUEADO']; ?>
<div class="encabezado">
    <div>
        <h1 class="titulo">Padrón RUC de la DNIT</h1>
        <p class="gris" style="margin:4px 0 0">Se usa para completar el nombre solo al escribir el RUC de un cliente nuevo. Los registros del padrón no son clientes.</p>
    </div>
    <div class="kpi-mini"><span class="gris">Registros cargados</span><strong><?= number_format($cantidad, 0, ',', '.') ?></strong></div>
</div>

<?php if (!$instalado): ?>
    <div class="alerta alerta-error">Falta la tabla del padrón en esta base. Ejecutá una vez el archivo <strong>database/actualizar_padron_ruc.sql</strong> en pgAdmin (Query Tool sobre tu base) y volvé a abrir esta pantalla.</div>
<?php else: ?>

<?php if (\App\Core\Auth::esAdmin()): ?>
<div class="tarjeta padron-importar">
    <h2><i class="fas fa-file-import"></i> Importar archivos</h2>
    <ol class="pasos">
        <li>Descargá los archivos <strong>ruc0</strong> a <strong>ruc9</strong> desde la página de la DNIT:
            <a href="https://www.dnit.gov.py/web/portal-institucional/listado-de-ruc-con-sus-equivalencias" target="_blank" rel="noopener">Listado de RUC con sus equivalencias</a>.</li>
        <li>Subilos acá tal como vienen (.zip) o descomprimidos (.txt o .csv). Podés elegir varios a la vez.</li>
        <li>Volver a importar actualiza los datos sin duplicar. Conviene hacerlo cada tanto para tener los RUC nuevos.</li>
    </ol>
    <form method="post" action="<?= url('padron/importar') ?>" enctype="multipart/form-data" class="fila-inline" id="form-padron">
        <?= csrf_field() ?>
        <input type="file" name="archivos[]" accept=".txt,.csv,.zip" multiple required>
        <button class="btn btn-primario"><i class="fas fa-upload"></i> Importar</button>
        <span class="gris" id="padron-cargando" hidden><i class="fas fa-spinner fa-spin"></i> Importando, puede tardar unos minutos…</span>
    </form>
    <p class="gris dz-ayuda">Límite de este servidor por envío: <?= e(ini_get('post_max_size')) ?> (archivo: <?= e(ini_get('upload_max_filesize')) ?>). Si los archivos son más grandes, subilos de a uno o aumentá esos valores en php.ini.</p>
</div>
<script>
document.getElementById('form-padron').addEventListener('submit', function () {
    document.getElementById('padron-cargando').hidden = false;
    this.querySelector('button').disabled = true;
});
</script>
<?php endif; ?>

<form class="filtros" method="get" action="<?= url('padron') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="padron"><?php endif; ?>
    <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por RUC o por palabras del nombre">
    <select name="estado">
        <option value="">Todos los estados</option>
        <?php foreach ($estados as $es): ?><option <?= $estado === $es ? 'selected' : '' ?>><?= e($es) ?></option><?php endforeach; ?>
    </select>
    <button class="btn">Buscar</button>
</form>
<div class="tarjeta">
    <table class="tabla">
        <thead><tr><th>RUC</th><th>Nombre o razón social</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($filas as $p): ?>
            <?php $ruc = $p['ruc'] . ($p['dv'] !== null ? '-' . $p['dv'] : ''); ?>
            <tr>
                <td><?= e($ruc) ?></td>
                <td><?= e($p['nombre']) ?></td>
                <td><span class="etiqueta <?= $p['estado'] === 'ACTIVO' ? 'verde' : 'rojo' ?>"><?= e($p['estado'] ?: '—') ?></span></td>
                <td class="der">
                    <?php if (\App\Core\Auth::puedeFacturar()): ?>
                        <a href="<?= url('clientes/nuevo', ['ruc' => $ruc, 'nombre' => \App\Models\Padron::nombreFactura($p['nombre'])]) ?>" title="Cargar como cliente"><i class="fas fa-user-plus"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$filas): ?><tr><td colspan="4" class="vacio"><?= $cantidad ? 'No hay coincidencias.' : 'El padrón está vacío. Importá los archivos de la DNIT.' ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php $ruta = 'padron'; $porPagina = 25; $params = ['q' => $busqueda, 'estado' => $estado]; require __DIR__ . '/../parciales/paginacion.php'; ?>
</div>
<?php endif; ?>
