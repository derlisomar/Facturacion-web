<?php $emitida = $factura['estado'] === 'EMITIDA'; ?>
<div class="encabezado">
    <h1 class="titulo">Factura <?= e($factura['numero_completo']) ?>
        <span class="etiqueta <?= $emitida ? 'verde' : 'rojo' ?>"><?= e($factura['estado']) ?></span></h1>
    <div>
        <?php if ($emitida): ?>
            <a href="<?= url('facturas/imprimir', ['id' => $factura['id']]) ?>" class="btn" data-imprimir><i class="fas fa-print"></i> Reimprimir</a>
        <?php endif; ?>
    </div>
</div>
<div class="dos-columnas">
    <div class="tarjeta datos">
        <p><span>Fecha</span><?= fecha($factura['fecha']) ?></p>
        <p><span>Cliente</span><?= e($factura['razon_social_cliente']) ?></p>
        <p><span>RUC</span><?= e($factura['ruc_cliente']) ?></p>
        <p><span>Condición</span><?= $factura['condicion'] === 'CONTADO' ? 'Contado' : 'Contado con fecha de pago (' . fecha($factura['fecha_pago']) . ')' ?></p>
        <p><span>Método</span><?= e($factura['metodo_pago'] ?? '—') ?></p>
        <p><span>Timbrado</span><?= e($factura['timbrado_numero']) ?></p>
        <p><span>Cargada por</span><?= e($factura['usuario']) ?> · <?= date('d/m/Y H:i', strtotime($factura['creado_en'])) ?></p>
        <?php if ($factura['observacion']): ?><p><span>Observación</span><?= e($factura['observacion']) ?></p><?php endif; ?>
        <?php if (!$emitida): ?><p class="rojo"><span>Motivo anulación</span><?= e($factura['motivo_anulacion']) ?></p><?php endif; ?>
    </div>
    <div class="tarjeta datos">
        <p><span>Exenta</span><?= gs($factura['total_exenta']) ?></p>
        <p><span>Gravada 5%</span><?= gs($factura['total_gravada5']) ?></p>
        <p><span>Gravada 10%</span><?= gs($factura['total_gravada10']) ?></p>
        <p><span>IVA 5% / 10%</span><?= gs($factura['total_iva5']) ?> / <?= gs($factura['total_iva10']) ?></p>
        <p class="total-final"><span>Total</span><?= gs($factura['total']) ?> ₲</p>
        <p><span>En letras</span><?= e($factura['monto_letras']) ?></p>
        <?php if ($emitida): ?>
            <p><span>Cobrado</span><?= gs($factura['cobrado']) ?></p>
            <p class="<?= $factura['saldo'] > 0 ? 'rojo' : 'verde' ?>"><span>Saldo</span><?= gs($factura['saldo']) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="tarjeta">
    <h2>Detalle</h2>
    <table class="tabla">
        <thead><tr><th class="der">Cant.</th><th>Descripción</th><th class="der">Precio</th><th class="der">IVA</th><th class="der">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($detalle as $d): ?>
            <tr><td class="der"><?= cantidad($d['cantidad']) ?></td><td><?= e($d['descripcion']) ?></td><td class="der"><?= gs($d['precio_unitario']) ?></td>
                <td class="der"><?= $d['iva'] ? (int) $d['iva'] . '%' : 'Exenta' ?></td><td class="der"><?= gs($d['subtotal']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($cobros || ($emitida && $factura['saldo'] > 0)): ?>
<div class="tarjeta">
    <h2>Cobros</h2>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th>Usuario</th><th class="der">Monto</th></tr></thead>
        <tbody>
        <?php foreach ($cobros as $c): ?>
            <tr><td><?= fecha($c['fecha']) ?></td><td><?= e($c['metodo_pago']) ?></td><td><?= e($c['referencia']) ?></td><td><?= e($c['usuario']) ?></td><td class="der"><?= gs($c['monto']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($emitida && $factura['saldo'] > 0 && \App\Core\Auth::puedeFacturar()): ?>
        <form method="post" action="<?= url('seguimiento/cobrar') ?>" class="formulario fila-cobro">
            <?= csrf_field() ?>
            <input type="hidden" name="factura_id" value="<?= (int) $factura['id'] ?>">
            <input type="hidden" name="volver" value="factura">
            <label>Fecha<input type="date" name="fecha" value="<?= date('Y-m-d') ?>"></label>
            <label>Monto<input name="monto" value="<?= (int) $factura['saldo'] ?>" class="monto" inputmode="numeric"></label>
            <label>Método<select name="metodo_pago"><option>EFECTIVO</option><option>TRANSFERENCIA</option></select></label>
            <label>Referencia<input name="referencia" maxlength="100"></label>
            <button class="btn btn-primario">Registrar cobro</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($emitida && \App\Core\Auth::esAdmin()): ?>
<details class="tarjeta zona-peligro">
    <summary>Anular factura</summary>
    <form method="post" action="<?= url('facturas/anular') ?>" class="formulario">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $factura['id'] ?>">
        <label>Motivo *<input name="motivo" required maxlength="200"></label>
        <p class="gris">La factura queda registrada como ANULADA, sale del seguimiento y se devuelve el stock de los productos físicos.</p>
        <button class="btn btn-peligro" data-confirmar="¿Anular la factura <?= e($factura['numero_completo']) ?>? No se puede deshacer." data-si="Sí, anular">Anular</button>
    </form>
</details>
<?php endif; ?>
