/**
 * **EL EXPERIMENTO B3: la cara de la isla y la cabecera a la vista** (la Z6c de `isla-y-landing-nueva.md` §4.27; `esB3` y
 * `enCabecera` de `ParkIsland.jsx`, zip (6)).
 *
 *   · `varianteDe`: la cara, `b3` u `hoy`. La pone el SERVIDOR en el `<html>` antes de pintar (`data-isla-variante`,
 *     `Http\Instancia\VarianteDeIsla`); la prop `variant` de la isla manda sobre ella. Sin ninguna, la de hoy.
 *   · `useEnCabecera`: con el B3, ¿se ve la cabecera de la página (`[data-pj-hero]`)? Mientras se ve, la isla lleva el
 *     botón grande sin su frase; al pasarla, su barra (`reparto()` de `situacion.js`). Se mira AL CREARSE la isla —la
 *     cabecera la pinta el servidor y ya está— con su posición medida en el momento, y después con un
 *     `IntersectionObserver`; si una cabecera monta un poco después, se busca dos veces más (a los 0 y a los 300 ms). Sin
 *     cabecera, la barra desde el principio. Sin B3 o sin el observador, nunca.
 * `doc` y `win`, para probarlo fuera de un navegador (`en-cabecera.test.js`).
 */
import { onScopeDispose, ref } from 'vue';

export const B3 = 'b3';

/** La cara de la isla: la prop si la hay; si no, la del `<html>`; si no, la de hoy. */
export function varianteDe(variant = null, doc = globalThis.document) {
    const pedida = variant || doc?.documentElement?.getAttribute?.('data-isla-variante') || null;

    return pedida === B3 ? B3 : 'hoy';
}

export function useEnCabecera(esB3, { doc = globalThis.document, win = globalThis.window } = {}) {
    const enCabecera = ref(false);

    if (! esB3 || typeof win?.IntersectionObserver === 'undefined' || ! doc?.querySelector) return enCabecera;

    let io = null;
    let reloj = 0;
    let intentos = 0;
    const mira = (cabecera) => {
        const r = cabecera.getBoundingClientRect();
        enCabecera.value = r.bottom > 0 && r.top < win.innerHeight;
        io = new win.IntersectionObserver(([e]) => { enCabecera.value = e.isIntersecting; }, { threshold: 0 });
        io.observe(cabecera);
    };
    const busca = () => {
        const cabecera = doc.querySelector('[data-pj-hero]');

        if (cabecera) mira(cabecera);
        else if (++intentos < 2) reloj = win.setTimeout(busca, 300);
    };
    const cabecera = doc.querySelector('[data-pj-hero]');

    if (cabecera) mira(cabecera);
    else reloj = win.setTimeout(busca, 0);
    onScopeDispose(() => { win.clearTimeout(reloj); if (io) io.disconnect(); });

    return enCabecera;
}
