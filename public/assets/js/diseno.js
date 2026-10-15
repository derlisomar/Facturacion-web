// Diseñador visual del formato de impresión sobre la hoja preimpresa.
// Todas las medidas están en milímetros desde la esquina superior izquierda de la hoja.
(function () {
    const D = window.DISENO;
    let formato = clonar(D.formato);
    let zoom = 3;               // px por mm
    let seleccion = null;       // { tipo: 'campo' | 'columna', clave }
    let cambios = false;

    const CAMPOS = {
        fecha: ['Fecha', '13/10/26'],
        razon_social: ['Razón social', 'CLIENTE DE PRUEBA S.A.'],
        ruc: ['RUC', '80000000-0'],
        direccion: ['Dirección', 'AVDA. EJEMPLO 1234'],
        telefono: ['Teléfono', '0981 000000'],
        contado: ['Casilla contado (X)', 'X'],
        credito: ['Casilla crédito (X)', 'X'],
        fecha_pago: ['Fecha de pago', 'Pago: 30/10/2026'],
        subtotal_exenta: ['Subtotal exenta', '10.000'],
        subtotal_5: ['Subtotal 5 %', '21.000'],
        subtotal_10: ['Subtotal 10 %', '110.000'],
        total_letras: ['Total en letras', 'CIENTO CUARENTA Y UN MIL GUARANÍES'],
        total: ['Total a pagar', '141.000'],
        iva5: ['Liquidación IVA 5 %', '1.000'],
        iva10: ['Liquidación IVA 10 %', '10.000'],
        iva_total: ['Total IVA', '11.000'],
        observacion: ['Observación (quién pidió)', 'PEDIDO POR: JUAN PÉREZ'],
    };
    const COLUMNAS = {
        cantidad: ['Cantidad', '2'],
        descripcion: ['Descripción', 'PRODUCTO DE EJEMPLO'],
        precio: ['Precio unitario', '55.000'],
        exenta: ['Columna exentas', '10.000'],
        iva5: ['Columna 5 %', '21.000'],
        iva10: ['Columna 10 %', '110.000'],
    };
    const PT_A_MM = 0.3528;

    const hoja = document.getElementById('dz-hoja');
    const scroll = document.getElementById('dz-scroll');
    const panel = document.getElementById('dz-propiedades');

    function clonar(o) { return JSON.parse(JSON.stringify(o)); }
    function r1(n) { return Math.round(n * 10) / 10; }
    function num(v, defecto) { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? defecto : n; }
    function marcarCambio() { cambios = true; document.getElementById('dz-guardar').classList.add('pendiente'); }

    function pos(sel) {
        if (sel.tipo === 'campo') {
            formato.campos[sel.clave] = formato.campos[sel.clave] || { x: 10, y: 10, visible: false };
            return formato.campos[sel.clave];
        }
        return formato.detalle.columnas[sel.clave];
    }

    // ---------- Dibujo de la hoja ----------
    function calcularZoom() {
        const elegido = parseFloat(document.getElementById('dz-zoom').value);
        zoom = elegido > 0 ? elegido : Math.max(1.5, (scroll.clientWidth - 24) / formato.pagina.ancho);
    }

    const NOMBRE_COPIA = ['Original', 'Duplicado', 'Triplicado', 'Cuadruplicado'];
    const nombreCopia = (i) => NOMBRE_COPIA[i] || 'Copia ' + (i + 1);

    function caja(clave, tipo, p, texto, y, fantasma, copia) {
        const el = document.createElement('div');
        const alinear = p.alinear || 'izquierda';
        el.className = 'dz-campo' + (alinear === 'derecha' ? ' der' : alinear === 'centro' ? ' centro' : '')
            + (p.visible === false ? ' oculto' : '') + (fantasma ? ' fantasma' : '')
            + (seleccion && !fantasma && seleccion.tipo === tipo && seleccion.clave === clave ? ' activo' : '');
        el.style.left = p.x * zoom + 'px';
        el.style.top = y * zoom + 'px';
        el.style.fontSize = (p.fuente || formato.fuente) * PT_A_MM * zoom + 'px';
        if (p.negrita) el.style.fontWeight = 'bold';
        if (p.ancho) el.style.width = p.ancho * zoom + 'px';
        el.textContent = texto;
        if (copia > 0) {
            // Duplicado y triplicado: arrastrar cualquiera de sus datos mueve la copia entera.
            el.classList.add('mover-copia');
            if (seleccion && seleccion.tipo === 'copia' && seleccion.clave === copia) el.classList.add('copia-activa');
            el.dataset.copia = copia;
            el.title = nombreCopia(copia) + ': arrastrá para subir o bajar toda la copia';
            el.addEventListener('pointerdown', empezarArrastreCopia);
        } else if (!fantasma) {
            el.dataset.clave = clave;
            el.dataset.tipo = tipo;
            el.title = (tipo === 'campo' ? CAMPOS[clave][0] : COLUMNAS[clave][0]) + ' · X ' + p.x + ' · Y ' + r1(y) + ' mm';
            el.addEventListener('pointerdown', empezarArrastre);
        }
        return el;
    }

    function dibujar() {
        calcularZoom();
        const fondo = document.getElementById('dz-fondo');
        hoja.querySelectorAll('.dz-campo').forEach((el) => el.remove());
        hoja.style.width = formato.pagina.ancho * zoom + 'px';
        hoja.style.height = formato.pagina.alto * zoom + 'px';
        hoja.style.backgroundSize = document.getElementById('dz-grilla').checked ? `${10 * zoom}px ${10 * zoom}px` : '0 0';
        if (fondo) fondo.style.opacity = (document.getElementById('dz-opacidad')?.value ?? 70) / 100;

        const verCopias = document.getElementById('dz-copias').checked;
        const copias = verCopias ? formato.copias : [formato.copias[0] || 0];
        copias.forEach((copiaY, i) => {
            const fantasma = i > 0;
            Object.keys(CAMPOS).forEach((clave) => {
                const p = formato.campos[clave];
                if (!p || (fantasma && p.visible === false) || p.en_copias) return;
                hoja.appendChild(caja(clave, 'campo', p, CAMPOS[clave][1], p.y + copiaY, fantasma, i));
            });
            const det = formato.detalle;
            for (let fila = 0; fila < det.max_filas; fila++) {
                Object.keys(COLUMNAS).forEach((clave) => {
                    const p = det.columnas[clave];
                    if (!p) return;
                    hoja.appendChild(caja(clave, 'columna', p, COLUMNAS[clave][1], det.y + fila * det.alto_fila + copiaY, fantasma || fila > 0, i));
                });
            }
        });
        // Campos que salen solo en algunas copias (la observación): se editan sobre la primera copia donde salen.
        Object.keys(CAMPOS).forEach((clave) => {
            const p = formato.campos[clave];
            if (!p || !p.en_copias || !p.en_copias.length) return;
            p.en_copias.forEach((n, k) => {
                if (k > 0 && (!verCopias || p.visible === false)) return;
                hoja.appendChild(caja(clave, 'campo', p, CAMPOS[clave][1], p.y + desplazamientoCopia(n), k > 0, 0));
            });
        });
    }
    const desplazamientoCopia = (n) => formato.copias[n - 1] ?? formato.copias[formato.copias.length - 1] ?? 0;

    // ---------- Arrastre ----------
    let arrastre = null;
    function empezarArrastre(ev) {
        ev.preventDefault();
        const el = ev.currentTarget;
        seleccionar({ tipo: el.dataset.tipo, clave: el.dataset.clave }, false);
        const p = pos(seleccion);
        arrastre = { x0: ev.clientX, y0: ev.clientY, px: p.x, py: seleccion.tipo === 'campo' ? p.y : formato.detalle.y, movio: false };
        window.addEventListener('pointermove', moverArrastre);
        window.addEventListener('pointerup', terminarArrastre, { once: true });
    }
    function moverArrastre(ev) {
        const dx = (ev.clientX - arrastre.x0) / zoom, dy = (ev.clientY - arrastre.y0) / zoom;
        if (!arrastre.movio && Math.abs(dx) + Math.abs(dy) < 0.3) return;
        arrastre.movio = true;
        const p = pos(seleccion);
        p.x = Math.max(0, r1(arrastre.px + dx));
        if (seleccion.tipo === 'campo') p.y = Math.max(0, r1(arrastre.py + dy));
        else formato.detalle.y = Math.max(0, r1(arrastre.py + dy));
        marcarCambio();
        dibujar();
        mostrarPropiedades();
    }
    function empezarArrastreCopia(ev) {
        ev.preventDefault();
        const i = parseInt(ev.currentTarget.dataset.copia, 10);
        seleccionar({ tipo: 'copia', clave: i }, false);
        arrastre = { y0: ev.clientY, py: formato.copias[i], movio: false };
        window.addEventListener('pointermove', moverCopia);
        window.addEventListener('pointerup', () => { window.removeEventListener('pointermove', moverCopia); arrastre = null; }, { once: true });
    }
    function moverCopia(ev) {
        const dy = (ev.clientY - arrastre.y0) / zoom;
        if (!arrastre.movio && Math.abs(dy) < 0.3) return;
        arrastre.movio = true;
        formato.copias[seleccion.clave] = Math.max(0, r1(arrastre.py + dy));
        copiasCambiaron();
    }
    function copiasCambiaron() {
        document.getElementById('dz-lista-copias').value = formato.copias.join('; ');
        marcarCambio();
        dibujar();
        mostrarPropiedades();
    }
    function terminarArrastre() {
        window.removeEventListener('pointermove', moverArrastre);
        arrastre = null;
    }

    hoja.addEventListener('pointermove', (ev) => {
        const r = hoja.getBoundingClientRect();
        document.getElementById('dz-cursor').textContent = `X ${r1((ev.clientX - r.left) / zoom)} · Y ${r1((ev.clientY - r.top) / zoom)} mm`;
    });
    hoja.addEventListener('pointerdown', (ev) => { if (ev.target === hoja || ev.target.id === 'dz-fondo') seleccionar(null); });

    document.addEventListener('keydown', (ev) => {
        if (!seleccion || ev.target.closest('input, select, textarea')) return;
        const pasos = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[ev.key];
        if (!pasos) return;
        ev.preventDefault();
        const paso = ev.shiftKey ? 5 : ev.altKey ? 0.1 : 0.5;
        if (seleccion.tipo === 'copia') {
            formato.copias[seleccion.clave] = Math.max(0, r1(formato.copias[seleccion.clave] + pasos[1] * paso));
            copiasCambiaron();
            return;
        }
        const p = pos(seleccion);
        p.x = Math.max(0, r1(p.x + pasos[0] * paso));
        if (seleccion.tipo === 'campo') p.y = Math.max(0, r1(p.y + pasos[1] * paso));
        else formato.detalle.y = Math.max(0, r1(formato.detalle.y + pasos[1] * paso));
        marcarCambio();
        dibujar();
        mostrarPropiedades();
    });

    // ---------- Lista de campos y panel de propiedades ----------
    function listar() {
        const armar = (contenedor, nombres, tipo) => {
            contenedor.innerHTML = '';
            Object.keys(nombres).forEach((clave) => {
                const p = tipo === 'campo' ? formato.campos[clave] : formato.detalle.columnas[clave];
                const fila = document.createElement('div');
                fila.className = 'dz-item' + (seleccion && seleccion.tipo === tipo && seleccion.clave === clave ? ' activo' : '');
                fila.innerHTML = `<input type="checkbox" ${p && p.visible !== false ? 'checked' : ''} title="Se imprime"><span>${nombres[clave][0]}</span>`;
                fila.querySelector('input').addEventListener('change', (ev) => {
                    const q = pos({ tipo, clave });
                    q.visible = ev.target.checked;
                    marcarCambio(); dibujar(); mostrarPropiedades();
                });
                fila.querySelector('span').addEventListener('click', () => seleccionar({ tipo, clave }, true));
                contenedor.appendChild(fila);
            });
        };
        armar(document.getElementById('dz-campos'), CAMPOS, 'campo');
        armar(document.getElementById('dz-columnas'), COLUMNAS, 'columna');
    }

    function seleccionar(sel, centrar) {
        seleccion = sel;
        dibujar(); listar(); mostrarPropiedades();
        if (sel && centrar) {
            const el = hoja.querySelector(`.dz-campo[data-tipo="${sel.tipo}"][data-clave="${sel.clave}"]`);
            if (el) el.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
        }
    }

    function mostrarPropiedades() {
        if (!seleccion) {
            panel.innerHTML = '<h2>Campo seleccionado</h2><p class="gris">Tocá un campo en la hoja o en la lista.</p>';
            return;
        }
        if (seleccion.tipo === 'copia') {
            const i = seleccion.clave;
            panel.innerHTML = `
                <h2>${nombreCopia(i)}</h2>
                <div class="formulario">
                    <label>Corrimiento desde el original (mm)<input type="number" step="0.1" min="0" id="dz-copia-y" value="${formato.copias[i]}"></label>
                    <p class="gris dz-ayuda">Mueve todos los datos de esta copia juntos. Arrastrala en la hoja o usá las flechas ↑ ↓
                    (Shift = 5 mm, Alt = 0,1 mm). Positivo = más abajo. Los campos se acomodan en el original y la copia los repite.</p>
                </div>`;
            panel.querySelector('#dz-copia-y').addEventListener('input', (e) => {
                formato.copias[i] = Math.max(0, num(e.target.value, formato.copias[i]));
                document.getElementById('dz-lista-copias').value = formato.copias.join('; ');
                marcarCambio(); dibujar();
            });
            return;
        }
        const p = pos(seleccion);
        const esCampo = seleccion.tipo === 'campo';
        const nombre = esCampo ? CAMPOS[seleccion.clave][0] : COLUMNAS[seleccion.clave][0];
        const det = formato.detalle;
        panel.innerHTML = `
            <h2>${nombre}</h2>
            <div class="formulario">
                <div class="fila">
                    <label>X (mm)<input type="number" step="0.1" data-p="x" value="${p.x}"></label>
                    ${esCampo ? `<label>Y (mm)<input type="number" step="0.1" data-p="y" value="${p.y}"></label>` : ''}
                </div>
                <div class="fila">
                    <label>Alineación
                        <select data-p="alinear">
                            <option value="izquierda">Izquierda</option><option value="derecha">Derecha</option><option value="centro">Centro</option>
                        </select>
                    </label>
                    <label>Ancho máx. (mm)<input type="number" step="1" min="0" data-p="ancho" value="${p.ancho || ''}" placeholder="libre"></label>
                </div>
                <div class="fila">
                    <label>Letra (pt)<input type="number" step="0.5" min="5" max="24" data-p="fuente" value="${p.fuente || ''}" placeholder="${formato.fuente}"></label>
                    <label class="check"><input type="checkbox" data-p="negrita" ${p.negrita ? 'checked' : ''}> Negrita</label>
                </div>
                <label class="check"><input type="checkbox" data-p="visible" ${p.visible !== false ? 'checked' : ''}> Se imprime</label>
                ${p.en_copias ? `<div class="dz-en-copias"><span>Sale en:</span>${formato.copias.map((_, i) => `<label class="check"><input type="checkbox" data-copia-sale="${i + 1}" ${p.en_copias.includes(i + 1) ? 'checked' : ''}> ${nombreCopia(i)}</label>`).join('')}</div>
                <p class="gris dz-ayuda">Y se mide desde el borde de esa copia. Si no lo usás, destildá «Se imprime» y no sale en ninguna.</p>` : ''}
                ${p.en_copias || (seleccion.clave === 'descripcion' && !esCampo) ? `<label class="check"><input type="checkbox" data-p="multilinea" ${p.multilinea ? 'checked' : ''}> Si es larga, sigue en el renglón de abajo (usa el ancho máx.)</label>` : ''}
                <p class="gris dz-ayuda">Con alineación derecha, X es donde termina el texto (útil para importes).</p>
                ${esCampo ? '' : `
                <h2 style="margin-top:12px">Renglones del detalle</h2>
                <div class="fila">
                    <label>Y primer renglón<input type="number" step="0.1" data-d="y" value="${det.y}"></label>
                    <label>Alto de renglón<input type="number" step="0.1" data-d="alto_fila" value="${det.alto_fila}"></label>
                </div>
                <label>Renglones que tiene la hoja<input type="number" step="1" min="1" max="40" data-d="max_filas" value="${det.max_filas}"></label>`}
            </div>`;
        panel.querySelector('[data-p="alinear"]').value = p.alinear || 'izquierda';
        panel.querySelectorAll('[data-copia-sale]').forEach((input) => input.addEventListener('change', () => {
            const marcadas = [...panel.querySelectorAll('[data-copia-sale]:checked')].map((c) => parseInt(c.dataset.copiaSale, 10));
            if (!marcadas.length) { input.checked = true; Aviso.aviso('Tiene que salir al menos en una copia. Para que no se imprima, destildá «Se imprime».'); return; }
            p.en_copias = marcadas;
            marcarCambio(); dibujar();
        }));
        panel.querySelectorAll('[data-p]').forEach((input) => input.addEventListener('input', () => {
            const k = input.dataset.p;
            if (input.type === 'checkbox') p[k] = input.checked;
            else if (k === 'alinear') p[k] = input.value;
            else if (input.value === '' && (k === 'ancho' || k === 'fuente')) delete p[k];
            else p[k] = Math.max(0, num(input.value, p[k] || 0));
            if (k === 'negrita' && !p.negrita) delete p.negrita;
            if (k === 'multilinea' && p.multilinea && !p.ancho) p.ancho = 80;
            marcarCambio(); dibujar(); listar();
        }));
        panel.querySelectorAll('[data-d]').forEach((input) => input.addEventListener('input', () => {
            const k = input.dataset.d;
            det[k] = k === 'max_filas' ? Math.max(1, parseInt(input.value, 10) || 1) : Math.max(0, num(input.value, det[k]));
            marcarCambio(); dibujar();
        }));
    }

    // ---------- Datos generales de la hoja ----------
    function leerRuta(ruta) { return ruta.split('.').reduce((o, k) => o[k], formato); }
    function escribirRuta(ruta, valor) {
        const partes = ruta.split('.'), ultima = partes.pop();
        partes.reduce((o, k) => o[k], formato)[ultima] = valor;
    }
    function cargarGenerales() {
        document.querySelectorAll('[data-general]').forEach((input) => { input.value = leerRuta(input.dataset.general); });
        document.getElementById('dz-lista-copias').value = formato.copias.join('; ');
    }
    document.querySelectorAll('[data-general]').forEach((input) => input.addEventListener('input', () => {
        const ruta = input.dataset.general;
        escribirRuta(ruta, input.tagName === 'SELECT' ? input.value : num(input.value, leerRuta(ruta)));
        marcarCambio(); dibujar();
    }));
    document.getElementById('dz-lista-copias').addEventListener('input', (ev) => {
        const lista = ev.target.value.split(/[;\s]+/).filter((t) => t !== '').map((t) => num(t, NaN)).filter((n) => !isNaN(n));
        formato.copias = lista.length ? lista : [0];
        marcarCambio(); dibujar();
    });

    ['dz-zoom', 'dz-grilla', 'dz-copias', 'dz-opacidad'].forEach((id) => document.getElementById(id)?.addEventListener('input', dibujar));
    window.addEventListener('resize', dibujar);

    // ---------- Guardar, prueba y valores por defecto ----------
    function aviso(texto, error) {
        const el = document.getElementById('dz-aviso');
        el.textContent = texto;
        el.className = 'dz-aviso' + (error ? ' error' : '');
        el.hidden = false;
        clearTimeout(aviso.t);
        aviso.t = setTimeout(() => { el.hidden = true; }, 3500);
    }

    async function guardar() {
        const datos = new FormData();
        datos.append('_csrf', document.querySelector('meta[name=csrf]').content);
        datos.append('formato', JSON.stringify(formato));
        try {
            const resp = await fetch(D.guardar, { method: 'POST', body: datos, credentials: 'same-origin' });
            const json = await resp.json().catch(() => ({ ok: false, error: 'La sesión expiró. Recargá la página.' }));
            if (!json.ok) throw new Error(json.error || 'No se pudo guardar.');
            cambios = false;
            document.getElementById('dz-guardar').classList.remove('pendiente');
            aviso('Diseño guardado. Las próximas facturas salen así.');
            return true;
        } catch (e) {
            aviso(e.message, true);
            return false;
        }
    }

    document.getElementById('dz-guardar').addEventListener('click', guardar);
    document.getElementById('dz-prueba').addEventListener('click', async () => {
        const ventana = window.open('about:blank', '_blank'); // se abre ya, si no el navegador la bloquea
        if (await guardar()) {
            if (ventana) ventana.location = D.prueba; else window.open(D.prueba, '_blank');
        } else if (ventana) {
            ventana.close();
        }
    });
    const selPlantilla = document.getElementById('dz-plantilla');
    selPlantilla.addEventListener('change', async () => {
        const clave = selPlantilla.value;
        const nombre = selPlantilla.options[selPlantilla.selectedIndex].text;
        selPlantilla.value = '';
        if (!clave || !(await Aviso.confirmar(`Se reemplazan todas las posiciones por las de "${nombre}". No se guarda hasta que toques "Guardar diseño".`, { titulo: '¿Cargar la plantilla?', si: 'Sí, cargar' }))) return;
        formato = clonar(D.plantillas[clave]);
        seleccion = null;
        marcarCambio(); cargarGenerales(); dibujar(); listar(); mostrarPropiedades();
    });
    window.addEventListener('beforeunload', (ev) => { if (cambios) { ev.preventDefault(); ev.returnValue = ''; } });

    cargarGenerales();
    listar();
    dibujar();
})();
