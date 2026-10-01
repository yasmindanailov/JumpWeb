import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, runForm } from '../account/form-run.js';
import { fieldError } from '../account/form-outcome.js';
import { useAccountContextStore } from './accountContext.js';

/**
 * El cuerpo de `PATCH /me` a partir de lo que el formulario trae.
 *
 * ⚠️⚠️ **La fecha de nacimiento (TP·1, `#792`) viaja SOLO si el formulario la trae**, y vacía viaja como `null` (la borra).
 * Ausente, el servidor no la toca: Mi cuenta de la ISLA llama a este mismo store con `{name, phone, locale, email}` y,
 * si aquí se mandase siempre, cada guardado suyo la borraría.
 *
 * ⚠️ Vive AQUÍ y no en `account/profile.js`, medido: importarla de allí metía ese módulo entero en la descarga del motor
 * (+0,77 kB, `SidebarBundleBudgetTest`). Es una función pura y tiene sus casos en `profile.test.js`, sin Pinia.
 *
 * ⚠️ **El `code` que confirma el cambio de correo solo viaja si lo hay** (A4b, `#813`; antes, `current_password`): solo
 * hace falta al cambiar el correo, y mandarlo vacío cuando no toca sería un 422 por un campo que no tocaba.
 */
export function profileBody(form) {
    const body = { name: form?.name, phone: form?.phone, locale: form?.locale, email: form?.email };

    if (form && 'born_on' in form) body.born_on = String(form.born_on ?? '').trim() || null;

    if (form?.code) body.code = form.code;

    return body;
}

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
 * ⚠️ El código que confirma el cambio de correo no se guarda aquí: vive en `stores/confirm.js`, que lo vacía al salir.
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

        /** Relee el perfil aunque ya lo tenga: pedir otro código del correo nuevo resella la caducidad del cambio. */
        async refresh({ api = httpClient } = {}) {
            const response = await api.get('/me');

            if (response.ok) this.user = response.data;
        },

        /**
         * Guarda el perfil. Devuelve si salió. Qué viaja lo decide {@link profileBody}: el código solo si lo hay, y la
         * fecha de nacimiento solo si el formulario la trae.
         */
        async apply(form, { api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(() => api.patch('/me', profileBody(form)), { api, messages, auth });
        },

        /**
         * **Confirma el correo NUEVO con su código** (`POST /me/pending-email/confirm`, A2b): el servidor devuelve el perfil
         * ya con el correo cambiado. Devuelve si salió.
         *
         * ⚠️ Si otra cuenta tomó ese correo mientras tanto, el «no» llega sobre `email`, que en este formulario es el campo del
         * correo de AHORA: allí diría algo falso, así que va arriba (como la isla). Al salir bien se relee el contexto de
         * cuenta, que dice si el correo está confirmado.
         */
        async confirmPending(code, { api = httpClient, messages = {}, auth = {} } = {}) {
            const done = await this.run(() => api.post('/me/pending-email/confirm', { code }), { api, messages, auth });

            if (! done && this.fields?.email) {
                this.notice = fieldError(this.fields, 'email');
                this.fields = { ...this.fields, email: undefined };
            }

            if (done) useAccountContextStore().refresh({ api });

            return done;
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
