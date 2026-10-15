<div class="encabezado">
    <h1 class="titulo">Seguimiento de cobros</h1>
    <div class="stock-grande">Pendiente: <strong><?= gs($resumen['saldo']) ?> ₲</strong> · <?= (int) $resumen['vencidas'] ?> vencidas</div>
</div>
<form class="filtros" method="get" action="<?= url('seguimiento') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="seguimiento"><?php endif; ?>
    <select name="filtro">
        <option value="todas" <?= $filtro === 'todas' ? 'selected' : '' ?>>Todas las pendientes</option>
        <option value="vencidas" <?= $filtro === 'vencidas' ? 'selected' : '' ?>>Vencidas</option>
        <option value="semana" <?= $filtro === 'semana' ? 'selected' : '' ?>>Vencen en 7 días</option>
    </select>
    <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Cliente, RUC o número">
    <button class="btn">Filtrar</button>
</form>
<div class="tarjeta">
    <table class="tabla">
        <thead><tr><th>Fecha de pago</th><th>Factura</th><th>Cliente</th><th>Teléfono</th><th class="der">Total</th><th class="der">Saldo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($facturas as $f):
            $dias = (int) $f['dias'];
            $estado = $dias < 0 ? ['rojo', 'Vencida hace ' . abs($dias) . ' días'] : ($dias === 0 ? ['naranja', 'Vence hoy'] : ($dias <= 7 ? ['naranja', 'En ' . $dias . ' días'] : ['gris', 'En ' . $dias . ' días']));
        ?>
            <tr>
                <td><?= fecha($f['fecha_pago']) ?><br><span class="etiqueta <?= $estado[0] ?>"><?= $estado[1] ?></span></td>
                <td><a href="<?= url('facturas/ver', ['id' => $f['id']]) ?>"><?= e($f['numero_completo']) ?></a><br><small class="gris"><?= fecha($f['fecha']) ?></small></td>
                <td><?= e($f['razon_social_cliente']) ?><br><small class="gris"><?= e($f['ruc_cliente']) ?></small></td>
                <td><?= e($f['telefono_cliente']) ?></td>
                <td class="der"><?= gs($f['total']) ?></td>
                <td class="der rojo"><?= gs($f['saldo']) ?></td>
                <td class="der">
                    <?php if (\App\Core\Auth::puedeFacturar()): ?>
                    <details class="cobro-rapido">
                        <summary class="btn btn-chico">Cobrar</summary>
                        <form method="post" action="<?= url('seguimiento/cobrar') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="factura_id" value="<?= (int) $f['id'] ?>">
                            <input type="date" name="fecha" value="<?= date('Y-m-d') ?>">
                            <input name="monto" value="<?= (int) $f['saldo'] ?>" class="monto" inputmode="numeric">
                            <select name="metodo_pago"><option>EFECTIVO</option><option>TRANSFERENCIA</option></select>
                            <input name="referencia" placeholder="Referencia">
                            <button class="btn btn-primario btn-chico">Registrar</button>
                        </form>
                    </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$facturas): ?><tr><td colspan="7" class="vacio">No hay facturas pendientes de cobro.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
