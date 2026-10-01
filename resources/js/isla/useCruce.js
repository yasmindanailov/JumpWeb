/**
 * EL CRUCE (Z6a; el `relevo` de `ParkIsland.jsx`, zip (6)): cuando cambia lo que la isla dice (la frase) o lo que ofrece
 * (la etiqueta de la acción), lo viejo se queda encima, quieto, y se desenfoca y se va (`isla-swap-out`) mientras lo
 * nuevo llega enfocándose (`isla-swap`); la caja cambia de forma con su morph a la vez. Nunca se ve cortado.
 *
 * Devuelve `{ lineaSale, accSale, nL, nA }`: la situación y la etiqueta que se van (480ms, y se quitan solas) y cuántas
 * veces ha cambiado cada una. La LLEGADA no cuenta (`animate` aún apagado): la isla toma su primer estado sin moverse,
 * y por eso lo que entra solo anima a partir del primer cambio (`nL > 0`, `nA > 0`).
 *
 * Un solo vigilante para la situación y la etiqueta, a propósito: con dos, el de la situación podía correr antes que el
 * de las claves y la frase que se va ya sería la nueva.
 */
import { onScopeDispose, ref, watch } from 'vue';

export const claveDeLinea = (s) => `${s.id}|${s.line || ''}|${s.note || ''}`;

export function useCruce({ s, accionKey, animate }) {
    const cruce = ref({ lineaSale: null, accSale: null, nL: 0, nA: 0 });
    let previa = { linea: claveDeLinea(s.value), acc: accionKey.value, sit: s.value };
    let reloj = null;

    watch(() => ({ sit: s.value, acc: accionKey.value }), ({ sit, acc }) => {
        const linea = claveDeLinea(sit);
        const anima = animate.value;
        const c = { ...cruce.value };
        let cambia = false;
        if (previa.linea !== linea) {
            if (anima && previa.sit.line) c.lineaSale = previa.sit;
            if (anima) c.nL += 1;
            cambia = true;
        }
        if (previa.acc !== acc) {
            if (anima && previa.acc) c.accSale = previa.acc;
            if (anima) c.nA += 1;
            cambia = true;
        }
        previa = { linea, acc, sit };
        if (! cambia) return;
        cruce.value = c;
        clearTimeout(reloj);
        if (c.lineaSale || c.accSale) {
            reloj = setTimeout(() => { cruce.value = { ...cruce.value, lineaSale: null, accSale: null }; }, 480);
        }
    }, { flush: 'sync' });

    onScopeDispose(() => clearTimeout(reloj));

    return cruce;
}
