import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { runRetry } from '../outcome.js';
import { STEPS } from '../machine.js';
import { usePurchaseStore } from './purchase.js';
import { useOutcomeStore } from './outcome.js';
import { useSectionStore } from './section.js';

/**
 * **El estado de «Mis reservas»**: qué página se ha pedido, qué respondió y qué falló
 * (`docs/specs/area-cliente.md` §4.3).
 *
 * ⚠️⚠️ **Guarda la respuesta CRUDA, no las filas ya compuestas**, y es deliberado: componerlas exige
 * los diccionarios del montaje, que viven en las props del componente. Con el payload crudo aquí y
 * `account/orders.js` ejecutándose donde están los textos, el store se prueba sin diccionarios y el
 * módulo se prueba sin store — que es lo que hace que las dos mitades tengan red propia.
 *
 * ⚠️ **Y aquí vive la regla que sustituyó a `<KeepAlive>`** (`DECISIONES #120(g)`): `ensure()` pide
 * **solo si no hay datos**. Eso es lo que hace que volver a la zona no repita la petición, sin pagar
 * los 2,3 KiB de una caché del framework y sin romper las template refs.
 *
 * ⚠️ **RGPD**: nada de esto se persiste. La cesta ya excluye a propósito las respuestas del evento
 * —nombres y alergias de menores, art. 9— y un historial de pedidos en `localStorage` sería la misma
 * fuga por otra puerta (`specs/area-cliente.md` §4.4). Vive en memoria y se repide al abrir.
 */
export const useOrdersStore = defineStore('orders', {
    state: () => ({
        /** La respuesta de `GET /me/orders`, cruda. `null` mientras no se haya pedido nunca. */
        payload: null,

        /** ¿Hay una petición en vuelo? */
        loading: false,

        /** Lo que salió mal, ya en el idioma del cliente. Cadena vacía si no hay nada que decir. */
        error: '',

        /**
         * ⚠️ **La sesión caducó mientras el cajón estaba abierto** (§4.6). Se distingue del error
         * genérico porque la salida es otra: no se reintenta, se entra. Sin este caso, un 401 dejaría
         * la zona en blanco sin decir por qué.
         */
        unauthenticated: false,
    }),

    getters: {
        /** ¿Ya se pidió alguna vez? Lo mira `ensure()` para no repetir la petición al volver. */
        loaded: (state) => state.payload !== null,
    },

    actions: {
        /** Pide la primera página **solo si hace falta**. Es lo que se llama al entrar en la zona. */
        async ensure(deps) {
            if (this.loaded || this.loading) return;

            await this.load(1, deps);
        },

        /**
         * Pide una página del historial.
         *
         * ⚠️ **La página anterior NO se borra al empezar**: si la petición falla, el cliente se queda
         * con lo que ya tenía y un aviso, en vez de con una pantalla vacía que parece decir «no tienes
         * reservas» — que es justo lo contrario de lo que ha pasado.
         */
        async load(page = 1, { api = httpClient } = {}) {
            this.loading = true;
            this.error = '';
            this.unauthenticated = false;

            try {
                const response = await api.get(`/me/orders?page=${encodeURIComponent(page)}`);

                if (response.ok) {
                    this.payload = response.data;

                    return;
                }

                if (response.status === 401) {
                    this.unauthenticated = true;

                    return;
                }

                this.error = response.error?.message ?? '';
            } finally {
                this.loading = false;
            }
        },

        /** Vuelve a pedir la página que se está viendo (tras un reintento, o tras un fallo). */
        async reload(deps) {
            await this.load(Number(this.payload?.meta?.current_page ?? 1), deps);
        },

        /**
         * **Reintentar el pago de un pedido desde su ficha.**
         *
         * ⚠️⚠️ **No reimplementa la secuencia: llama a `runRetry`, el MISMO módulo que usa el paso 10
         * del embudo.** Reabrir un cobro tiene reglas de dominio —extiende el hold, no suelta el
         * pedido si la pasarela falla, y devuelve 401 si la sesión se perdió por el camino— y
         * transcribirlas otra vez aquí sería la segunda superficie que las interpreta.
         *
         * ⚠️ Y sale por la pantalla de redirección **del embudo**, que ya existe y está probada. Se
         * usa `enter()` y no `go()` a propósito: `go()` exige que la transición esté declarada en
         * `FUNNEL_TRANSITIONS` y aquí se llega **desde fuera del embudo**, que es exactamente para lo
         * que `enter()` existe.
         *
         * ⚠️ **`api` es inyectable pero tiene el cliente REAL por defecto**, y las dos mitades tienen
         * motivo: inyectable para que `node --test` pueda doblarlo —sin eso este store no tendría
         * red—, y con defecto para que **el componente no necesite conocer la API**. Un componente
         * que importa `api` para pasárselo a un store ya está orquestando peticiones, que es lo que
         * `SidebarComponentBudgetTest` existe para impedir.
         *
         * @param {string} code  el código del pedido
         * @param {{api: object, messages: object}} deps
         */
        async retry(code, { api = httpClient, messages = {} } = {}) {
            this.loading = true;
            this.error = '';

            try {
                const result = await runRetry({ orderCode: code, api, messages });

                if (result.ok) {
                    const outcome = useOutcomeStore();
                    const purchase = usePurchaseStore();

                    outcome.declinedReason = '';
                    outcome.gateway = result.form;
                    useSectionStore().showPurchase();
                    purchase.enter(STEPS.REDIRECTING);

                    return true;
                }

                // ⚠️ Aquí el aviso SÍ se ve, al revés que en el paso 10 del embudo —cuyo bloque no
                // pinta errores y está anotado como deuda de PRODUCTO—. No es una divergencia
                // heredada: es que esta pantalla sí tiene dónde decirlo.
                this.error = result.error;

                if (result.goTo === 'identify') this.unauthenticated = true;

                return false;
            } finally {
                this.loading = false;
            }
        },
    },
});
