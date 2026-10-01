/**
 * **EL HUECO DE LA ACCIÓN, con su relevo** (Z6b; `SlotSwap` de `ParkIsland.jsx`, zip (6)): cuando la acción se vuelve el
 * banner de la razón —o al revés, o un banner deja paso a otro («Confirmando tu pago» → «¡Reservado!»)—, lo que se va se
 * queda encima, quieto, y se desenfoca (`isla-swap-out`) mientras lo nuevo llega enfocándose (`isla-swap`); la caja
 * cambia de forma con su morph a la vez. Un cambio de ETIQUETA de la acción no es un relevo del hueco: lo cruza el propio
 * botón (`useCruce`).
 *
 * `contenido` es un getter de lo que hay ahora: `{ clave, bn }` (un banner), `{ clave: 'act', accion }` o `null`.
 * Devuelve `sale`: lo que se va (su copia), durante 260 ms, o `null`.
 */
import { onScopeDispose, ref, watch } from 'vue';

export const RELEVO_MS = 260;

export function useHueco(contenido) {
    const sale = ref(null);
    let previo = contenido();
    let reloj = null;

    // Lo que se va es la ÚLTIMA versión de lo que había (como el diseño, que guarda el último elemento de cada clave).
    watch(contenido, (ahora) => {
        if (previo && (ahora?.clave ?? '') !== previo.clave) {
            sale.value = previo;
            clearTimeout(reloj);
            reloj = setTimeout(() => { sale.value = null; }, RELEVO_MS);
        }
        previo = ahora;
    }, { flush: 'sync' });

    onScopeDispose(() => clearTimeout(reloj));

    return sale;
}
