import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';

/**
 * **El estado de las dos gestiones de credenciales** (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * Guarda lo que la pantalla enseña —qué está en vuelo, qué campo falló, qué aviso general hay— y
 * llama a la API. La traducción de la respuesta vive en `account/credentials.js`, con `node --test`.
 *
 * ⚠️ **Un solo store para las dos**, y no dos: comparten forma —reconfirmar la contraseña, un
 * veredicto, los mismos tres modos de fallo— y el estado nunca convive, porque son dos zonas
 * distintas y solo hay una visible. Dos stores habrían duplicado `busy`, `notice`, `fields` y su
 * limpieza, que es donde estas cosas divergen.
 *
 * ⚠️ **RGPD/seguridad**: aquí no se guarda ninguna contraseña. Los valores viven en el formulario del
 * componente mientras se escriben y se limpian al salir; persistir cualquiera de los dos —ni siquiera
 * en memoria compartida— sería regalar una credencial a cualquier cosa que inspeccione el store.
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

        /**
         * Cambia la contraseña. Devuelve si salió.
         *
         * ⚠️ **No se envía `password_confirmation`**: repetir la contraseña es cosa de este formulario
         * y el contrato no lo pide (`PasswordPolicy` tampoco lo incluye). Que las dos coincidan lo
         * comprueba la pantalla antes de llamar.
         */
        async changePassword({ currentPassword, password }, { api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(
                () => api.put('/me/password', { current_password: currentPassword, password }),
                { messages, auth },
            );
        },

        /** Cierra la sesión en los demás dispositivos. La actual sobrevive. */
        async revokeOtherSessions({ currentPassword }, { api = httpClient, messages = {}, auth = {} } = {}) {
            return this.run(
                () => api.post('/me/sessions/revoke-others', { current_password: currentPassword }),
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
        async unlinkIdentity(provider, { currentPassword }, { api = httpClient, messages = {}, auth = {} } = {}) {
            const ok = await this.run(
                () => api.delete('/me/identities/' + provider, { current_password: currentPassword }),
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
