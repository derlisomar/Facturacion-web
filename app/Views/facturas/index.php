<?php
use App\Core\Auth;

$params = ['desde' => $filtros['desde'], 'hasta' => $filtros['hasta'], 'q' => $filtros['busqueda'], 'condicion' => $filtros['condicion'],
           'metodo' => $filtros['metodo'], 'estado' => $filtros['estado'], 'empresa' => $filtros['empresa']];
$consultaActual = http_build_query($params + ['pagina' => $pagina]);
?>
<div class="encabezado">
    <h1 class="titulo">Consulta de facturas</h1>
    <?php if (Auth::puedeFacturar()): ?>
        <a href="<?= url('facturas/nueva') ?>" class="btn btn-primario"><i class="fas fa-plus"></i> Nueva factura</a>
    <?php endif; ?>
</div>

<form class="tarjeta filtros" method="get" action="<?= url('facturas') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="facturas"><?php endif; ?>
    <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
    <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
    <?php if ($variasEmpresas): ?>
    <label>Empresa
        <select name="empresa">
            <option value="actual" <?= $filtros['empresa'] === 'actual' ? 'selected' : '' ?>><?= e(Auth::empresa()['nombre']) ?></option>
            <option value="todas" <?= $filtros['empresa'] === 'todas' ? 'selected' : '' ?>>Todas</option>
        </select>
    </label>
    <?php endif; ?>
    <label>Estado
        <select name="estado">
            <option value="EMITIDA" <?= $filtros['estado'] === 'EMITIDA' ? 'selected' : '' ?>>No anuladas</option>
            <option value="ANULADA" <?= $filtros['estado'] === 'ANULADA' ? 'selected' : '' ?>>Anuladas</option>
            <option value="" <?= $filtros['estado'] === '' ? 'selected' : '' ?>>Todas</option>
        </select>
    </label>
    <label>Condición
        <select name="condicion">
            <option value="">Todas</option>
            <option value="CONTADO" <?= $filtros['condicion'] === 'CONTADO' ? 'selected' : '' ?>>Contado</option>
            <option value="CONTADO_FECHA" <?= $filtros['condicion'] === 'CONTADO_FECHA' ? 'selected' : '' ?>>Con fecha de pago</option>
        </select>
    </label>
    <label>Método
        <select name="metodo">
            <option value="">Todos</option>
            <option value="EFECTIVO" <?= $filtros['metodo'] === 'EFECTIVO' ? 'selected' : '' ?>>Efectivo</option>
            <option value="TRANSFERENCIA" <?= $filtros['metodo'] === 'TRANSFERENCIA' ? 'selected' : '' ?>>Transferencia</option>
        </select>
    </label>
    <label>Buscar<input type="search" name="q" value="<?= e($filtros['busqueda']) ?>" placeholder="Cliente, RUC o número"></label>
    <button class="btn btn-primario">Consultar</button>
</form>

<div class="tarjeta">
    <table class="tabla tabla-consulta">
        <thead><tr>
            <th>Número</th><th>Fecha</th><th>RUC</th><th>Razón social</th><th class="der">Total</th><th>Condición</th>
            <th class="der">Ítems</th><th class="der">IVA</th><th>Método</th><?= $variasEmpresas ? '<th>Empresa</th>' : '' ?><th>Seguimiento</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($facturas as $f):
            $anulada = $f['estado'] === 'ANULADA';
            $columnas = $variasEmpresas ? 12 : 11;
        ?>
            <tr class="fila-factura <?= $anulada ? 'anulada' : '' ?>" data-detalle="det-<?= (int) $f['id'] ?>" title="Tocá para ver el detalle">
                <td><?= e($f['numero_completo']) ?></td>
                <td><?= fecha($f['fecha']) ?></td>
                <td><?= e($f['ruc_cliente']) ?></td>
                <td><?= e($f['razon_social_cliente'] ?? '— hoja anulada —') ?></td>
                <td class="der"><?= gs($f['total']) ?></td>
                <td><?= $f['condicion'] === 'CONTADO' ? 'Contado' : 'Con fecha' ?></td>
                <td class="der"><?= (int) $f['items'] ?></td>
                <td class="der"><?= gs($f['total_iva5'] + $f['total_iva10']) ?></td>
                <td><?= e($f['metodo_pago'] ?? '') ?></td>
                <?php if ($variasEmpresas): ?><td><span class="etiqueta" style="background: <?= e($f['empresa_color']) ?>22; color: <?= e($f['empresa_color']) ?>"><?= e($f['empresa_nombre']) ?></span></td><?php endif; ?>
                <td>
                    <?php if ($anulada): ?>
                        <span class="etiqueta rojo">ANULADA</span>
                    <?php elseif ($f['condicion'] === 'CONTADO_FECHA'): ?>
                        <?= $f['saldo'] > 0 ? 'Pago ' . fecha($f['fecha_pago']) . ' · saldo ' . gs($f['saldo']) : '<span class="etiqueta verde">Cobrada</span>' ?>
                    <?php endif; ?>
                </td>
                <td class="der acciones-fila">
                    <a href="<?= url('facturas/ver', ['id' => $f['id']]) ?>" title="Ver"><i class="fas fa-eye"></i></a>
                    <?php if (!$anulada): ?>
                        <a href="<?= url('facturas/imprimir', ['id' => $f['id']]) ?>" target="_blank" title="Reimprimir"><i class="fas fa-print"></i></a>
                        <?php if (Auth::esAdmin()): ?>
                            <form method="post" action="<?= url('facturas/anular') ?>" class="form-anular" data-numero="<?= e($f['numero_completo']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                <input type="hidden" name="motivo">
                                <input type="hidden" name="volver" value="consulta">
                                <input type="hidden" name="filtros" value="<?= e($consultaActual) ?>">
                                <button class="btn-link rojo" title="Anular"><i class="fas fa-ban"></i></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="fila-detalle" id="det-<?= (int) $f['id'] ?>" hidden>
                <td colspan="<?= $columnas ?>">
                    <?php if ($anulada && $f['motivo_anulacion']): ?><p class="rojo">Motivo de anulación: <?= e($f['motivo_anulacion']) ?></p><?php endif; ?>
                    <table class="tabla detalle-interno">
                        <thead><tr><th class="der">Cant.</th><th>Descripción</th><th class="der">Precio</th><th class="der">IVA</th><th class="der">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($detalles[$f['id']] ?? [] as $d): ?>
                            <tr><td class="der"><?= cantidad($d['cantidad']) ?></td><td><?= e($d['descripcion']) ?></td><td class="der"><?= gs($d['precio_unitario']) ?></td>
                                <td class="der"><?= $d['iva'] ? (int) $d['iva'] . '%' : 'Exenta' ?></td><td class="der"><?= gs($d['subtotal']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($detalles[$f['id']])): ?><tr><td colspan="5" class="vacio">Sin ítems.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($f['observacion']): ?><p class="gris">Obs.: <?= e($f['observacion']) ?></p><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$facturas): ?><tr><td colspan="12" class="vacio">No hay facturas con esos filtros.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php $ruta = 'facturas'; $porPagina = 30; require __DIR__ . '/../parciales/paginacion.php'; ?>

    <div class="pie-consulta">
        <div><span>Monto total</span><strong><?= gs($monto) ?></strong></div>
        <div><span>IVA</span><strong><?= gs($iva) ?></strong></div>
        <div><span>Cantidad</span><strong><?= (int) $total ?></strong></div>
        <div class="botones-consulta">
            <a class="btn" href="<?= url('facturas/listado', $params) ?>" target="_blank"><i class="fas fa-print"></i> Imprimir listado</a>
            <a class="btn" href="<?= url('facturas/exportar', $params) ?>"><i class="fas fa-file-excel" style="color:#16a34a"></i> Excel</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.fila-factura').forEach((fila) => fila.addEventListener('click', (e) => {
    if (e.target.closest('a, button, form')) return;
    const detalle = document.getElementById(fila.dataset.detalle);
    detalle.hidden = !detalle.hidden;
    fila.classList.toggle('abierta', !detalle.hidden);
}));
document.querySelectorAll('.form-anular').forEach((form) => form.addEventListener('submit', async (e) => {
    if (form.dataset.listo) return;
    e.preventDefault();
    const motivo = await Aviso.pedirTexto('La factura queda anulada en el libro de ventas. No se puede deshacer.', {
        titulo: 'Anular factura ' + form.dataset.numero, placeholder: 'Motivo de la anulación', si: 'Sí, anular',
        peligro: true, requerido: 'Escribí el motivo para poder anular.',
    });
    if (!motivo) return;
    form.motivo.value = motivo;
    form.dataset.listo = '1';
    form.submit();
}));
</script>
