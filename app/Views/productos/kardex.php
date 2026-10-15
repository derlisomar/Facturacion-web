<div class="encabezado">
    <h1 class="titulo"><?= e($producto['descripcion']) ?></h1>
    <div class="stock-grande">Stock actual: <strong><?= cantidad($producto['stock_actual']) ?></strong></div>
</div>
<?php if (\App\Core\Auth::puedeFacturar()): ?>
<form method="post" action="<?= url('productos/stock') ?>" class="tarjeta formulario">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
    <div class="fila">
        <label>Movimiento
            <select name="tipo">
                <option value="ENTRADA">Entrada de mercadería (+)</option>
                <option value="AJUSTE">Ajuste de inventario (+/−)</option>
            </select>
        </label>
        <label>Cantidad<input name="cantidad" inputmode="decimal" required placeholder="Ej: 10 o -2"></label>
        <label class="grande">Observación<input name="observacion" maxlength="200" placeholder="Factura de compra, conteo físico..."></label>
    </div>
    <div class="botones"><button class="btn btn-primario">Registrar</button></div>
</form>
<?php endif; ?>
<div class="tarjeta">
    <h2>Movimientos</h2>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Tipo</th><th class="der">Cantidad</th><th class="der">Stock</th><th>Factura</th><th>Usuario</th><th>Observación</th></tr></thead>
        <tbody>
        <?php foreach ($movimientos as $m): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                <td><?= e(str_replace('_', ' ', $m['tipo'])) ?></td>
                <td class="der <?= $m['cantidad'] < 0 ? 'rojo' : 'verde' ?>"><?= cantidad($m['cantidad']) ?></td>
                <td class="der"><?= cantidad($m['stock_resultante']) ?></td>
                <td><?php if ($m['factura_id']): ?><a href="<?= url('facturas/ver', ['id' => $m['factura_id']]) ?>"><?= e($m['numero_completo']) ?></a><?php endif; ?></td>
                <td><?= e($m['usuario']) ?></td>
                <td><?= e($m['observacion']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$movimientos): ?><tr><td colspan="7" class="vacio">Sin movimientos.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
