import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';

/**
 * **Las próximas reservas del cliente**, para el índice del área (`specs/area-cliente.md` §4.2).
 *
 * ⚠️ **El índice la necesita aunque el bloque `.acct` del panel ya la enseñe**, y el motivo está
 * MEDIDO en `VERIFICACION-E2E-CAJON.md` **V4**: dentro de la sección de cuenta ese bloque **se
 * colapsa** —lo hace el modo `account`, igual que en la compra— y su altura es 0. Es decir: en el
 * área de cliente esa información **no se ve**, así que aquí no hay duplicación, hay continuidad.
 *
 * ⚠️ Lista corta por naturaleza: el endpoint **no pagina** (lo dice su contrato). Nada que persistir
 * y nada que acumular.
 */
export const useReservationsStore = defineStore('reservations', {
    state: () => ({
        /** La respuesta de `GET /me/reservations`, cruda. `null` mientras no se haya pedido. */
        payload: null,
        loading: false,
    }),

    getters: {
        loaded: (state) => state.payload !== null,

        /** La más próxima, ya con su etiqueta compuesta por el servidor, o `null`. */
        next: (state) => state.payload?.data?.[0] ?? null,

        /** Cuántas quedan por delante. Sale de `meta.total`, no de contar la lista. */
        upcoming: (state) => Number(state.payload?.meta?.total ?? 0),
    },

    actions: {
        /**
         * Pide las próximas **solo si no las tiene**.
         *
         * ⚠️ Un fallo aquí **no se anuncia**, y es deliberado: esto es contexto de cortesía en un
         * índice, no el contenido de la pantalla. El servidor hace lo mismo —`CustomerAccountContext`
         * envuelve su carga en un `try` y sigue— porque romper el índice por no poder saludar con la
         * próxima reserva sería un mal negocio.
         */
        async ensure({ api = httpClient } = {}) {
            if (this.loaded || this.loading) return;

            this.loading = true;

            try {
                const response = await api.get('/me/reservations');

                if (response.ok) this.payload = response.data;
            } finally {
                this.loading = false;
            }
        },

        /**
         * **Olvida lo que sabía**, para que el siguiente `ensure()` vuelva a preguntar.
         *
         * ⚠️ **Existe por el cambio de titular** (2026-08-23, `specs/account-context-vue.md` §4.6).
         * `ensure()` pide «solo si no las tiene», que es lo correcto mientras el titular no cambia; y
         * quien entra en el paso 5 del embudo **no recarga la página**, así que sin esto abriría «Mi
         * cuenta» y encontraría el índice del invitado —vacío— **sin que nada fallara**. Lo llama
         * `account/session-gained.js`, que es el único sitio donde se sabe que hubo un cambio.
         */
        invalidate() {
            this.payload = null;
        },
    },
});
