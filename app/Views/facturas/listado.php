<?php
$estados = ['EMITIDA' => 'No anuladas', 'ANULADA' => 'Anuladas', '' => 'Todas'];
$variasEmpresas = $filtros['empresa'] === 'todas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Listado de facturas <?= fecha($filtros['desde']) ?> al <?= fecha($filtros['hasta']) ?></title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; margin: 0; }
    h1 { font-size: 13pt; margin: 0 0 2px; }
    .sub { color: #444; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { background: #eee; text-align: left; border-bottom: 1px solid #000; padding: 3px 4px; font-size: 8pt; }
    td { border-bottom: 1px solid #ddd; padding: 3px 4px; }
    .der { text-align: right; }
    .anulada td { color: #888; text-decoration: line-through; }
    tfoot td { font-weight: bold; border-top: 2px solid #000; border-bottom: 0; }
    .barra { padding: 10px; background: #1f2937; display: flex; gap: 10px; margin-bottom: 12px; }
    .barra button { padding: 6px 14px; cursor: pointer; }
    @media print { .barra { display: none; } }
</style>
</head>
<body>
<div class="barra"><button onclick="window.print()">Imprimir / Guardar PDF</button><button onclick="window.close()">Cerrar</button></div>
<h1>Listado de facturas · <?= e($variasEmpresas ? 'Todas las empresas' : $empresa['nombre']) ?></h1>
<div class="sub">Del <?= fecha($filtros['desde']) ?> al <?= fecha($filtros['hasta']) ?> · <?= e($estados[$filtros['estado']] ?? 'Todas') ?>
    <?= $filtros['busqueda'] !== '' ? ' · Búsqueda: ' . e($filtros['busqueda']) : '' ?> · Impreso el <?= date('d/m/Y H:i') ?></div>
<table>
    <thead><tr>
        <th>Número</th><th>Fecha</th><th>RUC</th><th>Razón social</th><th>Condición</th><th>Método</th>
        <?= $variasEmpresas ? '<th>Empresa</th>' : '' ?><th class="der">IVA</th><th class="der">Total</th><th>Estado</th>
    </tr></thead>
    <tbody>
    <?php foreach ($facturas as $f): ?>
        <tr class="<?= $f['estado'] === 'ANULADA' ? 'anulada' : '' ?>">
            <td><?= e($f['numero_completo']) ?></td>
            <td><?= fecha($f['fecha']) ?></td>
            <td><?= e($f['ruc_cliente']) ?></td>
            <td><?= e($f['razon_social_cliente']) ?></td>
            <td><?= $f['condicion'] === 'CONTADO' ? 'Contado' : 'Pago ' . fecha($f['fecha_pago']) ?></td>
            <td><?= e($f['metodo_pago'] ?? '') ?></td>
            <?php if ($variasEmpresas): ?><td><?= e($f['empresa_nombre']) ?></td><?php endif; ?>
            <td class="der"><?= gs($f['total_iva5'] + $f['total_iva10']) ?></td>
            <td class="der"><?= gs($f['total']) ?></td>
            <td><?= e($f['estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr>
        <td colspan="<?= $variasEmpresas ? 7 : 6 ?>">Cantidad: <?= (int) $total ?> facturas</td>
        <td class="der"><?= gs($iva) ?></td><td class="der"><?= gs($monto) ?></td><td></td>
    </tr></tfoot>
</table>
<p style="font-size:8pt;color:#444">El total no incluye facturas anuladas.</p>
</body>
</html>
