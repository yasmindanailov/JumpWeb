/*
 * LA ISLA DE LAS PÁGINAS DE ENLACE, en marcha (`navigation/LinkIsland.jsx` del zip (6); `fiesta-sistema-nuevo.md` §4.18,
 * `#814`). La pieza la pinta el servidor (`x-fiesta.isla`) con su cara de partida, que es un formulario o un botón de
 * enviar de verdad: sin JavaScript va en el flujo y la página funciona igual. Aquí solo se mueve:
 *  · FIJA abajo (la clase `js` de la página la saca del flujo) y su HUECO al final del contenido, con su alto
 *    (`--link-island-space`), que se mantiene mientras se aparta para que la página no salte;
 *  · llega tras `data-delay` (el confeti de la invitación); si el foco entra antes en ella, llega ya;
 *  · se aparta al escribir en un campo de la página que NO es suyo (el de la respuesta lo es: con el teclado, se queda) y
 *    con una capa `aria-modal` abierta (el vídeo del parque, el descargo);
 *  · se esconde mientras la página ya enseña lo que diría (`data-oculta-si`, un selector: regla 3);
 *  · el MORPH: la caja mide lo que lleva dentro y el alto se anima;
 *  · confirma y se va: un toque en algo con `data-isla-hecho` (el calendario del recibo) la quita.
 * Las reglas, sin DOM y con su `node --test`, en `logica.js` (`islaSale`, `asoma`, `huecoIsla`).
 */
import { q, qa } from './comun.js';
import { asoma, huecoIsla, islaSale } from './logica.js';

/** Los campos en los que se escribe: con el teclado abierto en uno que no es de la isla, ésta se aparta. */
const CAMPO = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=range]):not([type=hidden]),textarea,[contenteditable=true]';

/** Arranca la isla de la página, si la hay. */
export function islaDeEnlace(raiz = document) {
    const isla = q('[data-isla-enlace]', raiz);
    if (!isla) return null;

    const caja = q('[data-isla-caja]', isla);
    const dentro = q('[data-isla-dentro]', isla);
    const de = document.documentElement;
    const delay = Number(isla.dataset.delay || 0);
    const ocultaSi = isla.dataset.ocultaSi || '';
    const estado = { llegada: delay <= 0, vista: false, sin: false, capa: false, escribiendo: false };
    let alto = null;

    const suya = () => q('[data-isla-cara]', isla)?.dataset.islaCara === 'respuesta';
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

    // ── No repite la pantalla: con lo que diría a la vista, se esconde ──
    if (ocultaSi) {
        let tVista = null;
        const miraVista = () => {
            const vista = asoma(qa(ocultaSi).map((el) => el.getBoundingClientRect()), window.innerHeight);
            if (vista !== estado.vista) pon({ vista });
        };
        const tarde = () => { clearTimeout(tVista); tVista = setTimeout(miraVista, 120); };
        window.addEventListener('scroll', tarde, { passive: true });
        window.addEventListener('resize', tarde);
        setInterval(miraVista, 600);
        estado.vista = asoma(qa(ocultaSi).map((el) => el.getBoundingClientRect()), window.innerHeight);
    }

    // ── Confirma y se va: lo que ya está hecho (el calendario añadido) la quita ──
    qa('[data-isla-hecho]', isla).forEach((el) => el.addEventListener('click', () => setTimeout(() => pon({ sin: true }), 0)));

    aplica();

    return { isla, pon };
}
