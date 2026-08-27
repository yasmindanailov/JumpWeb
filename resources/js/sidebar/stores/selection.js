import { defineStore } from 'pinia';
import { toggleDependent, trimToQuantity } from '../assignment.js';

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

        /**
         * Los menores a cargo para los que son estas entradas (Fase 6 · tanda 4, `menores-a-cargo.md`
         * §9.9.3 D9): solo IDS, un conjunto acotado por la cantidad. Viajan a la cesta al añadir.
         */
        dependentIds: [],

        /** La línea ya resuelta que devuelve el servidor al validar, o `null`. */
        line: null,

        /** Lo que el servidor dice que se está comprando de verdad, ya resuelto. */
        resolved: [],
    }),

    actions: {
        setQuantity(quantity) {
            this.quantity = quantity;
            // Nunca más menores que unidades: al bajar la cantidad se quitan los últimos marcados.
            this.dependentIds = trimToQuantity(this.dependentIds, quantity);
        },

        /** Marca o desmarca un menor para estas entradas. El tope es la cantidad (`assignment.js`). */
        toggleDependent(id) {
            this.dependentIds = toggleDependent(this.dependentIds, id, this.quantity);
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

        /**
         * Pide al servidor los complementos del producto con la configuración actual, y guarda lo que
         * el DOMINIO resolvió.
         *
         * ⚠️ **El dinero del paso 3 viene de aquí y NO se compone**: `line.total_cents` lo publica el
         * endpoint desde 4.3·2 precisamente para que nadie sume `subtotal_cents` con
         * `addons_total_cents` — salen de dos recorridos distintos del servidor y pueden divergir.
         *
         * ⚠️ Y la selección que se guarda es **la que el dominio acaba de resolver** —obligatorios
         * inyectados, dependientes huérfanos podados—, no la que se pidió.
         */
        async loadAddons({ api, productId, date, time }) {
            if (! productId || this.quantity < 1) {
                return false;
            }

            const response = await api.post(`/catalog/products/${productId}/addons`, {
                quantity: this.quantity,
                date,
                time,
                addons: this.quantities,
                choices: this.choices,
            });

            if (! response.ok) {
                return false;
            }

            this.addons = { groups: response.data.groups, singles: response.data.singles };
            this.line = response.data.line ?? null;
            this.resolved = response.data.selection ?? [];

            return true;
        },

        /** Vacía la línea en construcción entera. Se llama al elegir otro producto y al añadir. */
        clear() {
            this.quantity = 0;
            this.addons = { groups: [], singles: [] };
            this.choices = [];
            this.quantities = [];
            this.eventData = {};
            this.dependentIds = [];
            this.line = null;
            this.resolved = [];
        },
    },
});
