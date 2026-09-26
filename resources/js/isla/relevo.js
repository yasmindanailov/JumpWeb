/**
 * **EL RELEVO ENTRE ISLAS** (Z3 de `specs/isla-y-landing-nueva.md` §4.14, `DECISIONES #782`).
 *
 * En el diseño la isla es UNA: la píldora crece hasta la compra y encoge al cerrarla, «una transformación, no un salto».
 * En el producto son tres montajes de la misma `IslaFlotante` —la de la página, la de la compra y la de Mi cuenta, cada
 * una con su app— y hasta aquí se relevaban de golpe: la píldora desaparecía y la compra salía entera (medido el 26-09:
 * 0 fotogramas de transición, y la primera vez ~290ms sin ninguna isla). Este módulo es la memoria compartida del
 * relevo: la isla que entra pregunta de dónde viene y crece desde ahí.
 *
 *   · `registrar(el)` / `soltar(el)`: cada isla montada se apunta y, al irse, deja su caja (por si la que entra llega
 *     un instante después: dos apps no se desmontan y montan en un orden fijo).
 *   · `desde(el)`: la caja de OTRA isla que siga en pantalla (la píldora que espera a ser relevada) o, si ya se fue, la
 *     que dejó hace menos de `VIDA`. Se toma UNA vez; y la isla relevada ya no deja la suya al irse (no es de nadie).
 *   · `vuelo`: el nombre del plan elegido en el selector, que la compra hace viajar a la cabecera de su paso (02b).
 *
 * Solo DOM y reloj: lo que decide está en funciones puras (`cajaDe`, `vigente`) y se prueba con `node --test`.
 */

/** Cuánto vale la caja de una isla que se fue: lo que tarda en montarse la siguiente (medido: 1 fotograma, o ~290ms la primera vez). */
export const VIDA = 700;

const islas = new Set();
const relevadas = new WeakSet();
let ultima = null;
let vuelo = null;

/** La caja de un elemento, como la necesita el morfeo: dónde, cuánto y su radio real (una píldora de 999px mide h/2). */
export function cajaDe(rect, radio) {
    return { x: rect.left, y: rect.top, w: rect.width, h: rect.height, radio: Math.min(radio || 0, rect.height / 2) };
}

/** Si una caja dejada sigue valiendo. */
export const vigente = (caja, ahora) => Boolean(caja) && ahora - caja.t < VIDA;

// `velo`: la que se va llevaba el velo (un panel, la compra): la que entra lo funde al irse, en vez de quitarlo de golpe.
const medir = (el) => ({ ...cajaDe(el.getBoundingClientRect(), parseFloat(getComputedStyle(el).borderTopLeftRadius)), velo: el.dataset.islaVelo === '1' });

export function registrar(el) {
    if (el) islas.add(el);
}

export function soltar(el) {
    if (! el) return;
    islas.delete(el);
    if (! relevadas.has(el) && el.isConnected) ultima = { ...medir(el), t: performance.now() };
}

/** De dónde viene la isla `el` que acaba de montarse, o `null` si no viene de ninguna (la página recién cargada). */
export function desde(el, ahora = performance.now()) {
    for (const otra of islas) {
        if (otra === el || ! otra.isConnected) continue;
        relevadas.add(otra);
        ultima = null;

        return medir(otra);
    }
    const u = ultima;

    ultima = null;

    return vigente(u, ahora) ? u : null;
}

/** El nombre del plan que viaja a la compra (lo deja el selector; lo toma la compra al abrirse). */
export function dejarVuelo(v) {
    vuelo = v ? { ...v, t: performance.now() } : null;
}

export function tomarVuelo(ahora = performance.now()) {
    const v = vuelo;

    vuelo = null;

    return v && ahora - v.t < 4000 ? v : null;
}
