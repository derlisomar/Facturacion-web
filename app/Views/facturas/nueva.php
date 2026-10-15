<div class="encabezado">
    <div>
        <h1 class="titulo">Nueva venta</h1>
        <p class="gris" style="margin:4px 0 0"><span class="pdv-punto"></span><?= e($empresa['nombre_fantasia'] ?: $empresa['razon_social']) ?> · F2 filtra productos, F4 busca clientes</p>
    </div>
</div>

<?php if (!$timbrado): ?>
    <div class="alerta alerta-error">
        No hay un timbrado activo y vigente para hoy. <?= \App\Core\Auth::esAdmin() ? '<a href="' . url('empresas/editar') . '">Cargá el timbrado</a>.' : 'Avisá al administrador.' ?>
    </div>
<?php else: ?>
<div id="facturador" class="pdv"
     data-max-desc="<?= \App\Models\Factura::largoDescripcion() ?>"
     data-url-clientes="<?= url('clientes/buscar') ?>"
     data-url-cliente-nuevo="<?= url('clientes/rapido') ?>"
     data-url-padron="<?= url('padron/ruc') ?>"
     data-url-productos="<?= url('productos/buscar') ?>"
     data-url-numero="<?= url('facturas/verificar-numero') ?>"
     data-url-guardar="<?= url('facturas/guardar') ?>"
     data-catalogo="<?= e(json_encode($catalogo, JSON_UNESCAPED_UNICODE)) ?>"
     data-stock-negativo="<?= $empresa['permite_stock_negativo'] ? '1' : '0' ?>">

    <!-- Izquierda: comprobante, buscador y carrito -->
    <div class="pdv-izq">
        <div class="tarjeta pdv-comprobante">
            <div class="pdv-nro">
                <span class="pdv-etq">N.º de factura (hoja en la impresora) *</span>
                <div class="numero-factura">
                    <span><?= e($timbrado['establecimiento'] . '-' . $timbrado['punto_expedicion']) ?>-</span>
                    <input type="number" id="numero" value="<?= (int) $numeroSugerido ?>" min="<?= (int) $timbrado['numero_desde'] ?>" max="<?= (int) $timbrado['numero_hasta'] ?>" required>
                </div>
                <small class="pdv-numero-usado" id="avisoNumero" hidden></small>
            </div>
            <label class="pdv-fecha"><span class="pdv-etq">Fecha</span><input type="date" id="fecha" value="<?= date('Y-m-d') ?>"></label>
            <div class="pdv-timbrado">
                <span class="pdv-etq">Timbrado</span>
                <strong><?= e($timbrado['numero']) ?></strong>
                <small class="gris">Vence <?= fecha($timbrado['vigencia_hasta']) ?></small>
            </div>
            <details class="hoja-danada">
                <summary>¿Se arruinó una hoja?</summary>
                <form method="post" action="<?= url('facturas/hoja-anulada') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="fecha" value="<?= date('Y-m-d') ?>">
                    <input type="number" name="numero" placeholder="Número de la hoja" required>
                    <input name="motivo" placeholder="Motivo (opcional)">
                    <button class="btn btn-peligro" data-confirmar="¿Registrar ese número de hoja como anulado? Queda en el libro de ventas como hoja anulada.">Registrar como anulada</button>
                </form>
            </details>
        </div>

        <div class="tarjeta pdv-catalogo">
            <div class="pdv-buscar">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="buscarProducto" placeholder="Filtrar por nombre o código… (Enter agrega el primero)" autocomplete="off">
                <kbd>F2</kbd>
            </div>
            <div class="pdv-tarjetas" id="tarjetasProductos"></div>
            <div class="pdv-vacio chico" id="catalogoVacio" hidden>
                <i class="fas fa-box-open"></i>
                <strong><?= $catalogo ? 'Ningún producto coincide con la búsqueda' : 'Esta empresa todavía no tiene productos' ?></strong>
                <?php if (!$catalogo): ?><a href="<?= url('productos/nuevo') ?>">Cargar un producto o servicio</a><?php endif; ?>
            </div>
        </div>

        <div class="tarjeta pdv-carrito">
            <div class="pdv-carrito-cab">
                <h2><i class="fas fa-cart-shopping"></i> Detalle de la venta</h2>
                <span class="etiqueta azul" id="cantidadItems">0 ítems</span>
            </div>
            <table class="tabla tabla-items">
                <thead><tr><th class="col-desc">Producto / descripción</th><th class="der">Cant.</th><th class="der">Precio unit.</th><th class="der">IVA</th><th class="der">Subtotal</th><th></th></tr></thead>
                <tbody id="items"></tbody>
            </table>
            <div class="pdv-vacio" id="carritoVacio">
                <i class="fas fa-cart-shopping"></i>
                <strong>El carrito está vacío</strong>
                <span>Buscá un producto o servicio arriba para empezar la venta.</span>
            </div>
        </div>
    </div>

    <!-- Derecha: resumen de venta -->
    <aside class="tarjeta pdv-der">
        <h2>Resumen de venta</h2>

        <span class="pdv-etq">Cliente *</span>
        <div class="pdv-cliente-buscar" id="cajaBuscarCliente">
            <div class="pdv-buscar chico">
                <i class="fas fa-user"></i>
                <input type="search" id="buscarCliente" placeholder="Seleccione o busque un cliente..." autocomplete="off">
                <ul class="pdv-lista" id="sugerenciasCliente" hidden></ul>
            </div>
            <button type="button" class="pdv-mas" id="btnNuevoCliente" title="Registrar nuevo cliente"><i class="fas fa-plus"></i></button>
        </div>
        <div class="pdv-cliente" id="clienteSeleccionado" hidden></div>

        <div class="pdv-campos">
            <label><span class="pdv-etq">Condición</span>
                <select id="condicion">
                    <option value="CONTADO">Contado</option>
                    <option value="CONTADO_FECHA">Contado con fecha de pago</option>
                </select>
            </label>
            <label id="bloqueMetodo"><span class="pdv-etq">Método de pago</span>
                <select id="metodoPago">
                    <option value="EFECTIVO">Efectivo</option>
                    <option value="TRANSFERENCIA">Transferencia bancaria</option>
                </select>
            </label>
            <label id="bloqueReferencia" hidden><span class="pdv-etq">N.º de comprobante de la transferencia</span>
                <input id="referencia" maxlength="100" placeholder="Opcional">
            </label>
            <label id="bloqueFechaPago" hidden><span class="pdv-etq">Fecha en que va a pagar *</span>
                <input type="date" id="fechaPago" min="<?= date('Y-m-d') ?>">
            </label>
            <label><span class="pdv-etq">Observación</span><input id="observacion" maxlength="300"></label>
        </div>

        <div class="totales">
            <div><span>Exenta</span><strong id="totExenta">0</strong></div>
            <div><span>Gravada 5%</span><strong id="totG5">0</strong></div>
            <div><span>Gravada 10%</span><strong id="totG10">0</strong></div>
            <div><span>IVA 5%</span><strong id="totIva5">0</strong></div>
            <div><span>IVA 10%</span><strong id="totIva10">0</strong></div>
        </div>
        <div class="pdv-total">
            <span>Total a pagar</span>
            <strong>₲ <span id="totTotal">0</span></strong>
        </div>
        <button type="button" class="pdv-procesar" id="btnGuardar"><i class="fas fa-print"></i> Guardar e imprimir</button>
        <p class="error-form" id="errorFactura" hidden></p>
    </aside>
</div>

<!-- Alta de cliente -->
<div class="modal" id="modalCliente" hidden>
    <div class="modal-caja">
        <div class="modal-cab">
            <h2><i class="fas fa-user-plus"></i> Registrar nuevo cliente</h2>
            <button type="button" class="modal-cerrar" data-cerrar title="Cerrar">&times;</button>
        </div>
        <p class="modal-aviso" id="avisoNoCliente" hidden></p>
        <div class="formulario">
            <label>RUC / CI *
                <input id="cr_ruc" placeholder="80012345-6" maxlength="20" autocomplete="off">
            </label>
            <div class="rp-aviso" id="cr_padron" hidden></div>
            <label>Razón social / Nombre *<input id="cr_razon" maxlength="150"></label>
            <label>Dirección<input id="cr_direccion" maxlength="200"></label>
            <div class="fila">
                <label>Teléfono<input id="cr_telefono" maxlength="50"></label>
                <label>Celular<input id="cr_celular" maxlength="50"></label>
            </div>
            <label>Correo<input type="email" id="cr_email" maxlength="100"></label>
            <p class="error-form" id="errorCliente" hidden></p>
            <div class="botones">
                <button type="button" class="btn" data-cerrar>Cancelar</button>
                <button type="button" class="btn btn-primario" id="btnGuardarCliente"><i class="fas fa-floppy-disk"></i> Guardar y usar</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/ruc-padron.js') ?>" defer></script>
<script src="<?= asset('js/factura.js') ?>" defer></script>
<?php endif; ?>
