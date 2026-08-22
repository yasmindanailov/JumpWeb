import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, runForm } from '../account/form-run.js';

/**
 * **El perfil del titular y el ciclo del cambio de correo** (`specs/area-cliente.md` §9, paso 7b).
 *
 * Guarda el perfil tal como lo publica `GET /me` —crudo, sin componer— y llama a los tres endpoints.
 * La traducción de la respuesta vive en `account/form-outcome.js` y la composición en
 * `account/profile.js`; los dos con `node --test`.
 *
 * ⚠️ **La respuesta de `PATCH /me` REEMPLAZA el perfil guardado**, y ahí está media gracia: trae ya
 * `pending_email` y su caducidad, así que pedir un cambio de correo **no cuesta una segunda
 * petición** y la pantalla se entera sola de que hay algo pendiente.
 *
 * ⚠️ **RGPD/seguridad**: la contraseña de reconfirmación no se guarda aquí. Vive en el formulario del
 * componente mientras se escribe y se limpia al salir bien.
 */
export const useProfileStore = defineStore('profile', {
    state: () => ({
        /** El perfil, tal cual lo publica `GET /me`. `null` mientras no se haya pedido. */
        user: null,

        ...formState(),
    }),

    getters: {
        loaded: (state) => state.user !== null,
    },

    actions: {
        /** Pide el perfil **solo si no lo tiene**. Se llama al entrar en la zona. */
        async ensure({ api = httpClient } = {}) {
            if (this.loaded || this.busy) return;

            this.busy = true;

            try {
                const response = await api.get('/me');

                if (response.ok) this.user = response.data;
                else if (response.status === 401) this.expired = true;
            } finally {
                this.busy = false;
            }
        },

        /**
         * Guarda el perfil. Devuelve si salió.
         *
         * ⚠️ **`current_password` solo se manda si de verdad hay algo escrito.** El contrato la
         * declara opcional porque solo hace falta al cambiar el correo; mandarla vacía cuando no toca
         * la convertiría en un 422 por un campo que el cliente no tenía por qué rellenar.
         */
        async apply(form, { api = httpClient, messages = {}, auth = {} } = {}) {
            const body = {
                name: form.name,
                phone: form.phone,
                locale: form.locale,
                email: form.email,
            };

            if (form.currentPassword) body.current_password = form.currentPassword;

            return this.run(() => api.patch('/me', body), { api, messages, auth });
        },

        /** Cancela el cambio de correo pedido. */
        async cancelPending({ api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(() => api.delete('/me/pending-email'), { api, messages, auth }, { reload: true });
        },

        /** Reenvía la confirmación al buzón nuevo. */
        async resendPending({ api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(() => api.post('/me/pending-email/resend', {}), { api, messages, auth }, { reload: true });
        },

        /**
         * El guardián común: limpia, llama, coloca el veredicto.
         *
         * ⚠️ **`PATCH` devuelve el perfil y los otros dos un `204`.** Los que no lo devuelven piden
         * `reload`, porque su efecto SÍ cambia lo que la pantalla enseña —cancelar quita el bloque
         * pendiente, reenviar resella su caducidad— y quedarse con el perfil viejo lo pintaría mal.
         */
        async run(call, ctx, { reload = false } = {}) {
            return runForm(this, call, ctx, async (response) => {
                if (! reload && response.data) {
                    this.user = response.data;

                    return;
                }

                if (! reload) return;

                // ⚠️ **Con el MISMO cliente que hizo la llamada**, no con el global: pasarle solo
                // `{messages, auth}` dejaba la relectura usando el real, y en `node --test` eso es
                // una petición de verdad que nadie puede doblar. Lo cazó el test, no la revisión.
                const fresh = await (ctx.api ?? httpClient).get('/me');

                if (fresh.ok) this.user = fresh.data;
            });
        },
    },
});
