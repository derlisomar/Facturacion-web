<div class="encabezado">
    <div>
        <a href="<?= url('empresas/editar') ?>" class="volver"><i class="fas fa-arrow-left"></i> Datos de la empresa</a>
        <h1 class="titulo">Diseño de impresión · <?= e($empresa['nombre_fantasia'] ?: $empresa['razon_social']) ?></h1>
        <p class="gris" style="margin:4px 0 0">Arrastrá cada campo a su lugar sobre la hoja. Con las flechas del teclado se mueve de a 0,5 mm (con Shift de a 5 mm, con Alt de a 0,1 mm).</p>
    </div>
    <div class="botones-consulta">
        <select id="dz-plantilla" class="btn" title="Reemplaza todas las posiciones por las de una hoja conocida">
            <option value="">Cargar plantilla…</option>
            <?php foreach ($plantillas as $clave => $pl): ?><option value="<?= e($clave) ?>"><?= e($pl['nombre']) ?></option><?php endforeach; ?>
        </select>
        <button type="button" class="btn" id="dz-prueba"><i class="fas fa-print"></i> Imprimir prueba</button>
        <button type="button" class="btn btn-primario" id="dz-guardar"><i class="fas fa-floppy-disk"></i> Guardar diseño</button>
    </div>
</div>

<div class="dz">
    <!-- Lista de campos -->
    <aside class="tarjeta dz-lista">
        <h2>Campos</h2>
        <p class="gris dz-ayuda">Tildá los que se imprimen. Tocá uno para editarlo.</p>
        <div id="dz-campos"></div>
        <h2 style="margin-top:14px">Detalle (renglones)</h2>
        <div id="dz-columnas"></div>
    </aside>

    <!-- Hoja -->
    <section class="tarjeta dz-centro">
        <div class="dz-barra">
            <label>Zoom
                <select id="dz-zoom">
                    <option value="0">Ajustar al ancho</option>
                    <option value="2">2 px/mm</option><option value="3">3 px/mm</option>
                    <option value="4">4 px/mm</option><option value="6">6 px/mm</option>
                </select>
            </label>
            <label class="check"><input type="checkbox" id="dz-grilla" checked> Grilla cada 10 mm</label>
            <label class="check"><input type="checkbox" id="dz-copias" checked> Ver duplicado y triplicado</label>
            <?php if ($fondo): ?>
                <label>Imagen <input type="range" id="dz-opacidad" min="0" max="100" value="70"></label>
            <?php endif; ?>
            <span class="gris" id="dz-cursor">—</span>
        </div>
        <div class="dz-scroll" id="dz-scroll">
            <div class="dz-hoja" id="dz-hoja">
                <?php if ($fondo): ?><img class="dz-fondo" id="dz-fondo" src="<?= e(url_base() . '/' . $fondo) ?>?v=<?= filemtime(BASE_PATH . '/public/' . $fondo) ?>" alt=""><?php endif; ?>
            </div>
        </div>
        <div class="dz-fondo-form">
            <form method="post" action="<?= url('empresas/diseno-fondo') ?>" enctype="multipart/form-data" class="fila-inline">
                <?= csrf_field() ?>
                <span class="gris"><i class="fas fa-image"></i> Imagen de la hoja preimpresa (escaneada o foto derecha, JPG o PNG):</span>
                <input type="file" name="fondo" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-chico"><?= $fondoPropio ? 'Reemplazar' : 'Subir' ?></button>
            </form>
            <?php if ($fondoPropio): ?>
            <form method="post" action="<?= url('empresas/diseno-fondo-borrar') ?>" data-confirmar="¿Quitar la imagen de fondo?" data-peligro>
                <?= csrf_field() ?><button class="btn-link rojo">Quitar imagen</button>
            </form>
            <?php endif; ?>
            <?php if ($fondo && !$fondoPropio): ?><p class="gris dz-ayuda">Se muestra la hoja oficio de ejemplo de la plantilla. Si la hoja de esta empresa es otra, subí su imagen.</p><?php endif; ?>
            <p class="gris dz-ayuda">La imagen solo sirve de guía en pantalla, nunca se imprime. Escaneala a tamaño real (100 %) para que coincida con las medidas de la hoja.</p>
        </div>
    </section>

    <!-- Propiedades -->
    <aside class="dz-panel">
        <div class="tarjeta" id="dz-propiedades">
            <h2>Campo seleccionado</h2>
            <p class="gris">Tocá un campo en la hoja o en la lista.</p>
        </div>
        <div class="tarjeta formulario">
            <h2>Hoja</h2>
            <div class="fila">
                <label>Ancho (mm)<input type="number" step="0.1" data-general="pagina.ancho"></label>
                <label>Alto (mm)<input type="number" step="0.1" data-general="pagina.alto"></label>
            </div>
            <div class="fila">
                <label>Letra general (pt)<input type="number" step="0.5" min="5" max="20" data-general="fuente"></label>
                <label>Formato de fecha
                    <select data-general="formato_fecha">
                        <option value="d/m/y">13/10/26</option><option value="d/m/Y">13/10/2026</option><option value="d-m-Y">13-10-2026</option>
                    </select>
                </label>
            </div>
            <label>Copias en la misma hoja: corrimiento vertical de cada una (mm, separados por ;)
                <input type="text" id="dz-lista-copias" placeholder="0; 110.1; 217.6">
            </label>
            <label>Factura con fecha de pago marca la casilla
                <select data-general="marca_contado_fecha"><option value="contado">Contado</option><option value="credito">Crédito</option></select>
            </label>
            <h2>Corrección de la impresora</h2>
            <p class="gris dz-ayuda">Si todo sale corrido para el mismo lado, no muevas los campos: corregí acá. Positivo = derecha / abajo.</p>
            <div class="fila">
                <label>Horizontal (mm)<input type="number" step="0.1" data-general="desplazamiento.x"></label>
                <label>Vertical (mm)<input type="number" step="0.1" data-general="desplazamiento.y"></label>
            </div>
        </div>
    </aside>
</div>

<div class="dz-aviso" id="dz-aviso" hidden></div>

<script>
window.DISENO = <?= json_encode([
    'formato'  => $formato,
    'defecto'  => $defecto,
    'plantillas' => array_map(fn($pl) => $pl['formato'], $plantillas),
    'guardar'  => url('empresas/diseno-guardar'),
    'prueba'   => url('facturas/prueba-impresion', $fondo ? ['fondo' => 1] : []),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= asset('js/diseno.js') ?>"></script>
