import { defineStore } from 'pinia';
import {
    cartRows, clear as forgetStored, decideOwnership, load as readStored, reconcile,
    removeLine as removeCartLine, save as writeStored, toApiItems,
} from '../cart.js';
import { useCatalogStore } from './catalog.js';

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

        /**
         * Las FILAS que pinta el carrito: el presupuesto del servidor emparejado con lo que hay en
         * memoria y con las etiquetas del esquema de evento.
         *
         * ⚠️ **Las etiquetas salen del store del CATÁLOGO, y por eso este getter lo usa**: el
         * presupuesto NO devuelve las respuestas del pack (RGPD, son datos de un menor) y sin las
         * etiquetas no hay con qué emparejarlas. Un store puede leer otro; lo que no puede es
         * duplicar el dato.
         */
        rows() {
            return cartRows(this.quote?.lines ?? [], this.lines, useCatalogStore().fieldsByProduct);
        },
    },

    actions: {
        setOwner(owner) {
            this.owner = owner ?? null;
        },

        /**
         * Resuelve QUÉ hacer con la cesta cuando cambia el titular, y lo hace.
         *
         * ⚠️ Las cinco casillas —incluida la que el servidor no tiene, porque allí el logout vacía la
         * sesión entera— las decide `cart.js::decideOwnership()`. Aquí solo se ejecuta la decisión.
         *
         * ⚠️ **Purgar es vaciar Y OLVIDAR**: dejar lo guardado devolvería la cesta del titular
         * anterior en la siguiente carga. Devuelve la decisión para que quien llama navegue si toca —
         * el store no mueve el paso.
         */
        applyIdentity(newOwner) {
            const decision = decideOwnership(this.owner, newOwner);

            if (decision === 'purge') {
                this.empty();
                this.forget();
            }

            this.setOwner(newOwner);

            return decision;
        },

        /**
         * Resuelve la identidad contra el SERVIDOR y la aplica.
         *
         * ⚠️ **La identidad sale SIEMPRE de `GET /me`, nunca de un parámetro**: el guard es quien la
         * dice, y colgarse de un id que viaje por el bus de eventos del navegador sería confiar en el
         * cliente para una defensa de seguridad.
         *
         * ⚠️ Un fallo que NO sea 401 devuelve `keep`: no se puede saber quién es, y purgar la cesta de
         * alguien por un corte de red sería destruir su compra por un problema nuestro.
         */
        async identify({ api }) {
            return this.applyIdentityResponse(await api.get('/me'));
        },

        /** Aplica una respuesta con forma de `GET /me` (el login devuelve el perfil con la misma). */
        applyIdentityResponse(response) {
            if (! response.ok && response.status !== 401) {
                return 'keep';
            }

            return this.applyIdentity(response.ok ? (response.data?.id ?? null) : null);
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
         * Pregunta al servidor si una línea candidata es comprable, con la cesta actual como contexto.
         *
         * ⚠️⚠️ **La candidata NO va dentro de `items`, y confundirlo devuelve un tope MENOR del real**:
         * `items` es lo que YA retiene cupo, así que meterla ahí la haría competir consigo misma.
         */
        async validateLine({ api, line }) {
            return api.post('/cart/validate-line', { line, items: toApiItems(this.lines) });
        },

        /**
         * Vacía la cesta EN MEMORIA, sin tocar lo guardado.
         *
         * ⚠️ **Vaciar y olvidar son dos cosas distintas y no se pueden confundir**: al cambiar el
         * titular hay que hacer las dos (`forget()` aparte), pero al crear la reserva hay que vaciar y
         * **persistir vacío** — si solo se vaciara en memoria, una recarga resucitaría la cesta y el
         * cliente acabaría comprando dos veces lo mismo.
         */
        empty() {
            this.lines = [];
            this.quote = null;
            this.error = '';
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
         * Vuelve a pedir el presupuesto de la cesta y reconcilia lo que el servidor haya podado.
         *
         * ⚠️⚠️ **Reconciliar y volver a presupuestar es UNA sola operación, y por eso se llama sola.**
         * Podar desplaza los índices, y las filas y el botón de quitar se emparejan por el `index` del
         * PRESUPUESTO: pintar el viejo sobre la cesta podada enseñaría las respuestas de otra línea y
         * dejaría el botón mudo.
         *
         * ⚠️ El velo de carga NO se enciende aquí: lo envuelve quien llama, que es quien sabe si esto
         * es algo que el cliente acaba de pedir o una consecuencia.
         */
        async refreshQuote({ api }) {
            if (this.lines.length === 0) {
                this.quote = null;

                return;
            }

            const response = await api.post('/orders/quote', { items: toApiItems(this.lines) });

            this.quote = response.ok ? response.data : null;

            if (! response.ok) {
                return;
            }

            const { lines, changed } = reconcile(this.lines, this.quote.lines ?? []);

            if (changed) {
                this.lines = lines;
                this.persist();
                this.quote = null;

                await this.refreshQuote({ api });
            }
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
