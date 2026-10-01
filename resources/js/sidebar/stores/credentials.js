import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';

/**
 * **El estado de las gestiones del acceso de «Sesiones»** (`specs/area-cliente.md` §9, tanda 2 · paso 6b): cerrar la
 * sesión en los demás dispositivos y las cuentas vinculadas.
 *
 * Guarda lo que la pantalla enseña —qué está en vuelo, qué campo falló, qué aviso general hay— y
 * llama a la API. La traducción de la respuesta vive en `account/form-outcome.js`, con `node --test`.
 *
 * ⚠️ **Se confirman con un CÓDIGO al correo, no con la contraseña** (A4b de `acceso-con-codigo.md` §4.11, `#813`): el
 * código lo pide y lo guarda `stores/confirm.js`, y aquí solo viaja. «Cambiar la contraseña» se fue con la zona: en el
 * cajón ya nadie entra con ella (`#848`), como en la isla desde su A3b.
 */
export const useCredentialsStore = defineStore('credentials', {
    state: () => ({
        ...formState(),

        /**
         * **Las cuentas externas vinculadas** (`specs/auth-con-google.md` §8). `null` mientras no se
         * hayan pedido; `[]` es una respuesta —esta cuenta no tiene ninguna— y no una falta.
         */
        identities: null,
    }),

    actions: {
        /** Deja el estado como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            resetForm(this);
        },

        /** Cierra la sesión en los demás dispositivos, con el código que lo confirma. La actual sobrevive. */
        async revokeOtherSessions({ code }, { api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(
                () => api.post('/me/sessions/revoke-others', { code }),
                { messages, auth },
            );
        },

        /**
         * Pide las identidades **solo si no las tiene**. Un fallo NO se anuncia con el aviso del
         * formulario: es contexto, no la acción de la pantalla — el mismo criterio que la lista de
         * consentimientos de privacidad.
         */
        async ensureIdentities({ api = httpClient } = {}) {
            if (this.identities !== null) return;

            const response = await api.get('/me/identities');

            if (response.ok) this.identities = response.data?.data ?? [];
        },

        /**
         * **Desvincula una cuenta externa.** Devuelve si salió.
         *
         * ⚠️ Al salir bien se RELEE la lista: dejarla con la foto vieja enseñaría un vínculo que el
         * titular acaba de quitar, que es justo lo que esta pantalla existe para poder hacer.
         */
        async unlinkIdentity(provider, { code }, { api = httpClient, messages = {}, auth = {} } = {}) {
            const ok = await this.run(
                () => api.delete('/me/identities/' + provider, { code }),
                { messages, auth },
            );

            if (ok) {
                this.identities = null;
                await this.ensureIdentities({ api });
            }

            return ok;
        },

        /**
         * El guardián común vive en `account/form-run.js` desde el paso 8: era el mismo cuerpo aquí,
         * en `profile.js` y en el borrado de cuenta, y la tercera copia es donde estas cosas empiezan
         * a divergir. Lo que allí se protege es que se limpie **antes** de llamar.
         */
        async run(call, ctx) {
            return runForm(this, call, ctx);
        },
    },
});
