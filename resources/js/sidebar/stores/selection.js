import { defineStore } from 'pinia';

/**
 * Lo que se está configurando ANTES de entrar en la cesta — cantidad, complementos y respuestas del
 * evento (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Es la línea en construcción, no la cesta.** En cuanto se añade, viaja a `stores/cart.js` y esto
 * se vacía. Separarlas no es cosmético: la cesta se PERSISTE y esto no, porque aquí viven las
 * respuestas del evento —nombre de un menor, edad, alergias— que no pueden salir de memoria (`#38(d)`,
 * art. 9 del RGPD).
 */
export const useSelectionStore = defineStore('selection', {
    state: () => ({
        /** Cuántas plazas. El suelo lo pone el producto y el techo la hora elegida. */
        quantity: 0,

        /** Los complementos que ofrece el producto, tal y como llegan de la API. */
        addons: { groups: [], singles: [] },

        /** La elección actual de cada grupo EXCLUYENTE (Menú 1 ⊻ Menú 2). */
        choices: [],

        /** Las cantidades elegidas de los complementos sumables. */
        quantities: [],

        /** Las respuestas de los campos del evento. ⚠️ Solo en memoria, nunca persistidas. */
        eventData: {},

        /** La línea ya resuelta que devuelve el servidor al validar, o `null`. */
        line: null,

        /** Lo que el servidor dice que se está comprando de verdad, ya resuelto. */
        resolved: [],
    }),

    actions: {
        setQuantity(quantity) {
            this.quantity = quantity;
        },

        setAddons(addons) {
            this.addons = addons ?? { groups: [], singles: [] };
        },

        setChoices(choices) {
            this.choices = Array.isArray(choices) ? choices : [];
        },

        setQuantities(quantities) {
            this.quantities = Array.isArray(quantities) ? quantities : [];
        },

        setEventData(data) {
            this.eventData = data ?? {};
        },

        answer(key, value) {
            this.eventData = { ...this.eventData, [key]: value };
        },

        setLine(line) {
            this.line = line ?? null;
        },

        setResolved(resolved) {
            this.resolved = Array.isArray(resolved) ? resolved : [];
        },

        /** Vacía la línea en construcción entera. Se llama al elegir otro producto y al añadir. */
        clear() {
            this.quantity = 0;
            this.addons = { groups: [], singles: [] };
            this.choices = [];
            this.quantities = [];
            this.eventData = {};
            this.line = null;
            this.resolved = [];
        },
    },
});
