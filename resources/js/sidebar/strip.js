/**
 * El DESPLAZAMIENTO de una tira con el RATÓN (`DECISIONES #241`).
 *
 * ⚠️ **Por qué existe.** Las dos tiras del embudo —días y horas— se diseñaron para el dedo (`#239`),
 * y con el dedo se deslizan. Con un ratón **no**: la barra va oculta (`scrollbar-width: none`, y en
 * un cajón de 390 px se comía 15 de los 350 útiles), así que en escritorio la única salida era
 * desplegar el calendario. `[OWNER, 2026-08-28]`: «el UX se queda a medias». Estas flechas son esa
 * salida, y **solo existen donde hay ratón**.
 *
 * **Módulo plano, sin Vue**, por lo mismo que `calendar.js`: así se prueba con `node --test` sin
 * navegador y las dos tiras comparten UNA implementación en vez de dos que divergen.
 *
 * ⚠️ Aquí no se decide nada de negocio: es aritmética de una caja con scroll.
 */

/**
 * Margen de tolerancia, en píxeles.
 *
 * ⚠️ **No es defensivo, es necesario**: `scrollLeft` es fraccionario (zoom del navegador, pantallas
 * de densidad alta), así que al final del recorrido `scrollLeft + clientWidth` puede quedarse en
 * `scrollWidth - 0.5` para siempre. Sin la tolerancia la flecha «siguiente» **nunca se apagaría**.
 */
const EPSILON = 1;

/**
 * ¿Hay recorrido a cada lado?
 *
 * Devuelve los dos a `false` cuando la tira cabe entera, que es lo que hace desaparecer las flechas
 * en vez de dejarlas inertes: una flecha que no lleva a ningún sitio es peor que no tenerla.
 *
 * @param {?{scrollLeft: number, clientWidth: number, scrollWidth: number}} track
 * @returns {{prev: boolean, next: boolean}}
 */
export function scrollState(track) {
    if (! track) {
        return { prev: false, next: false };
    }

    const { scrollLeft = 0, clientWidth = 0, scrollWidth = 0 } = track;

    return {
        prev: scrollLeft > EPSILON,
        next: scrollLeft + clientWidth < scrollWidth - EPSILON,
    };
}

/**
 * Cuánto desplaza un clic de flecha.
 *
 * **Una pantalla menos un chip**, no una pantalla entera: dejar un chip a la vista es lo que dice
 * hacia dónde se ha movido la lista. El «un chip» se estima con el 20 % del ancho visible en vez de
 * medir un hijo — la tira no siempre empieza por un chip (la de días abre con el rótulo del mes) y
 * medir el primer nodo daría un salto distinto según dónde esté parada.
 *
 * ⚠️ **El ajuste (`scroll-snap`) remata la posición**, así que este número no tiene que ser exacto:
 * el navegador coloca el chip más cercano. Por eso no hay aritmética de anchos de chip aquí.
 *
 * @param {?{clientWidth: number}} track
 */
export function scrollStep(track) {
    return Math.max(1, Math.round((track?.clientWidth ?? 0) * 0.8));
}
