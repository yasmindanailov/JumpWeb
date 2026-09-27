/**
 * **EL TECLADO DEL MÓVIL EN LA CAPA GRANDE** (la compra y Mi cuenta; `ParkIsland.jsx` del zip del 26-09, «La isla y la
 * compra · conversión»; `isla-y-landing-nueva.md` §4.16): lo que se DECIDE, puro y probado. Los efectos, en
 * `useTeclado.js`.
 *
 *   · En iOS (y en Android por defecto) abrir el teclado no encoge la página, solo la parte visible
 *     (`visualViewport`), y la capa —fija a toda la pantalla— se quedaba con la acción debajo del teclado.
 *   · Intro en un campo pasa al siguiente y, en el último, hace la acción del paso (como enviar un formulario): se
 *     rellena entero sin cerrar el teclado. El teclado lo dice: «Siguiente» entre campos e «Ir» en el último.
 */

/** Los campos que Intro recorre: los que se escriben, sin casillas ni opciones (esas se tocan). */
export const CAMPOS = 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([disabled]), select:not([disabled]), textarea:not([disabled])';

/** Por debajo de esto, lo que tapa la ventana visible no es un teclado (una barra del navegador, el zoom). */
export const TAPA_MINIMA = 120;

/**
 * Lo que se ve con el teclado abierto: `{ h, top }` de la ventana visible, o `null` si no hay teclado. `anterior`
 * se devuelve tal cual si no ha cambiado (el mismo objeto: quien lo guarda no repinta).
 */
export function leerTeclado({ alto, visible, desplazado }, anterior = null) {
    if (! visible || alto - visible <= TAPA_MINIMA) return null;
    const n = { h: Math.round(visible), top: Math.round(desplazado || 0) };

    return anterior && anterior.h === n.h && anterior.top === n.top ? anterior : n;
}

/**
 * Qué hace Intro en el campo `actual` de la lista `campos` (los visibles, en orden): pasar al `siguiente` (su índice),
 * `enviar` (el último: la acción del paso) o nada (`null`: no es un campo de los que se recorren, o no está en la lista).
 */
export function alPulsarIntro(campos, actual) {
    const i = campos.indexOf(actual);
    if (i < 0) return null;

    return i < campos.length - 1 ? { que: 'siguiente', indice: i + 1 } : { que: 'enviar' };
}

/** Si un elemento es de los que Intro recorre (un campo de texto, no una casilla ni un botón). */
export function esCampoDeIntro(el) {
    return Boolean(el) && el.tagName === 'INPUT' && ! /^(checkbox|radio|button|submit)$/.test(el.type || '');
}

/** La pista del teclado para cada uno de `n` campos: «Siguiente» (`next`) y, el último, «Ir» (`go`). */
export function pistasDeIntro(n) {
    return Array.from({ length: n }, (_, i) => (i < n - 1 ? 'next' : 'go'));
}

/**
 * Cuánto hay que desplazar la caja para que el campo con el foco no quede tapado (16px de margen, 24 de aire): positivo
 * baja, negativo sube, 0 si ya se ve.
 */
export function desplazarHastaVer(caja, campo) {
    if (campo.bottom > caja.bottom - 16) return campo.bottom - caja.bottom + 24;
    if (campo.top < caja.top + 16) return -(caja.top - campo.top + 24);

    return 0;
}
