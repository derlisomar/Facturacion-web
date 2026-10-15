<div class="encabezado">
    <div>
        <h1 class="titulo">Productos y servicios</h1>
        <p class="gris" style="margin:4px 0 0">De <strong><?= e(\App\Core\Auth::empresa()['nombre']) ?></strong>. Cada empresa tiene su propio catálogo y solo ve el suyo al facturar.</p>
    </div>
    <?php if (\App\Core\Auth::puedeFacturar()): ?>
        <a href="<?= url('productos/nuevo') ?>" class="btn btn-primario"><i class="fas fa-plus"></i> Nuevo</a>
    <?php endif; ?>
</div>
<form class="filtros" method="get" action="<?= url('productos') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="productos"><?php endif; ?>
    <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por descripción o código">
    <select name="tipo">
        <option value="">Todos</option>
        <option value="P" <?= $tipo === 'P' ? 'selected' : '' ?>>Productos (con stock)</option>
        <option value="S" <?= $tipo === 'S' ? 'selected' : '' ?>>Servicios</option>
    </select>
    <button class="btn">Buscar</button>
</form>
<?php $marcar = \App\Core\Auth::puedeFacturar(); ?>
<form method="post" action="<?= url('productos/desactivar') ?>" class="tarjeta" id="form-ocultar"
      data-confirmar="Los productos marcados no se borran: dejan de aparecer al facturar y se pueden volver a activar desde Editar." data-peligro data-si="Sí, ocultar">
    <?= csrf_field() ?>
    <?php if ($marcar): ?>
    <div class="barra-marcados" id="barra-marcados" hidden>
        <span><strong id="cant-marcados">0</strong> marcado(s)</span>
        <button class="btn btn-chico btn-peligro"><i class="fas fa-eye-slash"></i> Ocultar de esta empresa</button>
    </div>
    <?php endif; ?>
    <table class="tabla">
        <thead><tr><?php if ($marcar): ?><th style="width:28px"><input type="checkbox" id="marcar-todos" title="Marcar todos"></th><?php endif; ?><th>Cód.</th><th>Descripción</th><th>Tipo</th><th class="der">Precio</th><th class="der">IVA</th><th class="der">Stock</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <tr class="<?= $p['activo'] ? '' : 'inactivo' ?>">
                <?php if ($marcar): ?><td><?php if ($p['activo']): ?><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" class="marca"><?php endif; ?></td><?php endif; ?>
                <td><?= e($p['codigo_corto']) ?></td>
                <td><?= e($p['descripcion']) ?></td>
                <td><span class="etiqueta <?= $p['tipo'] === 'P' ? 'azul' : 'gris' ?>"><?= $p['tipo'] === 'P' ? 'Producto' : 'Servicio' ?></span></td>
                <td class="der"><?= gs($p['precio_venta']) ?></td>
                <td class="der"><?= $p['iva'] ? (int) $p['iva'] . '%' : 'Exenta' ?></td>
                <td class="der <?= $p['tipo'] === 'P' && $p['stock_actual'] <= $p['stock_minimo'] ? 'rojo' : '' ?>">
                    <?= $p['tipo'] === 'P' ? cantidad($p['stock_actual']) : '—' ?>
                </td>
                <td class="der">
                    <?php if ($p['tipo'] === 'P'): ?><a href="<?= url('productos/stock', ['id' => $p['id']]) ?>" title="Stock"><i class="fas fa-boxes-stacked"></i></a><?php endif; ?>
                    <?php if (\App\Core\Auth::puedeFacturar()): ?><a href="<?= url('productos/editar', ['id' => $p['id']]) ?>" title="Editar"><i class="fas fa-pen"></i></a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$productos): ?><tr><td colspan="<?= $marcar ? 8 : 7 ?>" class="vacio">No hay productos cargados.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php $ruta = 'productos'; $porPagina = 25; $params = ['q' => $busqueda, 'tipo' => $tipo]; require __DIR__ . '/../parciales/paginacion.php'; ?>
</form>
<?php if ($marcar): ?>
<script>
(function () {
    const marcas = [...document.querySelectorAll('.marca')], todos = document.getElementById('marcar-todos');
    const actualizar = () => {
        const n = marcas.filter((m) => m.checked).length;
        document.getElementById('cant-marcados').textContent = n;
        document.getElementById('barra-marcados').hidden = n === 0;
    };
    marcas.forEach((m) => m.addEventListener('change', actualizar));
    todos.addEventListener('change', () => { marcas.forEach((m) => { m.checked = todos.checked; }); actualizar(); });
})();
</script>
<?php endif; ?>
