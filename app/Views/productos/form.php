<h1 class="titulo"><?= e($titulo) ?></h1>
<form method="post" action="<?= url('productos/guardar') ?>" class="tarjeta formulario">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($producto['id']) ?>">
    <div class="fila">
        <label>Tipo *
            <select name="tipo" id="tipoProducto">
                <option value="S" <?= $producto['tipo'] === 'S' ? 'selected' : '' ?>>Servicio (sin stock)</option>
                <option value="P" <?= $producto['tipo'] === 'P' ? 'selected' : '' ?>>Producto físico (controla stock)</option>
            </select>
        </label>
        <label>Código
            <input name="codigo_corto" value="<?= e($producto['codigo_corto']) ?>" maxlength="30">
            <?php if (!$producto['id']): ?><small class="gris">Sugerido: el siguiente al último que usaste. Lo podés cambiar.</small><?php endif; ?>
        </label>
    </div>
    <label>Descripción *<input name="descripcion" value="<?= e($producto['descripcion']) ?>" required maxlength="200"></label>
    <div class="fila">
        <label>Precio de venta (₲) *<input name="precio_venta" value="<?= e($producto['precio_venta']) ?>" inputmode="numeric" class="monto" required></label>
        <label>IVA
            <select name="iva">
                <?php foreach ([10 => '10%', 5 => '5%', 0 => 'Exenta'] as $v => $t): ?>
                    <option value="<?= $v ?>" <?= (int) $producto['iva'] === $v ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="fila solo-producto">
        <?php if (!$producto['id']): ?>
            <label>Stock inicial<input name="stock_inicial" value="0" inputmode="decimal"></label>
        <?php else: ?>
            <label>Stock actual<input value="<?= cantidad($producto['stock_actual']) ?>" disabled>
                <small>Se modifica desde <a href="<?= url('productos/stock', ['id' => $producto['id']]) ?>">Stock</a> (entrada o ajuste).</small></label>
        <?php endif; ?>
        <label>Stock mínimo (alerta)<input name="stock_minimo" value="<?= cantidad($producto['stock_minimo']) ?>" inputmode="decimal"></label>
    </div>
    <?php if ($producto['id']): ?>
        <label class="check"><input type="checkbox" name="activo" value="1" <?= $producto['activo'] ? 'checked' : '' ?>> Activo</label>
    <?php endif; ?>
    <div class="botones">
        <a href="<?= url('productos') ?>" class="btn">Cancelar</a>
        <button class="btn btn-primario">Guardar</button>
    </div>
</form>
