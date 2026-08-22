import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { credentialOutcome } from '../account/credentials.js';

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
        /** ¿Hay una petición en vuelo? */
        busy: false,

        /** Errores por campo, tal cual los mandó el servidor. */
        fields: {},

        /** Aviso general: red caída, límite alcanzado o un fallo que no señala campo. */
        notice: '',

        /** La última gestión salió bien. Lo lee la pantalla para confirmar y limpiarse. */
        done: false,

        /** La sesión se perdió por el camino: no se reintenta, se vuelve a entrar. */
        expired: false,
    }),

    actions: {
        /** Deja el estado como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona. */
        reset() {
            this.busy = false;
            this.fields = {};
            this.notice = '';
            this.done = false;
            this.expired = false;
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
         * El guardián común: limpia, llama, coloca el veredicto.
         *
         * ⚠️ **Limpia ANTES de llamar**, no después: si al reintentar quedara el error anterior en
         * pantalla mientras la petición está en vuelo, el cliente lo leería como el resultado del
         * intento nuevo.
         */
        async run(call, ctx) {
            this.busy = true;
            this.fields = {};
            this.notice = '';
            this.done = false;
            this.expired = false;

            try {
                const outcome = credentialOutcome(await call(), ctx);

                this.fields = outcome.fields;
                this.notice = outcome.notice;
                this.expired = outcome.expired;
                this.done = outcome.ok;

                return outcome.ok;
            } finally {
                this.busy = false;
            }
        },
    },
});
