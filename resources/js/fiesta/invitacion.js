/*
 * LA INVITACIÓN DIGITAL del sistema nuevo — el comportamiento de la página y de su recibo (`specs/fiesta-sistema-nuevo.md`
 * §4.5, T2). Lo poco que `InvPagina` hace con React y aquí se hace a mano: el idioma (elegir en el desplegable cambia
 * de idioma), Intro no contesta (la respuesta es siempre un toque), el botón pulsado dice «Enviando» mientras el
 * formulario viaja, «Su ficha» se guarda sola al salir de cada campo y dice «Guardado», y «Contestar por otro hijo»
 * deja el foco en el nombre. ⚠️ Nada de esto ESCRIBE por su cuenta: escriben los formularios. Sin JavaScript la página
 * contesta y guarda igual; la clase `js` se pone AL FINAL: si algo falla, el documento se queda en `no-js`.
 */
/* global document, location, fetch, FormData, setTimeout, clearTimeout */
import './fiesta.css';
import { arranca, enterNoEnvia, idioma, q, qa } from './comun.js';

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

arranca(idioma, barra, ficha, foco);
