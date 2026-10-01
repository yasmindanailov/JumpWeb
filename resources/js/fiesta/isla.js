/*
 * LA ISLA DE LAS PÁGINAS DE ENLACE, en marcha (`navigation/LinkIsland.jsx` del zip (6); `fiesta-sistema-nuevo.md` §4.18,
 * `#814`). La pieza la pinta el servidor (`x-fiesta.isla`) con su cara de partida, que es un formulario o un botón de
 * enviar de verdad, y las otras caras como PLANTILLAS (`<template data-isla-plantilla>`): sin JavaScript va en el flujo y
 * la página funciona igual. Aquí solo se mueve:
 *  · FIJA abajo (la clase `js` de la página la saca del flujo) y su HUECO al final del contenido, con su alto
 *    (`--link-island-space`), que se mantiene mientras se aparta para que la página no salte;
 *  · llega tras `data-delay` (el confeti de la invitación); si el foco entra antes en ella, llega ya;
 *  · se aparta al escribir en un campo de la página que NO es suyo (el de la respuesta lo es: con el teclado, se queda) y
 *    con una capa `aria-modal` abierta (el vídeo del parque, el descargo);
 *  · se esconde mientras la página ya enseña lo que diría (`data-oculta-si`, o el de la cara: regla 3);
 *  · el MORPH: la caja mide lo que lleva dentro y el alto se anima; y el RELEVO al cambiar de cara (`cara()`): lo que se va
 *    se desenfoca encima mientras lo nuevo entra;
 *  · confirma y se va: el AVISO (`aviso()`, la barra lima que se vacía) y lo que ya está hecho (`data-isla-hecho`).
 * Las reglas, sin DOM y con su `node --test`, en `logica.js` (`islaSale`, `asoma`, `huecoIsla`, `caraDeLaLista`,
 * `faltaParaFirmar`).
 */
import { asoma, huecoIsla, islaSale } from './logica.js';

// ⚠️⚠️ `q` y `qa` AQUÍ y no de `comun.js`, MEDIDO (01-10): con la isla en dos entradas (la invitación y la lista), importarlos
//    de allí hacía que Rolldown repartiera de otra forma los trozos de TODA la web —el runtime de Vue aparte, el flujo de
//    compra en su trozo…—: +2,68 KiB a la descarga del motor del cajón y ocho techos de `SidebarBundleBudgetTest` en rojo,
//    sin una línea más de código del cajón. Mismo código, mismo reparto: es determinista.
const q = (sel, raiz = document) => raiz.querySelector(sel);
const qa = (sel, raiz = document) => [...raiz.querySelectorAll(sel)];

/** Los campos en los que se escribe: con el teclado abierto en uno que no es de la isla, ésta se aparta. */
const CAMPO = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=range]):not([type=hidden]),textarea,[contenteditable=true]';

/** Arranca la isla de la página, si la hay. Devuelve lo que la página puede pedirle (`cara`, `aviso`, `pon`). */
export function islaDeEnlace(raiz = document) {
    const isla = q('[data-isla-enlace]', raiz);
    if (!isla) return null;

    const caja = q('[data-isla-caja]', isla);
    const dentro = q('[data-isla-dentro]', isla);
    const contenedor = q('[data-isla-cara]', isla);
    const de = document.documentElement;
    const delay = Number(isla.dataset.delay || 0);
    const estado = { llegada: delay <= 0, vista: false, sin: false, capa: false, escribiendo: false };
    let alto = null;
    let cual = contenedor?.dataset.islaCara || '';
    let ocultaCara = null;

    const suya = () => contenedor?.dataset.islaCara === 'respuesta';
    const hueco = () => de.style.setProperty('--link-island-space', huecoIsla(alto, estado.sin));
    const aplica = () => {
        const sale = islaSale({ ...estado, suya: suya() });
        isla.dataset.sale = sale ? '1' : '0';
        if (sale) isla.setAttribute('aria-hidden', 'true'); else isla.removeAttribute('aria-hidden');
        hueco();
    };
    const pon = (cambio) => { Object.assign(estado, cambio); aplica(); };

    // ── El morph: la caja toma el alto de lo que lleva dentro (y el borde), y el hueco con él ──
    const mide = () => {
        alto = dentro.offsetHeight;
        caja.style.height = `${alto + 2}px`;
        hueco();
    };
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(mide).observe(dentro);
    mide();

    // ── Llega tras el confeti; si el foco entra antes (`#rsvp-nino`), ya ──
    if (!estado.llegada) setTimeout(() => pon({ llegada: true }), delay);
    isla.addEventListener('focusin', () => { if (!estado.llegada) pon({ llegada: true }); });

    // ── Se aparta al escribir en un campo que no es suyo; vuelve al cerrar el teclado ──
    let tEscribe = null;
    const esCampo = (el) => Boolean(el && el.matches && el.matches(CAMPO) && !isla.contains(el));
    const miraFoco = () => {
        clearTimeout(tEscribe);
        if (esCampo(document.activeElement)) pon({ escribiendo: true });
        else tEscribe = setTimeout(() => pon({ escribiendo: esCampo(document.activeElement) }), 220);
    };
    document.addEventListener('focusin', miraFoco);
    document.addEventListener('focusout', miraFoco);

    // ── Con una capa abierta (`aria-modal`: el vídeo del parque, el descargo), no va encima ──
    const miraCapa = () => {
        const capa = qa('[aria-modal="true"]').some((el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden');
        if (capa !== estado.capa) pon({ capa });
    };
    setInterval(miraCapa, 400);

    // ── No repite la pantalla: con lo que diría a la vista, se esconde (el selector de la cara manda sobre el de la isla) ──
    const selector = () => (ocultaCara !== null ? ocultaCara : (isla.dataset.ocultaSi || ''));
    const vistaAhora = () => (selector() ? asoma(qa(selector()).map((el) => el.getBoundingClientRect()), window.innerHeight) : false);
    let tVista = null;
    const miraVista = () => { const vista = vistaAhora(); if (vista !== estado.vista) pon({ vista }); };
    const tarde = () => { clearTimeout(tVista); tVista = setTimeout(miraVista, 120); };
    window.addEventListener('scroll', tarde, { passive: true });
    window.addEventListener('resize', tarde);
    setInterval(miraVista, 600);
    estado.vista = vistaAhora();

    // ── Confirma y se va: lo que ya está hecho (el calendario añadido, la invitación enviada) la quita ──
    const enganchaHecho = (raizCara) => qa('[data-isla-hecho]', raizCara).forEach((el) => el.addEventListener('click', () => setTimeout(() => pon({ sin: true }), 0)));
    enganchaHecho(isla);

    /** Pone en la cara lo que cambia: la etiqueta, su línea, el punto, si es la principal y si está ocupada. */
    const rellena = (datos) => {
        const etiqueta = q('[data-isla-label]', contenedor);
        if (etiqueta && datos.label !== undefined) etiqueta.textContent = datos.label;
        const sub = q('[data-isla-sub]', contenedor);
        if (sub && datos.sub !== undefined) { q('[data-isla-sub-texto]', sub).textContent = datos.sub; sub.hidden = !datos.sub; }
        const punto = q('[data-isla-punto]', contenedor);
        if (punto && datos.punto !== undefined) { punto.hidden = !datos.punto; punto.style.background = datos.punto || ''; }
        const barra = q('.fi-isla-barra', contenedor);
        if (barra && datos.primary !== undefined) barra.classList.toggle('fi-isla-barra--primary', Boolean(datos.primary));
        if (barra && datos.ocupada !== undefined) {
            barra.classList.toggle('fi-isla-barra--ocupada', Boolean(datos.ocupada));
            if (datos.ocupada) barra.setAttribute('aria-busy', 'true'); else barra.removeAttribute('aria-busy');
        }
    };

    /**
     * Cambia de cara: `nombre` es la de una plantilla (`data-isla-plantilla`), o `null` para irse (regla 5). Con `ocultaSi`,
     * esa cara se esconde mientras la página lo enseña. El relevo, solo si la isla está a la vista: fuera no se ve.
     */
    const cara = (nombre, datos = {}) => {
        if (nombre === null) { pon({ sin: true }); return; }
        if (nombre !== cual) {
            const plantilla = q(`template[data-isla-plantilla="${nombre}"]`, isla);
            if (!plantilla) return;
            if (isla.dataset.sale === '0') {
                q('.fi-isla-sale', caja)?.remove();
                const sale = document.createElement('div');
                sale.className = 'fi-isla-sale';
                sale.setAttribute('aria-hidden', 'true');
                sale.append(...[...contenedor.childNodes].map((n) => n.cloneNode(true)));
                caja.append(sale);
                setTimeout(() => sale.remove(), 320);
            }
            contenedor.replaceChildren(plantilla.content.cloneNode(true));
            contenedor.classList.remove('is-entra');
            void contenedor.offsetWidth;
            contenedor.classList.add('is-entra');
            contenedor.dataset.islaCara = nombre;
            cual = nombre;
            enganchaHecho(contenedor);
        }
        ocultaCara = datos.ocultaSi ?? null;
        rellena(datos);
        pon({ sin: false, vista: vistaAhora() });
    };

    /** El aviso: lo hecho, un momento (`ms`), con la barra lima que se vacía; encima se para y un toque lo cierra. */
    const aviso = (titulo, sub = '', ms = 2600) => new Promise((listo) => {
        cara('aviso', { label: titulo, sub });
        contenedor.style.setProperty('--aviso-ms', `${ms}ms`);
        let hecho = false;
        const fin = () => { if (!hecho) { hecho = true; listo(); } };
        q('[data-isla-aviso]', contenedor)?.addEventListener('click', fin, { once: true });
        q('[data-isla-resto]', contenedor)?.addEventListener('animationend', fin, { once: true });
    });

    aplica();

    return { isla, pon, cara, aviso };
}
