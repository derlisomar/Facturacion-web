<?php
/**
 * Impresión sobre hoja preimpresa. Cada dato se ubica en milímetros según el formato de la empresa
 * (empresa.formato_impresion). Imprimir con márgenes "Ninguno" y escala 100%.
 */
$f = $formato;
$embebido = !empty($embebido);
$dx = (float) $f['desplazamiento']['x'];
$dy = (float) $f['desplazamiento']['y'];

$poner = function (array $pos, string $texto, float $copiaY, array $extra = [], string $atributos = '') use ($dx, $dy): string {
    $pos += $extra;
    if (($pos['visible'] ?? true) === false) {
        return '';
    }
    $alinear = $pos['alinear'] ?? 'izquierda';
    $clase = ['derecha' => ' der', 'centro' => ' centro'][$alinear] ?? '';
    $estilo = sprintf('left:%.1fmm;top:%.1fmm;', $pos['x'] + $dx, $pos['y'] + $dy + $copiaY);
    if (!empty($pos['ancho'])) {
        $estilo .= sprintf('width:%.1fmm;', $pos['ancho']);
    }
    if (!empty($pos['fuente'])) {
        $estilo .= sprintf('font-size:%.1fpt;', $pos['fuente']);
    }
    if (!empty($pos['negrita'])) {
        $estilo .= 'font-weight:bold;';
    }
    if (!empty($pos['multilinea'])) {
        $clase .= ' multi';
    }
    return '<div class="c' . $clase . '" style="' . $estilo . '"' . $atributos . '>' . e($texto) . '</div>';
};
$campo = fn(string $nombre) => $f['campos'][$nombre] ?? null;

$marca = $factura['condicion'] === 'CONTADO' ? 'contado' : ($f['marca_contado_fecha'] ?? 'contado');
$valores = [
    'fecha'           => date($f['formato_fecha'] ?? 'd/m/Y', strtotime($factura['fecha'])),
    $marca            => 'X',
    'razon_social'    => $factura['razon_social_cliente'],
    'ruc'             => $factura['ruc_cliente'],
    'direccion'       => $factura['direccion_cliente'],
    'telefono'        => $factura['telefono_cliente'],
    'fecha_pago'      => $factura['condicion'] === 'CONTADO_FECHA' ? 'Pago: ' . fecha($factura['fecha_pago']) : '',
    'subtotal_exenta' => $factura['total_exenta'] ? gs($factura['total_exenta']) : '',
    'subtotal_5'      => $factura['total_gravada5'] ? gs($factura['total_gravada5']) : '',
    'subtotal_10'     => $factura['total_gravada10'] ? gs($factura['total_gravada10']) : '',
    'total_letras'    => $factura['monto_letras'],
    'total'           => gs($factura['total']),
    'iva5'            => gs($factura['total_iva5']),
    'iva10'           => gs($factura['total_iva10']),
    'iva_total'       => gs($factura['total_iva5'] + $factura['total_iva10']),
    'observacion'     => trim((string) ($factura['observacion'] ?? '')),
];
$columnas = $f['detalle']['columnas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Imprimir factura</title>
<style>
    @page { size: <?= (float) $f['pagina']['ancho'] ?>mm <?= (float) $f['pagina']['alto'] ?>mm; margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: <?= (float) $f['fuente'] ?>pt; color: #000; background: #fff; }
    .hoja { position: relative; width: <?= (float) $f['pagina']['ancho'] ?>mm; height: <?= (float) $f['pagina']['alto'] ?>mm; overflow: hidden; }
    .c { position: absolute; white-space: nowrap; overflow: hidden; line-height: 1; }
    .c.der { transform: translateX(-100%); text-align: right; }
    .c.centro { transform: translateX(-50%); text-align: center; }
    .c.multi { white-space: normal; line-height: 1.15; overflow: visible; margin-top: -.075em; }
    .barra { position: fixed; top: 0; left: 0; right: 0; padding: 10px; background: #1f2937; color: #fff; font: 14px Arial; display: flex; gap: 12px; align-items: center; }
    .barra button { padding: 6px 14px; font-size: 14px; cursor: pointer; }
    <?php if ($prueba): ?>.hoja { outline: 1px dashed #999; } .c { background: rgba(255, 230, 0, .35); }<?php endif; ?>
    <?php if (!$embebido): ?>@media screen { body { background: #e5e7eb; padding-top: 60px; } .hoja { margin: 0 auto 20px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.2); } }<?php endif; ?>
    @media print { .barra { display: none; } }
    <?php if (!empty($fondo)): ?>@media screen { .hoja { background: #fff url("<?= e(url_base() . "/" . $fondo) ?>?v=<?= time() ?>") 0 0 / 100% 100% no-repeat; } }<?php endif; ?>
</style>
</head>
<body>
<?php if (!$embebido): ?>
<div class="barra">
    <button onclick="window.print()">Imprimir</button>
    <button onclick="window.close()">Cerrar</button>
    <?php if ($prueba): ?><a href="?<?= e(http_build_query(array_diff_key($_GET, ['fondo' => 1]) + (empty($fondo) ? ['fondo' => 1] : []))) ?>" style="color:#93c5fd"><?= empty($fondo) ? 'Ver sobre la imagen de la hoja' : 'Ver sin la imagen' ?></a><?php endif; ?>
    <span>Imprimí con márgenes <b>Ninguno</b>, escala <b>100%</b> y sin encabezados ni pies de página.</span>
</div>
<?php endif; ?>
<div class="hoja">
<?php foreach ($f['copias'] as $nCopia => $copiaY): $copiaY = (float) $copiaY; ?>
    <?php foreach ($valores as $nombre => $texto): ?>
        <?php if ($texto !== '' && $texto !== null && ($pos = $campo($nombre))
            && (!isset($pos['en_copias']) || in_array($nCopia + 1, (array) $pos['en_copias'], false))): ?>
            <?= $poner($pos, (string) $texto, $copiaY) ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php foreach ($detalle as $i => $d):
        $y = $f['detalle']['y'] + $i * $f['detalle']['alto_fila'];
        $col = fn(string $c) => ['x' => $columnas[$c]['x'], 'y' => $y] + $columnas[$c];
        $monto = gs($d['subtotal']);
        $fila = sprintf(' data-copia="%d" data-fila="%d"', $nCopia, $i);
    ?>
        <?= $poner($col('cantidad'), cantidad($d['cantidad']), $copiaY, [], $fila) ?>
        <?= $poner($col('descripcion'), $d['descripcion'], $copiaY, [], $fila . ' data-desc') ?>
        <?= $poner($col('precio'), gs($d['precio_unitario']), $copiaY, [], $fila) ?>
        <?= $poner($col((int) $d['iva'] === 10 ? 'iva10' : ((int) $d['iva'] === 5 ? 'iva5' : 'exenta')), $monto, $copiaY, [], $fila) ?>
    <?php endforeach; ?>
<?php endforeach; ?>
</div>
<script>
// Descripción larga: sigue en el renglón de abajo y los ítems siguientes bajan.
// Lo que no entra en los renglones de la hoja no se imprime (se avisa al abrir la vista previa).
(function () {
    const altoFila = <?= (float) $f['detalle']['alto_fila'] ?>, maxFilas = <?= (int) $f['detalle']['max_filas'] ?>;
    const regla = document.createElement('div');
    regla.style.cssText = 'position:absolute;visibility:hidden;height:100mm';
    document.body.appendChild(regla);
    const pxMm = regla.getBoundingClientRect().height / 100;
    regla.remove();
    const filas = {};
    document.querySelectorAll('[data-fila]').forEach((el) => {
        const k = el.dataset.copia + '-' + el.dataset.fila;
        (filas[k] = filas[k] || []).push(el);
    });
    let sobran = false;
    <?php foreach (array_keys($f['copias']) as $nCopia): ?>
    {
        let usadas = 0;
        for (let i = 0; filas['<?= $nCopia ?>-' + i]; i++) {
            const els = filas['<?= $nCopia ?>-' + i];
            const desc = els.find((el) => el.hasAttribute('data-desc'));
            const alto = desc ? desc.getBoundingClientRect().height / pxMm : 0;
            const ocupa = Math.max(1, Math.ceil((alto + 0.8) / altoFila)); // 0,8 mm de aire hasta el ítem siguiente
            const corrimiento = (usadas - i) * altoFila;
            els.forEach((el) => {
                el.style.top = (parseFloat(el.style.top) + corrimiento) + 'mm';
                if (usadas + ocupa > maxFilas) { el.style.display = 'none'; sobran = true; }
            });
            usadas += ocupa;
        }
    }
    <?php endforeach; ?>
    <?php if (!$embebido): ?>if (sobran) alert('El detalle de esta factura no entra en los ' + maxFilas + ' renglones de la hoja: lo que sobra no se imprime.');<?php endif; ?>
})();
</script>
<?php if (!$prueba && !$embebido): ?>
<script>
// Abierta en una pestaña aparte: imprime sola y, una vez impresa (o cancelada), la pestaña se cierra.
window.addEventListener('afterprint', () => setTimeout(() => window.close(), 100));
window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
<?php endif; ?>
</body>
</html>
