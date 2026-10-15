<h1 class="titulo">Datos de la empresa</h1>
<form method="post" action="<?= url('empresas/guardar') ?>" class="tarjeta formulario">
    <?= csrf_field() ?>
    <div class="fila">
        <label class="grande">Razón social *<input name="razon_social" value="<?= e($empresa['razon_social']) ?>" required></label>
        <label>Nombre en pantalla<input name="nombre_fantasia" value="<?= e($empresa['nombre_fantasia']) ?>"></label>
        <label>RUC *<input name="ruc" value="<?= e($empresa['ruc']) ?>" required></label>
    </div>
    <div class="fila">
        <label class="grande">Actividad<input name="actividad" value="<?= e($empresa['actividad']) ?>"></label>
        <label>Ciudad<input name="ciudad" value="<?= e($empresa['ciudad']) ?>"></label>
    </div>
    <div class="fila">
        <label class="grande">Dirección<input name="direccion" value="<?= e($empresa['direccion']) ?>"></label>
        <label>Teléfono<input name="telefono" value="<?= e($empresa['telefono']) ?>"></label>
        <label>Correo<input name="email" value="<?= e($empresa['email']) ?>"></label>
        <label>Color<input type="color" name="color" value="<?= e($empresa['color']) ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="permite_stock_negativo" value="1" <?= $empresa['permite_stock_negativo'] ? 'checked' : '' ?>>
        Permitir facturar productos sin stock suficiente</label>

    <h2>Formato de impresión (hoja preimpresa)</h2>
    <p><a class="btn btn-primario" href="<?= url('empresas/diseno') ?>"><i class="fas fa-ruler-combined"></i> Abrir el diseñador visual</a>
        <span class="gris">Arrastrá cada campo sobre la imagen de tu hoja preimpresa. Lo de abajo es el mismo diseño en texto, para usuarios avanzados.</span></p>
    <p class="gris">Posiciones en milímetros desde la esquina superior izquierda de la hoja. Para corregir un corrimiento de la impresora, cambiá solo <code>desplazamiento</code>.
        <a href="<?= url('facturas/prueba-impresion') ?>" target="_blank">Imprimir prueba</a> sobre una hoja en blanco y comparala a contraluz con una preimpresa.</p>
    <textarea name="formato_impresion" rows="22" class="codigo"><?= e($formato) ?></textarea>
    <div class="botones"><button class="btn btn-primario">Guardar</button></div>
</form>

<?php
$ed = $editando ?? null;
$usado = $ed && (int) $ed['facturas'] > 0;
$v = fn($k, $def = '') => e((string) ($ed[$k] ?? $def));
?>
<div class="tarjeta" id="timbrados">
    <h2>Timbrados</h2>
    <table class="tabla">
        <thead><tr><th>Timbrado</th><th>Vigencia</th><th>Est.-Punto</th><th>Rango</th><th>Facturas</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($timbrados as $t): ?>
            <tr class="<?= $t['activo'] ? '' : 'inactivo' ?><?= $ed && (int) $ed['id'] === (int) $t['id'] ? ' editando' : '' ?>">
                <td><?= e($t['numero']) ?></td>
                <td><?= fecha($t['vigencia_desde']) ?> al <?= fecha($t['vigencia_hasta']) ?></td>
                <td><?= e($t['establecimiento'] . '-' . $t['punto_expedicion']) ?></td>
                <td><?= (int) $t['numero_desde'] ?> a <?= (int) $t['numero_hasta'] ?></td>
                <td><?= (int) $t['facturas'] ?></td>
                <td><?= $t['activo'] ? 'Activo' : 'Inactivo' ?></td>
                <td class="der acciones-timbrado">
                    <a class="btn btn-chico" href="<?= url('empresas/editar', ['timbrado' => (int) $t['id']]) ?>#form-timbrado" title="Editar"><i class="fas fa-pen"></i> Editar</a>
                    <form method="post" action="<?= url('empresas/timbrado-estado') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="activo" value="<?= $t['activo'] ? '0' : '1' ?>">
                        <button class="btn btn-chico"><?= $t['activo'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                    <?php if ((int) $t['facturas'] === 0): ?>
                    <form method="post" action="<?= url('empresas/timbrado-borrar') ?>" data-confirmar="¿Eliminar el timbrado <?= e($t['numero']) ?> (<?= e($t['establecimiento'] . '-' . $t['punto_expedicion']) ?>)? No se puede deshacer." data-peligro data-si="Sí, eliminar">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button class="btn btn-chico btn-peligro" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-chico" disabled title="Tiene facturas emitidas: no se puede eliminar, solo desactivar"><i class="fas fa-lock"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" action="<?= url('empresas/timbrado') ?>" class="formulario" id="form-timbrado">
        <?= csrf_field() ?>
        <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int) $ed['id'] ?>"><?php endif; ?>
        <h3><?= $ed ? 'Editar timbrado ' . e($ed['numero']) : 'Agregar timbrado' ?></h3>
        <?php if ($usado): ?>
        <p class="gris">Tiene <?= (int) $ed['facturas'] ?> factura(s) emitida(s) (del N.º <?= (int) $ed['primer_usado'] ?> al <?= (int) $ed['ultimo_usado'] ?>): el establecimiento y el punto no se pueden cambiar.</p>
        <?php endif; ?>
        <div class="fila">
            <label>Número *<input name="numero" required value="<?= $v('numero') ?>"></label>
            <label>Vigencia desde *<input type="date" name="vigencia_desde" required value="<?= $v('vigencia_desde') ?>"></label>
            <label>Vigencia hasta *<input type="date" name="vigencia_hasta" required value="<?= $v('vigencia_hasta') ?>"></label>
            <label>Establecimiento<input name="establecimiento" value="<?= $v('establecimiento', '001') ?>" maxlength="3"<?= $usado ? ' readonly' : '' ?>></label>
            <label>Punto de expedición<input name="punto_expedicion" value="<?= $v('punto_expedicion', '001') ?>" maxlength="3"<?= $usado ? ' readonly' : '' ?>></label>
            <label>Desde N.º<input name="numero_desde" value="<?= $v('numero_desde', '1') ?>"></label>
            <label>Hasta N.º<input name="numero_hasta" value="<?= $v('numero_hasta', '9999999') ?>"></label>
        </div>
        <div class="botones">
            <?php if ($ed): ?><a class="btn" href="<?= url('empresas/editar') ?>#timbrados">Cancelar</a><?php endif; ?>
            <button class="btn btn-primario"><?= $ed ? 'Guardar cambios' : 'Agregar' ?></button>
        </div>
    </form>
</div>
