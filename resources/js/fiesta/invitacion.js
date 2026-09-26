/*
 * LA INVITACIÓN DIGITAL del sistema nuevo — el comportamiento de la página y de su recibo (`specs/fiesta-sistema-nuevo.md`
 * §4.5, T2). Lo poco que `InvPagina` hace con React y aquí se hace a mano (el idioma no: desde `#748` son enlaces al
 * pie): Intro no contesta (la respuesta es siempre un toque), el botón pulsado dice «Enviando» mientras el
 * formulario viaja, «Su ficha» se guarda sola al salir de cada campo y dice «Guardado», y «Contestar por otro hijo»
 * deja el foco en el nombre. ⚠️ Nada de esto ESCRIBE por su cuenta: escriben los formularios. Sin JavaScript la página
 * contesta y guarda igual; la clase `js` se pone AL FINAL: si algo falla, el documento se queda en `no-js`.
 */
/* global document, window, location, fetch, FormData, setTimeout, clearTimeout */
import './fiesta.css';
import { arranca, enterNoEnvia, firma, menores, q, qa } from './comun.js';

/* ── «Ver el parque» (F1c, `content/ClipViewer.jsx`): la píldora abre el visor; el vídeo suena (si el navegador lo veta,
   sin sonido), un toque lo pausa y enseña el triángulo, se cierra con la X, con Escape, tocando fuera o deslizando hacia
   abajo, y «Vamos» lo cierra y lleva al nombre de la respuesta. El foco vuelve a donde estaba. ────────────────────── */
function visor() {
    const dialogo = q('[data-visor]');
    const abrir = q('[data-visor-abrir]');
    if (!dialogo || !abrir) return;
    const video = q('[data-visor-video]', dialogo);
    const pausa = q('[data-visor-pausa]', dialogo);
    const cerrarBoton = q('[data-visor-cerrar]', dialogo);
    const escena = q('[data-visor-escena]', dialogo);
    const estrecho = window.matchMedia('(max-width: 640px)');
    const ajusta = () => dialogo.classList.toggle('inv-visor--estrecho', estrecho.matches);
    ajusta();
    estrecho.addEventListener('change', ajusta);
    let ultimoFoco = null;
    let desbordamiento = '';
    let toque = null;
    const cerrar = () => {
        if (dialogo.hidden) return;
        if (video) video.pause();
        dialogo.hidden = true;
        abrir.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = desbordamiento;
        window.removeEventListener('keydown', teclas);
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
    };
    const teclas = (e) => { if (e.key === 'Escape') { e.preventDefault(); cerrar(); } };
    const abre = () => {
        ultimoFoco = document.activeElement;
        desbordamiento = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogo.hidden = false;
        abrir.setAttribute('aria-expanded', 'true');
        if (pausa) pausa.hidden = true;
        if (video) {
            video.muted = false;
            const p = video.play();
            if (p && p.catch) p.catch(() => { video.muted = true; video.play().catch(() => {}); });
        }
        setTimeout(() => { if (cerrarBoton) cerrarBoton.focus(); }, 30);
        window.addEventListener('keydown', teclas);
    };
    abrir.addEventListener('click', abre);
    if (cerrarBoton) cerrarBoton.addEventListener('click', cerrar);
    dialogo.addEventListener('pointerdown', (e) => { if (e.target === dialogo) cerrar(); });
    if (video) {
        video.addEventListener('click', () => {
            if (video.paused) { video.play().catch(() => {}); if (pausa) pausa.hidden = true; } else { video.pause(); if (pausa) pausa.hidden = false; }
        });
    }
    if (escena) {
        escena.addEventListener('touchstart', (e) => { const t = e.touches[0]; toque = { x: t.clientX, y: t.clientY }; }, { passive: true });
        escena.addEventListener('touchend', (e) => {
            if (!toque) return;
            const t = e.changedTouches[0];
            const dx = t.clientX - toque.x;
            const dy = t.clientY - toque.y;
            toque = null;
            if (dy > 90 && Math.abs(dy) > Math.abs(dx)) cerrar();
        }, { passive: true });
    }
    const accion = q('[data-visor-accion]', dialogo);
    if (accion) {
        accion.addEventListener('click', (e) => {
            e.preventDefault();
            cerrar();
            const campo = q('#rsvp-nino');
            if (!campo) { location.hash = '#rsvp-nino'; return; }
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => campo.focus({ preventScroll: true }), 350);
        });
    }
}

/* ── La barra: Intro cierra el teclado y no contesta; al pulsar, «Enviando» y el otro botón se bloquea. ──────── */
function barra() {
    const form = q('[data-rsvp]');
    if (!form) return;
    enterNoEnvia(q('[data-rsvp-nombre]', form));
    form.addEventListener('submit', (e) => {
        const pulsado = e.submitter;
        if (!pulsado) return;
        qa('button[type="submit"]', form).forEach((b) => {
            if (b !== pulsado) b.disabled = true;
        });
        pulsado.setAttribute('aria-busy', 'true');
        const texto = form.dataset.enviando || '';
        if (texto) pulsado.textContent = texto;
    });
}

/* ── «Su ficha»: se guarda sola al salir de cada campo (el mismo POST del formulario, por fetch) y lo dice. ──── */
function ficha() {
    const form = q('[data-ficha]');
    if (!form) return;
    const ok = q('[data-ficha-ok]');
    const guardar = q('[data-ficha-guardar]', form);
    // Con JavaScript el Guardar sobra (se guarda al salir de cada campo); vuelve solo si el guardado por detrás falla.
    if (guardar) guardar.hidden = true;
    let ultimo = new FormData(form);
    const mismo = (a, b) => [...a.entries()].every(([k, v]) => b.get(k) === v) && [...b.entries()].every(([k, v]) => a.get(k) === v);
    let temporizador = null;
    const dilo = (texto) => {
        if (!ok) return;
        ok.textContent = '';
        if (texto) {
            const icono = q('template[data-icono-ok]');
            if (icono) ok.appendChild(icono.content.cloneNode(true));
            ok.appendChild(document.createTextNode(texto));
            if (temporizador) clearTimeout(temporizador);
            temporizador = setTimeout(() => { ok.textContent = ''; }, 2400);
        }
    };
    const envia = async () => {
        const datos = new FormData(form);
        if (mismo(datos, ultimo)) return;
        try {
            const r = await fetch(form.action, {
                method: 'POST',
                body: datos,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error(String(r.status));
            const cuerpo = await r.json();
            ultimo = datos;
            if (cuerpo && cuerpo.saved) dilo(ok ? ok.dataset.guardado || '' : '');
            else if (guardar) guardar.hidden = false;
        } catch {
            // Si el guardado por detrás no sale, vuelve el botón: el formulario de siempre sigue ahí.
            if (guardar) guardar.hidden = false;
        }
    };
    qa('[data-ficha-campo]', form).forEach((campo) => campo.addEventListener('blur', envia));
    form.addEventListener('submit', (e) => {
        // Con JavaScript, Guardar también va por detrás: nadie recarga el recibo por dejar una edad.
        if (guardar && guardar.hidden === false) return;
        e.preventDefault();
        envia();
    });
}

/* ── Llegar con `#rsvp-nino` («Contestar por otro hijo», o «Vamos» del vídeo): el foco, en el nombre. ─────────── */
function foco() {
    if (location.hash !== '#rsvp-nino') return;
    const campo = q('#rsvp-nino');
    if (campo) setTimeout(() => campo.focus({ preventScroll: false }), 60);
}

// La firma dentro del recibo (F6a): el mismo comportamiento que la página de la autorización (`comun.js`).
arranca(visor, barra, ficha, menores, firma, foco);
