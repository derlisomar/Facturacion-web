// Pantalla de venta (estilo punto de venta): el carrito vive en el navegador y se envía completo al confirmar.
(function () {
    const raiz = document.getElementById('facturador');
    if (!raiz) return;

    const csrf = document.querySelector('meta[name="csrf"]').content;
    const permiteNegativo = raiz.dataset.stockNegativo === '1';
    const gs = (n) => Math.round(n).toLocaleString('es-PY');
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const pareceRuc = (q) => /^\d{3,12}(\s*-\s*\d?)?$/.test(q);
    const conQ = (url, q) => url + (url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q);

    const maxDesc = Number(raiz.dataset.maxDesc) || 200;
    let cliente = null;
    let items = [];

    // ---------- Listas desplegables con teclado ----------
    // conVacio: al tocar el campo vacío ya muestra la lista (alBuscar recibe '').
    function lista(input, ul, alBuscar, conVacio = false) {
        let temporizador;
        const cerrar = () => { ul.hidden = true; ul.innerHTML = ''; };
        const buscar = () => {
            clearTimeout(temporizador);
            const q = input.value.trim();
            if (q.length < 2 && !(conVacio && q === '')) { cerrar(); return; }
            temporizador = setTimeout(async () => {
                const html = await alBuscar(q);
                if (input.value.trim() !== q) return;
                ul.innerHTML = html;
                ul.hidden = !html;
                const primero = ul.querySelector('[data-elegir]');
                if (primero) primero.classList.add('activo');
            }, q === '' ? 0 : 220);
        };
        input.addEventListener('input', buscar);
        if (conVacio) {
            input.addEventListener('focus', () => { if (ul.hidden) buscar(); });
            input.addEventListener('click', () => { if (ul.hidden) buscar(); });
        }
        input.addEventListener('keydown', (e) => {
            const opciones = [...ul.querySelectorAll('[data-elegir]')];
            const actual = opciones.findIndex((li) => li.classList.contains('activo'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!opciones.length) return;
                const sig = (actual + (e.key === 'ArrowDown' ? 1 : -1) + opciones.length) % opciones.length;
                opciones.forEach((li) => li.classList.remove('activo'));
                opciones[sig].classList.add('activo');
                opciones[sig].scrollIntoView({ block: 'nearest' });
            }
            if (e.key === 'Enter') { e.preventDefault(); (opciones[actual] || opciones[0])?.click(); }
            if (e.key === 'Escape') cerrar();
        });
        document.addEventListener('click', (e) => { if (!ul.contains(e.target) && e.target !== input) cerrar(); });
        return cerrar;
    }

    // ---------- Catálogo en tarjetas (solo los productos de la empresa activa) ----------
    const catalogo = JSON.parse(raiz.dataset.catalogo || '[]');
    const sinAcentos = (t) => String(t ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    function visibles() {
        const palabras = sinAcentos($('buscarProducto').value).split(/\s+/).filter(Boolean);
        return catalogo.filter((p) => palabras.every((w) => sinAcentos(p.descripcion + ' ' + (p.codigo_corto || '')).includes(w)));
    }

    function dibujarTarjetas() {
        const lista = visibles();
        $('catalogoVacio').hidden = lista.length > 0;
        $('tarjetasProductos').innerHTML = lista.map((p) => {
            const enCarrito = items.find((i) => i.producto_id === p.id);
            let stock = '<span class="etiqueta azul">Servicio</span>';
            let agotado = false;
            if (p.tipo === 'P') {
                const s = Number(p.stock_actual) - (enCarrito ? enCarrito.cantidad : 0);
                agotado = s <= 0 && !permiteNegativo;
                stock = Number(p.stock_actual) <= 0 ? '<span class="etiqueta rojo">Sin stock</span>'
                    : `<span class="etiqueta ${Number(p.stock_actual) < 5 ? 'naranja' : 'verde'}">Stock ${Number(p.stock_actual).toLocaleString('es-PY')}</span>`;
            }
            return `<button type="button" class="pdv-tarjeta${agotado ? ' agotado' : ''}${enCarrito ? ' elegido' : ''}" data-id="${p.id}">
                ${enCarrito ? `<span class="pdv-en-carrito">${enCarrito.cantidad.toLocaleString('es-PY')}</span>` : ''}
                <span class="pdv-tarjeta-cab"><span class="pdv-cod">${esc(p.codigo_corto || '—')}</span>${stock}</span>
                <span class="pdv-tarjeta-nombre">${esc(p.descripcion)}</span>
                <span class="pdv-tarjeta-pie"><strong>₲ ${gs(p.precio_venta)}</strong><small>${Number(p.iva) ? 'IVA ' + p.iva + '%' : 'Exenta'}</small></span>
            </button>`;
        }).join('');
    }

    $('tarjetasProductos').addEventListener('click', (e) => {
        const t = e.target.closest('.pdv-tarjeta');
        if (!t) return;
        if (t.classList.contains('agotado')) { avisoStock(t); return; }
        agregarItem(catalogo.find((p) => p.id === Number(t.dataset.id)));
    });
    function avisoStock(t) {
        t.classList.remove('sacudir'); void t.offsetWidth; t.classList.add('sacudir');
        Aviso.toast('Ese producto no tiene stock suficiente.', 'warning');
    }
    $('buscarProducto').addEventListener('input', dibujarTarjetas);
    $('buscarProducto').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const primera = $('tarjetasProductos').querySelector('.pdv-tarjeta:not(.agotado)');
            if (primera) { primera.click(); $('buscarProducto').select(); }
        }
        if (e.key === 'Escape') { $('buscarProducto').value = ''; dibujarTarjetas(); }
    });

    function agregarItem(p) {
        const existente = items.find((i) => i.producto_id === p.id);
        if (existente) { existente.cantidad += 1; } else {
            items.push({
                producto_id: p.id, codigo: p.codigo_corto, descripcion: p.descripcion, cantidad: 1,
                precio_unitario: Number(p.precio_venta), iva: Number(p.iva), tipo: p.tipo, stock: Number(p.stock_actual),
            });
        }
        dibujar();
    }

    function dibujar() {
        const tbody = $('items');
        $('carritoVacio').hidden = items.length > 0;
        tbody.closest('table').hidden = !items.length;
        $('cantidadItems').textContent = items.length + (items.length === 1 ? ' ítem' : ' ítems');
        tbody.innerHTML = items.map((i, idx) => {
            const sinStock = i.tipo === 'P' && !permiteNegativo && i.cantidad > i.stock;
            return `<tr class="${sinStock ? 'sin-stock' : ''}">
                <td class="col-desc"><textarea class="desc" data-i="${idx}" rows="1" maxlength="${maxDesc}" spellcheck="false">${esc(i.descripcion)}</textarea>
                    <small class="${sinStock ? 'rojo' : 'gris'}">${i.codigo ? esc(i.codigo) + ' · ' : ''}${i.tipo === 'P' ? 'Stock: ' + i.stock.toLocaleString('es-PY') + (sinStock ? ' (no alcanza)' : '') : 'Servicio'}</small></td>
                <td class="der"><div class="pdv-cant">
                    <button type="button" data-menos="${idx}" title="Restar">−</button>
                    <input class="cant" data-i="${idx}" type="number" min="0.01" step="any" value="${i.cantidad}">
                    <button type="button" data-mas="${idx}" title="Sumar">+</button>
                </div></td>
                <td class="der"><input class="precio" data-i="${idx}" inputmode="numeric" value="${gs(i.precio_unitario)}"></td>
                <td class="der">${i.iva ? i.iva + '%' : 'Exenta'}</td>
                <td class="der pdv-subtotal">${gs(i.cantidad * i.precio_unitario)}</td>
                <td class="der"><button type="button" class="pdv-quitar" data-quitar="${idx}" title="Quitar"><i class="fas fa-trash"></i></button></td>
            </tr>`;
        }).join('');
        calcularTotales();
        dibujarTarjetas();
        tbody.querySelectorAll('textarea.desc').forEach(crecer);
    }
    // La descripción crece hacia abajo mientras se escribe, así se ve entera (código incluido).
    function crecer(t) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; }
    $('items').addEventListener('input', (e) => {
        if (!e.target.classList.contains('desc')) return;
        crecer(e.target);
        const i = items[e.target.dataset.i];
        if (i) i.descripcion = e.target.value;
    });
    $('items').addEventListener('keydown', (e) => {
        if (e.target.classList.contains('desc') && e.key === 'Enter') e.preventDefault(); // un solo renglón lógico: la hoja lo acomoda sola
    });

    $('items').addEventListener('change', (e) => {
        const i = items[e.target.dataset.i];
        if (!i) return;
        if (e.target.classList.contains('cant')) i.cantidad = Math.max(0.01, parseFloat(e.target.value) || 1);
        if (e.target.classList.contains('precio')) i.precio_unitario = parseInt(e.target.value.replace(/\D/g, ''), 10) || 0;
        if (e.target.classList.contains('desc')) i.descripcion = e.target.value;
        dibujar();
    });
    $('items').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.quitar !== undefined) items.splice(Number(b.dataset.quitar), 1);
        else if (b.dataset.mas !== undefined) items[b.dataset.mas].cantidad += 1;
        else if (b.dataset.menos !== undefined) items[b.dataset.menos].cantidad = Math.max(1, items[b.dataset.menos].cantidad - 1);
        else return;
        dibujar();
    });

    function calcularTotales() {
        const t = { exenta: 0, g5: 0, g10: 0 };
        items.forEach((i) => {
            const sub = Math.round(i.cantidad * i.precio_unitario);
            if (i.iva === 10) t.g10 += sub; else if (i.iva === 5) t.g5 += sub; else t.exenta += sub;
        });
        $('totExenta').textContent = gs(t.exenta);
        $('totG5').textContent = gs(t.g5);
        $('totG10').textContent = gs(t.g10);
        $('totIva5').textContent = gs(t.g5 / 21);
        $('totIva10').textContent = gs(t.g10 / 11);
        $('totTotal').textContent = gs(t.exenta + t.g5 + t.g10);
    }

    // ---------- Clientes (con sugerencias del padrón de la DNIT) ----------
    let opcionesCliente = [];
    const cerrarClientes = lista($('buscarCliente'), $('sugerenciasCliente'), async (q) => {
        const pedidos = [fetch(conQ(raiz.dataset.urlClientes, q)).then((r) => r.json())];
        if (pareceRuc(q)) pedidos.push(fetch(conQ(raiz.dataset.urlPadron, q)).then((r) => r.json()).catch(() => null));
        const [clientes, padron] = await Promise.all(pedidos);
        const base = q.split('-')[0].trim();
        const rucsClientes = clientes.map((c) => String(c.ruc).split('-')[0]);
        const delPadron = (padron?.padron || []).filter((p) => !rucsClientes.includes(p.ruc.split('-')[0]));
        const exacto = clientes.some((c) => String(c.ruc).split('-')[0] === base || c.razon_social.toUpperCase() === q.toUpperCase());

        opcionesCliente = [];
        let html = '';
        const fila = (ruc, nombre) => `<span class="cl-ruc">${esc(ruc)}</span> - ${esc(nombre)}`;
        if (q === '') {
            if (!clientes.length) return '<li class="pdv-nada"><i class="fas fa-user-slash"></i> Todavía no hay clientes guardados. Escribí un RUC o nombre.</li>';
            clientes.forEach((c) => {
                opcionesCliente.push({ tipo: 'cliente', dato: c });
                html += `<li data-elegir="${opcionesCliente.length - 1}" class="cl-op"><span class="pdv-desc"><span>${fila(c.ruc, c.razon_social)}</span></span></li>`;
            });
            return html;
        }
        clientes.forEach((c) => {
            opcionesCliente.push({ tipo: 'cliente', dato: c });
            html += `<li data-elegir="${opcionesCliente.length - 1}" class="cl-op"><span class="pdv-desc"><span>${fila(c.ruc, c.razon_social)}</span></span></li>`;
        });
        if (!clientes.length && !delPadron.length) {
            html += `<li class="pdv-nada"><i class="fas fa-user-slash"></i> «${esc(q)}» no es cliente todavía.</li>`;
        }
        delPadron.forEach((p) => {
            opcionesCliente.push({ tipo: 'padron', dato: p });
            const malo = p.estado && p.estado.toUpperCase() !== 'ACTIVO';
            html += `<li data-elegir="${opcionesCliente.length - 1}" class="cl-op">
                <span class="pdv-desc">
                    <span><span class="cl-dnit">Padrón DNIT</span> ${fila(p.ruc, p.original || p.nombre)}</span>
                    <small class="${malo ? 'cl-mal' : ''}">${malo ? 'RUC ' + esc(p.estado) + ' · ' : ''}Tocá para registrarlo como cliente</small>
                </span>
            </li>`;
        });
        if (!exacto) {
            opcionesCliente.push({ tipo: 'nuevo', dato: q });
            html += `<li data-elegir="${opcionesCliente.length - 1}" class="pdv-agregar">
                <span class="pdv-avatar nuevo"><i class="fas fa-user-plus"></i></span>
                <span class="pdv-desc"><strong>No es cliente. Agregar como cliente</strong><small>Registrar «${esc(q)}» y usarlo en esta venta</small></span>
            </li>`;
        }
        return html;
    }, true);
    $('sugerenciasCliente').addEventListener('click', (e) => {
        const li = e.target.closest('[data-elegir]');
        if (!li) return;
        const op = opcionesCliente[Number(li.dataset.elegir)];
        cerrarClientes();
        if (op.tipo === 'cliente') elegirCliente(op.dato);
        else if (op.tipo === 'padron') abrirModal({ ruc: op.dato.ruc, nombre: op.dato.nombre });
        else abrirModal(pareceRuc(op.dato) ? { ruc: op.dato } : { nombre: op.dato.toUpperCase() });
    });

    function elegirCliente(c) {
        cliente = c;
        const div = $('clienteSeleccionado');
        div.innerHTML = `<span class="pdv-avatar">${esc(c.razon_social.charAt(0))}</span>
            <div><strong>${esc(c.razon_social)}</strong><small>RUC ${esc(c.ruc)}${c.direccion ? ' · ' + esc(c.direccion) : ''}${c.telefono ? ' · ' + esc(c.telefono) : ''}</small></div>
            <button type="button" id="quitarCliente" title="Cambiar cliente">&times;</button>`;
        div.hidden = false;
        $('cajaBuscarCliente').hidden = true;
        $('buscarCliente').value = '';
        $('quitarCliente').addEventListener('click', () => {
            cliente = null;
            div.hidden = true;
            $('cajaBuscarCliente').hidden = false;
            $('buscarCliente').focus();
        });
        if (!items.length) $('buscarProducto').focus();
    }

    // ---------- Alta de cliente (modal) ----------
    const modal = $('modalCliente');
    const camposCliente = ['cr_ruc', 'cr_razon', 'cr_direccion', 'cr_telefono', 'cr_celular', 'cr_email'];
    const padron = window.RucPadron ? RucPadron({
        ruc: $('cr_ruc'), nombre: $('cr_razon'), aviso: $('cr_padron'), url: raiz.dataset.urlPadron,
        alExistente: (c) => { cerrarModal(); elegirCliente(c); },
    }) : null;

    function abrirModal(datos = {}) {
        camposCliente.forEach((id) => { $(id).value = ''; });
        $('cr_razon').dataset.auto = '0';
        $('errorCliente').hidden = true;
        padron?.limpiar();
        const aviso = $('avisoNoCliente');
        aviso.hidden = !datos.aviso;
        aviso.textContent = datos.aviso || '';
        if (datos.ruc) $('cr_ruc').value = datos.ruc;
        if (datos.nombre) { $('cr_razon').value = datos.nombre; $('cr_razon').dataset.auto = '1'; }
        modal.hidden = false;
        if (datos.ruc) padron?.consultar();
        (datos.ruc && !datos.nombre ? $('cr_razon') : datos.ruc ? $('cr_direccion') : $('cr_ruc')).focus();
    }
    function cerrarModal() { modal.hidden = true; }

    $('btnNuevoCliente').addEventListener('click', () => {
        const q = $('buscarCliente').value.trim();
        abrirModal(q ? (pareceRuc(q) ? { ruc: q } : { nombre: q.toUpperCase() }) : {});
    });
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-cerrar]')) cerrarModal(); });

    $('btnGuardarCliente').addEventListener('click', async () => {
        const error = $('errorCliente');
        error.hidden = true;
        const datos = new FormData();
        datos.append('_csrf', csrf);
        datos.append('ruc', $('cr_ruc').value.trim());
        datos.append('razon_social', $('cr_razon').value.trim());
        datos.append('direccion', $('cr_direccion').value.trim());
        datos.append('telefono', $('cr_telefono').value.trim());
        datos.append('celular', $('cr_celular').value.trim());
        datos.append('email', $('cr_email').value.trim());
        const boton = $('btnGuardarCliente');
        boton.disabled = true;
        try {
            const r = await (await fetch(raiz.dataset.urlClienteNuevo, { method: 'POST', body: datos })).json();
            if (r.error) { error.textContent = r.error; error.hidden = false; return; }
            cerrarModal();
            elegirCliente(r);
        } catch (e) {
            error.textContent = 'No se pudo guardar el cliente. Revisá la conexión.';
            error.hidden = false;
        } finally {
            boton.disabled = false;
        }
    });

    // ---------- Condición y método de pago ----------
    function actualizarPago() {
        const conFecha = $('condicion').value === 'CONTADO_FECHA';
        $('bloqueMetodo').hidden = conFecha;
        $('bloqueFechaPago').hidden = !conFecha;
        $('bloqueReferencia').hidden = conFecha || $('metodoPago').value !== 'TRANSFERENCIA';
    }
    $('condicion').addEventListener('change', () => { actualizarPago(); if ($('condicion').value === 'CONTADO_FECHA') $('fechaPago').focus(); });
    $('metodoPago').addEventListener('change', actualizarPago);

    // ---------- Número de factura: no se puede repetir en la empresa ----------
    let numeroUsado = null;
    async function revisarNumero() {
        const n = parseInt($('numero').value, 10);
        numeroUsado = null;
        $('avisoNumero').hidden = true;
        $('numero').classList.remove('invalido');
        if (!n) return null;
        try {
            const r = await (await fetch(raiz.dataset.urlNumero + '?numero=' + n, { headers: { Accept: 'application/json' } })).json();
            if (parseInt($('numero').value, 10) !== n) return null;
            if (r.usado) {
                numeroUsado = r.usado;
                $('avisoNumero').textContent = 'Ese número ya fue usado.';
                $('avisoNumero').hidden = false;
                $('numero').classList.add('invalido');
            }
        } catch (e) { /* sin conexión: el servidor igual lo rechaza al guardar */ }
        return numeroUsado;
    }
    let tNumero;
    $('numero').addEventListener('input', () => { clearTimeout(tNumero); tNumero = setTimeout(revisarNumero, 300); });
    $('numero').addEventListener('change', async () => {
        if (await revisarNumero()) Aviso.aviso(numeroUsado, 'Número de factura repetido');
    });

    // ---------- Atajos ----------
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') { e.preventDefault(); $('buscarProducto').focus(); }
        if (e.key === 'F4') { e.preventDefault(); if (!cliente) $('buscarCliente').focus(); }
        if (e.key === 'Escape' && !modal.hidden) cerrarModal();
    });

    // ---------- Guardar ----------
    $('btnGuardar').addEventListener('click', async () => {
        const condicion = $('condicion').value;
        const numero = parseInt($('numero').value, 10);

        // Escribió un cliente pero no lo eligió: si coincide con uno de la lista se usa; si no, se ofrece registrarlo.
        if (!cliente && $('buscarCliente').value.trim()) {
            const q = $('buscarCliente').value.trim();
            const igual = opcionesCliente.find((op) => op.tipo === 'cliente'
                && (String(op.dato.ruc).split('-')[0] === q.split('-')[0] || op.dato.razon_social.toUpperCase() === q.toUpperCase()));
            if (igual) { elegirCliente(igual.dato); } else {
                cerrarClientes();
                abrirModal({ ...(pareceRuc(q) ? { ruc: q } : { nombre: q.toUpperCase() }),
                    aviso: `«${q}» no es cliente. Registralo para poder emitirle la factura.` });
                return;
            }
        }

        const problemas = [];
        if (!numero) problemas.push('Ingresá el número de la hoja de factura.');
        else if (await revisarNumero()) problemas.push(numeroUsado);
        if (!cliente) problemas.push('Elegí un cliente o registralo con el botón +.');
        if (!items.length) problemas.push('Agregá al menos un producto o servicio.');
        if (condicion === 'CONTADO_FECHA' && !$('fechaPago').value) problemas.push('Indicá la fecha de pago.');
        if (problemas.length) {
            Aviso.aviso(problemas.join(' '), 'Revisá antes de guardar', '<ul class="sw-lista">' + problemas.map((p) => `<li>${esc(p)}</li>`).join('') + '</ul>');
            return;
        }

        const numeroCompleto = document.querySelector('.numero-factura span').textContent + String(numero).padStart(7, '0');
        const totalTexto = $('totTotal').textContent;
        const seguro = await Aviso.confirmar(`¿La hoja en la impresora es la N.º ${numeroCompleto}?`, {
            titulo: '¿Imprimimos?',
            icono: 'question',
            html: `<p>Verificá que la hoja puesta en la impresora sea la</p><div class="sw-numero">${esc(numeroCompleto)}</div>`
                + `<p class="sw-detalle">${esc(cliente.razon_social)} · Total ₲ ${esc(totalTexto)}</p>`,
            si: 'Sí, guardar e imprimir', no: 'Revisar',
        });
        if (!seguro) return;

        const metodo = condicion === 'CONTADO' ? $('metodoPago').value : '';
        const factura = {
            numero, fecha: $('fecha').value, cliente_id: cliente.id, condicion,
            metodo_pago: metodo,
            referencia: metodo === 'TRANSFERENCIA' ? $('referencia').value.trim() : '',
            fecha_pago: condicion === 'CONTADO_FECHA' ? $('fechaPago').value : '',
            observacion: $('observacion').value,
            items: items.map(({ producto_id, descripcion, cantidad, precio_unitario }) => ({ producto_id, descripcion, cantidad, precio_unitario })),
        };

        const boton = $('btnGuardar');
        boton.disabled = true;
        try {
            const datos = new FormData();
            datos.append('_csrf', csrf);
            datos.append('factura', JSON.stringify(factura));
            const resp = await fetch(raiz.dataset.urlGuardar, { method: 'POST', body: datos });
            const r = await resp.json();
            if (r.error) { boton.disabled = false; Aviso.error(r.error, 'No se guardó la factura'); return; }
            // Se imprime desde un marco oculto en esta misma pantalla: no queda ninguna pestaña abierta atrás.
            await window.imprimirOculto(r.imprimir);
            window.location = r.ver;
        } catch (e) {
            boton.disabled = false;
            Aviso.error('Revisá la conexión e intentá de nuevo.', 'No se pudo guardar');
        }
    });

    dibujar();
    actualizarPago();
})();
