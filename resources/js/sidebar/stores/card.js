import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';

/**
 * **El estado del carné QR en el cajón** (Fase 6 · A, `docs/specs/identidad-qr-puerta.md` §4.1, §4.5,
 * §9.6 B·2).
 *
 * Una lectura y una escritura, las dos del SERVIDOR:
 *  · `GET /me/card` — el carné activo (se emite si no lo hay), con `png_url` para dibujarlo.
 *  · `POST /me/card/rotate` — renovar: **el viejo muere en el acto** (§4.5, `[DECIDIDO owner]`) y la
 *    respuesta ES el nuevo, que sustituye al que había en memoria.
 *
 * La escritura va por `runForm`, como el resto del área: `busy`, `notice`, `done`, `expired`.
 *
 * ⚠️ **El token vive solo en memoria mientras la zona está abierta y NO se persiste**: es una
 * credencial de puerta (`RGPD-04`: el servidor la sirve con `no-store`, y el cliente no la guarda).
 */
export const useCardStore = defineStore('card', {
    state: () => ({
        ...formState(),

        /** La respuesta de `GET /me/card`, cruda: `{token, issued_at, png_url}`. `null` mientras no se haya pedido. */
        card: null,
        loading: false,
        /** La petición EN VUELO, para que quien llame mientras tanto espere a la misma. */
        inflight: null,
    }),

    getters: {
        loaded: (state) => state.card !== null,
    },

    actions: {
        /** Deja el formulario como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            resetForm(this);
        },

        /** Pide el carné **solo si no lo tiene**; dos llamadas seguidas esperan a la MISMA petición. */
        async ensure({ api = httpClient } = {}) {
            if (this.loaded) return;
            if (this.inflight) return this.inflight;

            this.loading = true;
            this.inflight = (async () => {
                try {
                    const response = await api.get('/me/card');

                    if (response.ok) this.card = response.data;
                    else if (response.status === 401) this.expired = true;
                } finally {
                    this.loading = false;
                    this.inflight = null;
                }
            })();

            return this.inflight;
        },

        /** Olvida el carné: el siguiente `ensure()` vuelve a preguntar (al cambiar de titular). */
        invalidate() {
            this.card = null;
        },

        /**
         * Renueva el carné. Devuelve si salió.
         *
         * La respuesta del servidor sustituye al carné en memoria: con ella cambia `issued_at`, y con
         * `issued_at` la URL de la imagen (`account/card.js`), así que el `<img>` pide la nueva.
         */
        async rotate({ api = httpClient, messages = {}, auth = {} } = {}) {
            return runForm(
                this,
                () => api.post('/me/card/rotate', {}),
                { messages, auth },
                (response) => { this.card = response.data; },
            );
        },
    },
});
