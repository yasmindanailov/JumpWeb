/**
 * **EL ENLACE QUE ABRE MI CUENTA** (T5a de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): `#mi-cuenta`
 * en cualquier página abre Mi cuenta en la isla, y `#mi-cuenta/<bloque>` la abre en ese bloque —«el enlace de un
 * correo la abre en la tarjeta que toque», dice el diseño (`paginas/mi-cuenta/cuenta.jsx`)—. Es también por donde
 * vuelve quien acaba de entrar dentro de la isla: la página se recarga ya identificada y Mi cuenta aparece sola.
 *
 * Es una PUERTA del lado del cliente, hermana de las del servidor (`Http\Sidebar\AccountDoor`, `/mi-cuenta`). La trae
 * el controlador del paquete SOLO cuando la dirección la lleva (`import()`): la entrada de la landing la paga cada
 * visita a cada página (`SidebarBundleBudgetTest`), y este enlace lo usa casi nadie. Módulo plano, con su `node --test`.
 *
 * ⚠️ El bloque solo admite letras, cifras y guiones: lo que venga detrás de `#` lo escribe cualquiera, y aquí se lee
 *    un NOMBRE de bloque, nunca un selector ni HTML.
 */

/** Los bloques que son otra VISTA de la capa y no un sitio de la lista: Tu QR tiene su propia zona del motor. */
const BLOQUES_QR = ['qr', 'mi-qr'];

/**
 * La zona del motor y el bloque que pide un enlace, o `null` si no es un enlace a Mi cuenta.
 *
 * @param {string|null|undefined} hash  `location.hash`, con su `#`
 * @returns {{zona: 'home'|'card', bloque: string}|null}
 */
export function enlaceDeCuenta(hash) {
    const m = /^#mi-cuenta(?:\/([A-Za-z0-9-]{1,40}))?$/.exec(String(hash ?? ''));

    if (m === null) return null;

    const bloque = m[1] ?? '';

    return { zona: BLOQUES_QR.includes(bloque) ? 'card' : 'home', bloque };
}

/**
 * Abre Mi cuenta con el enlace de la dirección, si lo hay y la carcasa es la ISLA —es su enlace (el diseño), y el cajón
 * lateral no protege sus zonas privadas sin sesión—. En una página ajena la carcasa no se sabe hasta traer el arranque:
 * se trae (`bootSpaEngine`), y se decide con él.
 *
 * @param {object} cajon  el controlador (`controller.js`)
 * @param {{hash: string, carcasaSabida: boolean, isla: string}} deps
 * @returns {Promise<void>|undefined}
 */
export function abrirEnlaceDeCuenta(cajon, { hash, carcasaSabida, isla }) {
    const enlace = enlaceDeCuenta(hash);

    if (enlace === null || cajon.isOpen) return undefined;

    const abrir = () => {
        if (cajon.carcasaActual() === isla && ! cajon.isOpen) cajon.openAccount({ preventDefault() {} }, enlace.zona, { desde: 'enlace' });
    };

    if (carcasaSabida) return abrir();

    return cajon.bootSpaEngine()?.then?.(abrir);
}
