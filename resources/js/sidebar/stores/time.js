import { defineStore } from 'pinia';
import { maxQuantityFor, timeAt } from '../offer.js';

/**
 * El estado del paso 3 — **la HORA** (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Aquí no se decide ninguna regla de aforo.** Qué horas hay, cuántas plazas quedan y cuál es el
 * tope contratable lo resuelve el servidor (`AFORO-02`), y la lectura de esos dos números vive en
 * `offer.js`. Este store solo guarda lo que llegó y cuál está elegida.
 *
 * ⚠️⚠️ **Los DOS números de una hora no son el mismo, y confundirlos es un fallo de dinero**:
 * `available` es para MOSTRAR y `max_quantity` para ACOTAR el selector. En un pack **no coinciden**
 * —una plaza de pack consume varias del aforo—, así que el tope se saca SIEMPRE de `max_quantity`.
 */
export const useTimeStore = defineStore('time', {
    state: () => ({
        /** Las horas que la API ofrece para el día elegido, tal cual llegan. */
        offered: [],

        /** La hora elegida (`HH:MM`), o `null`. */
        selected: null,
    }),

    getters: {
        /** La hora elegida con sus dos números, o `null` si aún no hay ninguna. */
        current: (state) => timeAt(state.offered, state.selected),

        /** El techo del selector de cantidad. ⚠️ `max_quantity`, NUNCA `available`. */
        maxQuantity: (state) => maxQuantityFor(state.offered, state.selected),
    },

    actions: {
        setOffer(times) {
            this.offered = Array.isArray(times) ? times : [];
        },

        select(time) {
            this.selected = time;
        },

        /** Olvida la hora elegida y conserva la oferta (volver al paso 3 desde el 4). */
        clearSelection() {
            this.selected = null;
        },

        /** Olvida las dos cosas: la oferta de horas es de UN día, y al cambiar de día ya no vale. */
        reset() {
            this.offered = [];
            this.selected = null;
        },
    },
});
