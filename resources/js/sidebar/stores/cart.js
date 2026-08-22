import { defineStore } from 'pinia';
import { clear as forgetStored, load as readStored, removeLine as removeCartLine, save as writeStored } from '../cart.js';

/**
 * El estado de la CESTA (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Aquí no se tarifica nada.** El importe lo compone `POST /orders/quote` (`PAY-12`) y el saneado,
 * la reconciliación y la propiedad de la cesta guardada los decide `cart.js`, un módulo plano con sus
 * casos y su paridad contra el servidor. Este store guarda las líneas, el presupuesto que devolvió el
 * servidor, los avisos, y sabe persistir.
 *
 * ⚠️⚠️ **Lo que se queda FUERA a propósito**: `refreshQuote()` y la navegación al catálogo cuando la
 * cesta se vacía son del embudo —piden a la API y mueven el paso—, así que viven en la raíz. El store
 * dice **qué pasó** (`remove()` devuelve si quedó vacía) y quien llama decide a dónde ir.
 */

/**
 * El almacén del navegador, o `null`.
 *
 * ⚠️ Va envuelto en `try` porque **acceder a `localStorage` LANZA** en Safari con cookies bloqueadas y
 * en modo privado de algunos navegadores. Sin esto, una cesta vacía sería una excepción no capturada.
 */
function storage() {
    try {
        return window.localStorage ?? null;
    } catch {
        return null;
    }
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        /** Las líneas de la cesta, tal y como se persisten. */
        lines: [],

        /** De QUIÉN es esta cesta: el id del titular, o `null` si es de invitado. */
        owner: null,

        /** El tope de líneas. Lo publica el servidor en `GET /config`; aquí solo se guarda. */
        maxLines: 50,

        /** El presupuesto de la cesta. Lo tarifica `POST /orders/quote`; aquí no se suma nada. */
        quote: null,

        /** El aviso de la cesta, ya traducido. Ocupa el sitio del `@error('cart')` del Blade. */
        error: '',

        /** Errores por campo del evento, con la misma forma que el error bag de la web. */
        fieldErrors: {},
    }),

    getters: {
        /** Cuántas líneas tarificó el SERVIDOR. No se cuenta `lines`: la verdad es la del presupuesto. */
        count: (state) => state.quote?.lines?.length ?? 0,

        isEmpty: (state) => state.lines.length === 0,
    },

    actions: {
        setOwner(owner) {
            this.owner = owner ?? null;
        },

        setMaxLines(max) {
            if (typeof max === 'number') this.maxLines = max;
        },

        setLines(lines) {
            this.lines = Array.isArray(lines) ? lines : [];
        },

        setQuote(quote) {
            this.quote = quote ?? null;
        },

        setError(message) {
            this.error = message ?? '';
        },

        setFieldErrors(errors) {
            this.fieldErrors = errors ?? {};
        },

        /**
         * Lee la cesta guardada, con su dueño y su caducidad resueltos por `cart.js`.
         *
         * ⚠️ **El día de hoy se INYECTA, no se lee del reloj aquí.** Este proyecto ya pagó dos veces
         * el precio de una foto que incluye el tiempo (`DECISIONES #64`, `#97`): un store que mira el
         * reloj no se puede probar sin congelarlo.
         */
        restore(today) {
            return readStored(storage(), { owner: this.owner, today, maxLines: this.maxLines });
        },

        /** Guarda la cesta. ⚠️ `cart.js::save()` descarta `event_data`: son datos de un menor (RGPD). */
        persist() {
            writeStored(storage(), { owner: this.owner, lines: this.lines });
        },

        forget() {
            forgetStored(storage());
        },

        /**
         * Quita una línea y persiste. **Devuelve `true` si la cesta se quedó vacía**, que es lo que
         * quien llama necesita para volver al catálogo — el store no navega.
         */
        remove(index) {
            this.lines = removeCartLine(this.lines, index);
            this.persist();

            if (this.lines.length === 0) {
                this.quote = null;

                return true;
            }

            return false;
        },

        /**
         * Contesta un campo del evento de una línea.
         *
         * ⚠️⚠️ **Estas respuestas viven SOLO en memoria y NO se persisten nunca** — es la razón de que
         * haya que volver a pedirlas: son el nombre de un menor, su edad y sus alergias (`#38(d)`,
         * art. 9 del RGPD). Que aquí no se llame a `persist()` **no es un olvido**: `save()` las
         * descartaría igualmente, y el canario de `cart.test.js` busca centinelas en el volcado
         * entero del almacén.
         */
        updateField(index, key, value) {
            const line = this.lines[index];

            if (! line) return;

            line.event_data = { ...(line.event_data ?? {}), [key]: value };
            this.error = '';
        },
    },
});
