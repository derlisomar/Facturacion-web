// Mientras se escribe un RUC, lo busca en los clientes y en el padrón de la DNIT y completa el nombre.
// Uso: RucPadron({ ruc, nombre, aviso, url, alExistente(cliente), textoExistente })
window.RucPadron = function (o) {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let temporizador, exacto = null, opciones = [], existente = null;

    o.nombre.addEventListener('input', () => { o.nombre.dataset.auto = '0'; });

    function ponerNombre(nombre) {
        if (!o.nombre.value.trim() || o.nombre.dataset.auto === '1') {
            o.nombre.value = nombre;
            o.nombre.dataset.auto = '1';
        }
    }
    function quitarNombreAutomatico() {
        if (o.nombre.dataset.auto === '1') { o.nombre.value = ''; o.nombre.dataset.auto = '0'; }
    }
    function ocultar() { o.aviso.hidden = true; o.aviso.innerHTML = ''; }

    function mostrar(r, q) {
        const base = q.split('-')[0];
        existente = r.existente;
        exacto = r.padron.find((p) => p.ruc.split('-')[0] === base) || null;
        opciones = r.padron.filter((p) => p !== exacto);
        let html = '';
        if (existente) {
            html = `<div class="rp-existe"><i class="fas fa-circle-check"></i><span>Ya es cliente: <strong>${esc(existente.razon_social)}</strong> · ${esc(existente.ruc)}</span>`
                + (o.alExistente ? `<button type="button" class="btn btn-chico" data-usar>${esc(o.textoExistente || 'Usar este cliente')}</button>` : '') + '</div>';
        } else if (exacto) {
            ponerNombre(exacto.nombre);
            html = `<div class="rp-ok"><i class="fas fa-id-card"></i><span>Padrón DNIT: <strong>${esc(exacto.nombre)}</strong> · RUC ${esc(exacto.ruc)}</span>`
                + (exacto.estado ? `<span class="etiqueta ${exacto.estado === 'ACTIVO' ? 'verde' : 'rojo'}">${esc(exacto.estado)}</span>` : '') + '</div>';
        } else {
            quitarNombreAutomatico();
            if (opciones.length) {
                html = '<div class="rp-titulo">En el padrón de la DNIT</div>' + opciones.map((p, i) =>
                    `<button type="button" class="rp-item" data-i="${i}"><strong>${esc(p.ruc)}</strong><span>${esc(p.nombre)}</span></button>`).join('');
            } else if (!r.cargado) {
                html = '<div class="rp-nada">El padrón de RUC todavía no está cargado (Registros → Padrón RUC). Escribí el nombre a mano.</div>';
            } else if (base.length >= 5) {
                html = '<div class="rp-nada">No figura en el padrón de la DNIT. Escribí el nombre a mano.</div>';
            }
        }
        o.aviso.innerHTML = html;
        o.aviso.hidden = !html;
    }

    o.aviso.addEventListener('click', (e) => {
        if (e.target.closest('[data-usar]') && existente) { o.alExistente(existente); return; }
        const item = e.target.closest('[data-i]');
        if (item) {
            const p = opciones[Number(item.dataset.i)];
            o.ruc.value = p.ruc;
            o.nombre.dataset.auto = '1';
            o.nombre.value = p.nombre;
            consultar();
        }
    });

    function consultar() {
        clearTimeout(temporizador);
        const q = o.ruc.value.trim();
        if (!/^\d{3,12}(\s*-\s*\d?)?$/.test(q)) { exacto = null; quitarNombreAutomatico(); ocultar(); return; }
        temporizador = setTimeout(async () => {
            try {
                const r = await (await fetch(o.url + (o.url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q))).json();
                if (o.ruc.value.trim() === q && !r.error) mostrar(r, q.replace(/\s/g, ''));
            } catch (e) { ocultar(); }
        }, 250);
    }
    o.ruc.addEventListener('input', consultar);
    // Si escribió el RUC sin el dígito verificador, se completa al salir del campo.
    o.ruc.addEventListener('blur', () => {
        if (exacto && !existente && !o.ruc.value.includes('-')) o.ruc.value = exacto.ruc;
    });

    return { consultar, limpiar() { exacto = null; existente = null; ocultar(); } };
};
