import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { formState, resetForm, runForm } from '../account/form-run.js';
import { useProfileStore } from './profile.js';
import { saveExport } from '../account/privacy.js';

/**
 * **El estado de los dos derechos RGPD** (`specs/area-cliente.md` §9, tanda 2 · paso 8): descargarse
 * los datos (art. 20) y borrar la cuenta (art. 17).
 *
 * Espeja `Identity\Services\AccountPrivacy` en el servidor, y por el mismo motivo: son las dos
 * gestiones de la pantalla de privacidad y comparten pantalla, aunque no se parezcan en nada más
 * —una lee y la otra destruye—.
 *
 * ⚠️ **`busy` es UNO solo para las dos, y es deliberado.** Mientras el export está en vuelo el botón
 * de borrar tiene que estar bloqueado igual: el documento que se está componiendo es de una cuenta
 * que dejaría de existir a mitad, y no hay manera de explicarle eso al titular después.
 *
 * ⚠️ **RGPD/seguridad**: ni la contraseña ni el documento se guardan aquí. La contraseña vive en el
 * formulario mientras se escribe; el documento se entrega como fichero y **no se retiene** —guardarlo
 * en un store dejaría la PII más densa del producto colgando de cualquier cosa que inspeccione el
 * estado, que es la misma regla por la que la cesta no persiste las respuestas del evento—.
 */
export const usePrivacyStore = defineStore('privacy', {
    state: () => ({
        ...formState(),

        /** El fichero que se acaba de entregar. Lo enseña la pantalla para confirmar la descarga. */
        savedAs: '',

        /** La respuesta de `GET /me/consents`, cruda. `null` mientras no se haya pedido. */
        consents: null,

        /**
         * ¿Está la lista de consentimientos en vuelo?
         *
         * ⚠️⚠️ **Bandera PROPIA y no `busy`, por dos razones y la segunda es la que cierra la
         * elección** (2026-08-23). La primera es semántica: `busy` es el estado de los FORMULARIOS de
         * esta pantalla —exportar y borrar—, y bloquear los dos botones mientras se lee una lista de
         * cortesía sería un efecto que nadie pidió. La segunda es mecánica: `reset()` llama a
         * `resetForm()`, que pone `busy` a `false`; entrar en la zona con una carga anterior en vuelo
         * **borraría la bandera a mitad**. Un campo propio no lo sufre.
         *
         * ▶ Es el MISMO patrón que `stores/orders.js` y `stores/reservations.js` para lo mismo. El
         * outlier era esta pantalla: hasta hoy `ensureConsents()` no levantaba ninguna bandera, así
         * que las tres ramas del `v-if/v-else-if` eran falsas a la vez y **no se pintaba nada**
         * durante toda la petición — ni el spinner que `DECISIONES #124` puso, ni un marcador.
         * Medido en navegador el 2026-08-23: 2,6 s de tarjeta con solo su título.
         */
        consentsLoading: false,
    }),

    getters: {
        /** ¿Ya se pidió la lista de consentimientos? Distingue «no hay» de «aún no se sabe». */
        consentsLoaded: (state) => state.consents !== null,

        /** El interruptor de marketing: de dónde sale es el perfil (`marketing_opt_in` de `GET /me`). */
        marketing: () => Boolean(useProfileStore().user?.marketing_opt_in),

        /** El interruptor del análisis identificado: encendido = NO se opuso (`analytics_opt_out` de `GET /me`). */
        analytics: () => ! useProfileStore().user?.analytics_opt_out,
        /** El interruptor de la encuesta del día siguiente: encendido = se manda (`surveys_opt_out` de `GET /me`). */
        surveys: () => ! useProfileStore().user?.surveys_opt_out,
    },

    actions: {
        /**
         * **Recibir la encuesta del día siguiente, o no** (`specs/encuestas.md` §4.3, T3). Es un correo de
         * servicio, así que aquí no hay consentimiento que releer: solo la preferencia, que `GET /me` refleja.
         * La baja de un toque del propio correo escribe lo mismo.
         */
        async setSurveys(wants, { api = httpClient } = {}) {
            const response = await api.put('/me/surveys', { accepted: wants });

            if (! response.ok) return false;

            const profile = useProfileStore();
            if (profile.user) profile.user.surveys_opt_out = ! wants;

            return true;
        },
        /**
         * Deja el estado como si nunca se hubiera intentado nada. Se llama al ENTRAR en la zona.
         *
         * ⚠️ **No borra los consentimientos**, y es deliberado: son un DATO leído, no el resultado de
         * un intento. Vaciarlos aquí obligaría a volver a pedirlos cada vez que el titular entra en
         * la pantalla, que es justo lo que `ensureConsents()` existe para evitar.
         */
        reset() {
            resetForm(this);
            this.savedAs = '';
        },

        /**
         * Pide los consentimientos **solo si no los tiene**.
         *
         * ⚠️ **Un fallo NO se anuncia con el aviso del formulario.** Esta lista es contexto —a qué
         * dijo que sí y cuándo— y no la acción de la pantalla; pintar «revisa los datos» encima del
         * formulario de borrado porque no se pudo leer una lista sería decirle al titular que su
         * problema es otro. Se queda sin lista, como hace el índice con la próxima reserva.
         */
        async ensureConsents({ api = httpClient } = {}) {
            if (this.consentsLoaded || this.consentsLoading) return;

            this.consentsLoading = true;

            try {
                const response = await api.get('/me/consents');

                if (response.ok) this.consents = response.data;
            } finally {
                this.consentsLoading = false;
            }
        },

        /**
         * **Da o RETIRA el consentimiento de marketing** (art. 7.3, `#344`).
         *
         * ⚠️⚠️ **No pide contraseña, y eso es la ley, no una comodidad**: el art. 7.3 exige que
         * retirar sea *tan fácil como dar*. Poner fricción solo a la retirada sería incumplirlo por
         * otra puerta — y es justo la fricción que a alguien le parecería «más seguro» añadir.
         *
         * ⚠️ **Al salir bien se RELEEN los consentimientos**: la lista de arriba es la prueba visible
         * del art. 7.1, y dejarla con la foto vieja diría «aceptado» sobre un interruptor que el
         * titular acaba de apagar. Es la contradicción que esta tanda existe para cerrar.
         *
         * @returns {Promise<boolean>} si el servidor lo aplicó
         */
        /** Trae el perfil si no está: es de donde sale el estado del interruptor. */
        ensureMarketing({ api = httpClient } = {}) {
            return useProfileStore().ensure({ api });
        },

        async setMarketing(accepted, { api = httpClient } = {}) {
            const response = await api.put('/me/marketing', { accepted });

            if (! response.ok) return false;

            // El perfil ya cargado refleja el cambio sin volver a pedir `/me`: es la MISMA fuente
            // (`marketing_opt_in` de `GET /me`), y pedirla otra vez solo añadiría una espera.
            const profile = useProfileStore();
            if (profile.user) profile.user.marketing_opt_in = accepted;

            this.consents = null;
            await this.ensureConsents({ api });

            return true;
        },

        /**
         * **Vincular la navegación a la cuenta, o OPONERSE** (art. 21, `specs/analitica.md` §4.3, T3a·3).
         *
         * El interruptor pinta `! analytics_opt_out`: encendido = «vincula». El consentimiento real es la
         * categoría «análisis» del aviso de cookies; esto es la puerta de la cuenta para retirarlo —tan fácil
         * como darlo, sin contraseña— y el servidor, al apagarlo, desvincula y pide el olvido al driver.
         * Al salir bien se releen los consentimientos: la fila `analytics` queda sellada, y la lista lo dice.
         *
         * @returns {Promise<boolean>} si el servidor lo aplicó
         */
        async setAnalytics(linked, { api = httpClient } = {}) {
            const response = await api.put('/me/analytics', { accepted: linked });

            if (! response.ok) return false;

            const profile = useProfileStore();
            if (profile.user) profile.user.analytics_opt_out = ! linked;

            this.consents = null;
            await this.ensureConsents({ api });

            return true;
        },

        /**
         * Descarga el documento de portabilidad.
         *
         * ⚠️ **No pide contraseña**, y es lo que hace hoy la web: descargarse los datos propios no
         * destruye ni cede nada, y ponerle fricción a un derecho que la ley quiere fácil de ejercer
         * solo consigue que no se ejerza.
         */
        async exportData({ api = httpClient, messages = {}, auth = {}, dom = {} } = {}) {
            this.savedAs = '';

            return runForm(this, () => api.get('/me/export'), { messages, auth }, (response) => {
                this.savedAs = saveExport(response.data, dom);
            });
        },

        /**
         * Borra la cuenta. Devuelve si salió.
         *
         * ⚠️ **Al volver `true` la sesión YA NO VALE**: el servidor revoca todas las credenciales del
         * titular, incluida la de esta petición. Quien llama tiene que sacar al cliente de la página
         * —todo lo que hay pintado alrededor habla de una cuenta que ya no existe—, y por eso esta
         * acción no intenta releer nada después.
         */
        async deleteAccount({ currentPassword }, { api = httpClient, messages = {}, auth = {} } = {}) {
            return runForm(
                this,
                () => api.delete('/me', { current_password: currentPassword }),
                { messages, auth },
            );
        },
    },
});
