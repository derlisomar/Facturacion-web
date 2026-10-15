// Formatea los campos de monto con separador de miles mientras se escribe (1.250.000).
document.querySelectorAll('input.monto').forEach((input) => {
    const formatear = () => {
        const numero = input.value.replace(/\D/g, '');
        input.value = numero ? Number(numero).toLocaleString('es-PY') : '';
    };
    input.addEventListener('input', formatear);
    formatear();
});

// Producto: muestra los campos de stock solo para productos físicos.
const tipoProducto = document.getElementById('tipoProducto');
if (tipoProducto) {
    const actualizar = () => document.querySelectorAll('.solo-producto').forEach((el) => {
        el.hidden = tipoProducto.value !== 'P';
    });
    tipoProducto.addEventListener('change', actualizar);
    actualizar();
}

// Modo claro / oscuro: se guarda en el navegador y vale también para el login.
document.querySelectorAll('.boton-tema').forEach((boton) => boton.addEventListener('click', () => {
    const raiz = document.documentElement;
    const tema = raiz.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    raiz.setAttribute('data-theme', tema);
    try { localStorage.setItem('theme', tema); } catch (e) {}
    window.dispatchEvent(new Event('cambio-tema'));
}));

// Menú lateral achicable; con el menú chico el nombre de cada opción queda como tooltip.
document.querySelectorAll('.sidebar nav a').forEach((a) => { a.title = a.textContent.trim(); });
const botonMenu = document.getElementById('boton-menu');
if (botonMenu) {
    const esMovil = () => window.matchMedia('(max-width: 900px)').matches;
    if (esMovil()) document.body.classList.remove('menu-chico'); // en el celular el menú es un cajón completo
    botonMenu.addEventListener('click', () => {
        if (esMovil()) { document.body.classList.toggle('menu-abierto'); return; }
        const chico = document.body.classList.toggle('menu-chico');
        try { localStorage.setItem('menu', chico ? 'chico' : 'grande'); } catch (e) {}
    });
    document.getElementById('velo-menu')?.addEventListener('click', () => document.body.classList.remove('menu-abierto'));
}

// Botón + de la barra inferior: despliega Nueva factura / Nuevo cliente.
const botonMas = document.getElementById('boton-mas');
if (botonMas) {
    const cerrarMas = () => { document.body.classList.remove('mas-abierto'); botonMas.setAttribute('aria-expanded', 'false'); };
    botonMas.addEventListener('click', (ev) => {
        ev.preventDefault();
        const abierto = document.body.classList.toggle('mas-abierto');
        botonMas.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', (ev) => {
        if (document.body.classList.contains('mas-abierto') && !ev.target.closest('#boton-mas, #menu-mas')) cerrarMas();
    });
    document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') cerrarMas(); });
}

// En el celular las tablas anchas se desplazan de costado dentro de su tarjeta.
document.querySelectorAll('table.tabla').forEach((tabla) => {
    if (tabla.closest('.tabla-scroll, .tabla-movil')) return;
    const caja = document.createElement('div');
    caja.className = 'tabla-movil';
    tabla.parentNode.insertBefore(caja, tabla);
    caja.appendChild(tabla);
});

// ---------- Mensajes con SweetAlert2 (si no cargó, se usan los del navegador) ----------
window.Aviso = (function () {
    const hay = () => typeof window.Swal !== 'undefined';
    const base = () => Swal.mixin({
        buttonsStyling: false,
        reverseButtons: true,
        customClass: { popup: 'sw-popup', confirmButton: 'btn btn-primario', cancelButton: 'btn', denyButton: 'btn btn-peligro', actions: 'sw-acciones' },
    });
    return {
        /** Pregunta y devuelve true si acepta. */
        async confirmar(texto, o = {}) {
            if (!hay()) return window.confirm(texto);
            const opciones = {
                title: o.titulo || '¿Confirmás?', icon: o.icono || (o.peligro ? 'warning' : 'question'),
                showCancelButton: true, confirmButtonText: o.si || 'Sí, continuar', cancelButtonText: o.no || 'Cancelar',
                focusCancel: !!o.peligro,
            };
            if (o.html) opciones.html = o.html; else opciones.text = texto;
            if (o.peligro) opciones.customClass = { popup: 'sw-popup', confirmButton: 'btn btn-peligro', cancelButton: 'btn', actions: 'sw-acciones' };
            const r = await base().fire(opciones);
            return r.isConfirmed;
        },
        /** Pide un texto obligatorio. Devuelve el texto, o null si cancela. */
        async pedirTexto(texto, o = {}) {
            if (!hay()) { const v = window.prompt(texto); return v && v.trim() ? v.trim() : null; }
            const r = await base().fire({
                title: o.titulo || 'Escribí el dato', text: texto, icon: o.icono || 'warning',
                input: o.multilinea ? 'textarea' : 'text', inputPlaceholder: o.placeholder || '',
                inputAttributes: { maxlength: String(o.max || 200), autocomplete: 'off' },
                showCancelButton: true, confirmButtonText: o.si || 'Aceptar', cancelButtonText: o.no || 'Cancelar',
                customClass: o.peligro ? { popup: 'sw-popup', confirmButton: 'btn btn-peligro', cancelButton: 'btn', actions: 'sw-acciones', input: 'sw-input' }
                    : { popup: 'sw-popup', confirmButton: 'btn btn-primario', cancelButton: 'btn', actions: 'sw-acciones', input: 'sw-input' },
                inputValidator: (v) => (!v || !v.trim() ? (o.requerido || 'Este dato es obligatorio.') : undefined),
            });
            return r.isConfirmed ? r.value.trim() : null;
        },
        error(texto, titulo) {
            if (!hay()) { window.alert(texto); return Promise.resolve(); }
            return base().fire({ icon: 'error', title: titulo || 'No se pudo completar', text: texto, confirmButtonText: 'Entendido' });
        },
        aviso(texto, titulo, html) {
            if (!hay()) { window.alert(texto); return Promise.resolve(); }
            return base().fire(html ? { icon: 'warning', title: titulo || 'Revisá', html, confirmButtonText: 'Entendido' }
                : { icon: 'warning', title: titulo || 'Revisá', text: texto, confirmButtonText: 'Entendido' });
        },
        toast(texto, tipo = 'success') {
            if (!hay()) return;
            Swal.fire({ toast: true, position: 'top-end', icon: tipo, title: texto, showConfirmButton: false,
                timer: tipo === 'success' ? 3500 : 6000, timerProgressBar: true, customClass: { popup: 'sw-popup sw-toast' } });
        },
    };
})();

// Formularios y botones con data-confirmar="pregunta" piden confirmación antes de enviarse.
document.addEventListener('submit', (e) => {
    const form = e.target;
    const quien = e.submitter;
    const pregunta = (quien && quien.dataset.confirmar) || form.dataset.confirmar;
    if (!pregunta || form.dataset.confirmado) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const peligro = (quien && quien.classList.contains('btn-peligro')) || form.dataset.peligro !== undefined || (quien && quien.classList.contains('rojo'));
    Aviso.confirmar(pregunta, { peligro, si: (quien && quien.dataset.si) || form.dataset.si }).then((ok) => {
        if (!ok) return;
        form.dataset.confirmado = '1';
        if (quien && quien.name) {
            const oculto = document.createElement('input');
            oculto.type = 'hidden'; oculto.name = quien.name; oculto.value = quien.value;
            form.appendChild(oculto);
        }
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
}, true);

// Mensajes del servidor (guardado, errores): éxito como aviso breve, error como ventana.
(window.MENSAJES || []).forEach((m) => {
    if (m.tipo === 'ok') Aviso.toast(m.mensaje, 'success');
    else Aviso.error(m.mensaje, 'Atención');
});

// ---------- Imprimir sin abrir otra pestaña ----------
// Carga la factura en un marco oculto, abre el diálogo de impresión y, al terminar, el marco desaparece.
window.imprimirOculto = function (url) {
    return new Promise((resolver) => {
        const marco = document.createElement('iframe');
        marco.className = 'marco-impresion';
        marco.setAttribute('aria-hidden', 'true');
        marco.src = url + (url.includes('?') ? '&' : '?') + 'embebido=1';
        let listo = false;
        const terminar = () => {
            if (listo) return;
            listo = true;
            setTimeout(() => marco.remove(), 500);
            resolver();
        };
        marco.addEventListener('load', () => {
            const w = marco.contentWindow;
            w.addEventListener('afterprint', terminar);
            setTimeout(() => { w.focus(); w.print(); terminar(); }, 150); // en Chrome print() espera a que se cierre el diálogo
        });
        document.body.appendChild(marco);
    });
};
document.addEventListener('click', (e) => {
    const enlace = e.target.closest('a[data-imprimir]');
    if (!enlace) return;
    e.preventDefault();
    window.imprimirOculto(enlace.href);
});
