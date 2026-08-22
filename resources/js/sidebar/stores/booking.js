import { defineStore } from 'pinia';

/**
 * El estado de las RESERVAS: si están pausadas y con qué aviso (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Se PIDE y no se inyecta en el montaje, y está decidido**: `/config` es estático por despliegue,
 * pero la pausa la acciona la dueña **con clientes navegando**. Una landing abierta durante horas con
 * un snapshot mentiría desde el segundo en que se toca el interruptor.
 *
 * ⚠️ El aviso NO tapa los pasos de RESULTADO: quien vuelve de la pasarela tiene que ver en qué quedó
 * su pago aunque las reservas se hayan pausado entre medias. Esa regla vive en `paused.js`; aquí solo
 * está el dato que la alimenta.
 */
export const useBookingStore = defineStore('booking', {
    state: () => ({
        /** Lo último que dijo `GET /booking/status`, o `null` si aún no se ha preguntado. */
        status: null,
    }),

    getters: {
        isPaused: (state) => state.status?.reservations_paused === true,
    },

    actions: {
        /**
         * Relee el estado.
         *
         * ⚠️ **Devuelve si se PUDO releer**, y quien pasa al pago lo necesita: cuando el veredicto dice
         * «pausa» y el estado que pintaría el cartel no llega, hay que saberlo para no dejar un clic
         * mudo.
         */
        async refresh({ api }) {
            const response = await api.get('/booking/status');

            if (response.ok) this.status = response.data;

            return response.ok;
        },
    },
});
