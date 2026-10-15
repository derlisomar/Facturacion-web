<div class="encabezado">
    <h1 class="titulo">Clientes</h1>
    <?php if (\App\Core\Auth::puedeFacturar()): ?>
        <a href="<?= url('clientes/nuevo') ?>" class="btn btn-primario"><i class="fas fa-plus"></i> Nuevo cliente</a>
    <?php endif; ?>
</div>
<form class="filtros" method="get" action="<?= url('clientes') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="clientes"><?php endif; ?>
    <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar por RUC o razón social">
    <button class="btn">Buscar</button>
</form>
<div class="tarjeta">
    <table class="tabla">
        <thead><tr><th>RUC / CI</th><th>Razón social</th><th>Teléfono</th><th>Dirección</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clientes as $c): ?>
            <tr class="<?= $c['activo'] ? '' : 'inactivo' ?>">
                <td><?= e($c['ruc']) ?></td>
                <td><?= e($c['razon_social']) ?></td>
                <td><?= e($c['telefono'] ?: $c['celular']) ?></td>
                <td><?= e($c['direccion']) ?></td>
                <td class="der">
                    <a href="<?= url('facturas', ['q' => $c['ruc'], 'desde' => '2000-01-01', 'hasta' => date('Y-m-d')]) ?>" title="Ver facturas"><i class="fas fa-file-invoice"></i></a>
                    <?php if (\App\Core\Auth::puedeFacturar()): ?>
                        <a href="<?= url('clientes/editar', ['id' => $c['id']]) ?>" title="Editar"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?><tr><td colspan="5" class="vacio">No se encontraron clientes.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php $ruta = 'clientes'; $porPagina = 25; $params = ['q' => $busqueda]; require __DIR__ . '/../parciales/paginacion.php'; ?>
</div>
