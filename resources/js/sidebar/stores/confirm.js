import { defineStore } from 'pinia';
import { api as httpClient } from '../api.js';
import { CODE_LENGTH, codeDigits } from '../code-input.js';
import { confirmCodeOutcome } from '../account/confirm-code.js';
import { fieldError } from '../account/form-outcome.js';
import { useProfileStore } from './profile.js';

/**
 * El código del correo NUEVO de un cambio de correo (A2b, `#856`): no se pide aquí —sale con el cambio, `PATCH /me`— y
 * se repite por su propio camino (`/me/pending-email/resend`), pero se escribe en la MISMA pieza que los de confirmar.
 */
export const NEW_EMAIL = 'new_email';

const blank = () => ({
    /** La acción cuyo código está a la vista (`account/confirm-code.js::CONFIRM_ACTIONS` o {@link NEW_EMAIL}). */
    action: '',
    /** ¿Se ve el campo del código? Antes de pedirlo, la pieza solo dice a dónde irá. */
    shown: false,
    /** Cuántos se han pedido OTRA vez: con uno o más, la pista dice «otro». */
    resends: 0,
    code: '',
    /** El «no» bajo el código: la espera para pedir otro, o el del servidor al usarlo. */
    error: '',
    /** El de arriba: la red, un fallo sin campo. */
    notice: '',
    busy: false,
    expired: false,
});

/**
 * **El código que CONFIRMA lo sensible de Mi cuenta** (A4b de `docs/specs/acceso-con-codigo.md` §4.11, `#813`): cerrar
 * las otras sesiones, desvincular Google, cambiar el correo y borrar la cuenta. La pieza que lo pinta es
 * `account/ConfirmCode.vue`; lo que enseña cada respuesta, `account/confirm-code.js`.
 *
 * ⚠️⚠️ **UNO para toda el área, y no uno por zona**: solo se ve una zona a la vez, y al cambiar de zona se vacía
 * (`stores/account.js::sync`). Y dentro de una zona solo vive el código de UNA acción: pedir el de otra anula el anterior también en el servidor (uno vivo por
 * correo y propósito), así que enseñar dos campos sería enseñar uno que ya no vale.
 *
 * ⚠️ **El primer toque PIDE el código y el segundo lo USA** (como la isla, `#857`): pedirlo al entrar mandaría un correo a
 * quien solo mira. La sexta cifra hace lo mismo que el segundo toque (el `CodeInput`, `#861`).
 */
export const useConfirmStore = defineStore('confirm', {
    state: blank,

    getters: {
        /** ¿Están las seis? Hasta entonces el botón de la acción va apagado. */
        ready: (state) => codeDigits(state.code).length === CODE_LENGTH,

        /** ¿Está a la vista el código de ESTA acción? */
        isShown: (state) => (action) => state.shown && state.action === action,
    },

    actions: {
        /** Como si nunca se hubiera pedido nada. Lo llama cada zona al entrar. */
        reset() {
            Object.assign(this, blank());
        },

        /** El cambio de correo quedó pendiente: su código ya salió hacia el buzón NUEVO, así que su campo se ve. */
        showNewEmail() {
            if (! this.isShown(NEW_EMAIL)) Object.assign(this, blank(), { action: NEW_EMAIL, shown: true });
        },

        /** Pide el código de confirmar `action` al correo de la cuenta (`POST /me/confirm-code`). Devuelve si salió. */
        async request(action, ctx = {}) {
            return this.send(action, (api) => api.post('/me/confirm-code', { action }), ctx);
        },

        /**
         * «Pedir otro código»: otro de la MISMA acción, y el nuevo anula el anterior. El del correo nuevo va por su camino
         * y resella la caducidad del cambio, así que el perfil se relee para que el aviso diga los minutos de verdad.
         */
        async again(ctx = {}) {
            if (this.action !== NEW_EMAIL) return this.request(this.action, ctx);

            const sent = await this.send(NEW_EMAIL, (api) => api.post('/me/pending-email/resend', {}), ctx);

            if (sent) await useProfileStore().refresh(ctx);

            return sent;
        },

        /**
         * Pide un código y coloca lo que diga el servidor. Con el campo ya a la vista para esta acción es «otro»: lo escrito
         * se borra solo si salió de verdad (si es pronto, ese código sigue valiendo y la espera sale bajo él, `#812`).
         */
        async send(action, call, { api = httpClient, ...texts } = {}) {
            if (this.busy) return false;

            const again = this.isShown(action);

            Object.assign(this, { busy: true, error: '', notice: '', expired: false });

            try {
                const r = confirmCodeOutcome(await call(api), texts);

                if (r.shown && ! again) Object.assign(this, { action, shown: true, resends: 0, code: '' });
                if (r.sent && again) Object.assign(this, { resends: this.resends + 1, code: '' });
                Object.assign(this, { error: r.error, notice: r.notice, expired: r.expired });

                return r.sent;
            } finally {
                this.busy = false;
            }
        },

        /**
         * **Un gesto que PREGUNTA antes de actuar** (borrar la cuenta, con `ConfirmInline`): sin su código a la vista, lo
         * pide; con las seis cifras, `next()` —la pregunta—. La acción, después, con {@link act}.
         */
        async ask(action, next, ctx = {}) {
            if (! this.isShown(action)) return this.request(action, ctx);
            if (this.ready) next();

            return false;
        },

        /**
         * **El botón de la acción**: sin su código a la vista, lo pide; con las seis cifras, la hace con él (`perform(code)`,
         * que devuelve si salió). Si el servidor dice que el código no vale, su «no» va bajo el campo y el campo se vacía
         * (`owner` es el store del formulario, donde `runForm` deja los errores por campo). Devuelve si la acción se hizo.
         */
        async act(action, owner, perform, ctx = {}) {
            if (! this.isShown(action)) {
                // El del correo nuevo no se pide aquí: sale con el cambio (`showNewEmail`).
                if (action !== NEW_EMAIL) await this.request(action, ctx);

                return false;
            }

            if (! this.ready || this.busy) return false;

            this.error = '';

            const done = await perform(codeDigits(this.code));

            // ⚠️ Solo si sigue siendo la suya: confirmar el cambio de correo deja pendiente el NUEVO, y la zona ya ha
            // enseñado su código (`showNewEmail`) antes de que esto vuelva: vaciarlo aquí lo escondería.
            if (done) {
                if (this.action === action) this.reset();
            } else {
                const error = fieldError(owner?.fields, 'code');

                if (error) Object.assign(this, { error, code: '' });
            }

            return done;
        },
    },
});
