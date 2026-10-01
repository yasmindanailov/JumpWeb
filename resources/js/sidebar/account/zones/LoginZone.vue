<script setup>
import { useAuthStore, STAGE_REGISTER } from '../../stores/auth.js';
import { landOnAccount } from '../after-auth.js';
import { api } from '../../api.js';
import EntryForm from '../../steps/EntryForm.vue';
import RegisterZone from './RegisterZone.vue';

/**
 * **LA PUERTA de Mi cuenta** (A4a de `docs/specs/acceso-con-codigo.md` §4.11; antes, `specs/auth-en-cajon.md` §4.1): la
 * zona en la que aterriza un invitado que entra al área de cliente, y la de `/login` y `/registro`
 * (`App\Http\Sidebar\AccountDoor`). Sus tres caras, sin pestañas (`#849`): el correo y el código (`EntryForm`, el mismo
 * del paso 5 de la compra) y el alta (`RegisterZone`, contexto `standalone`), con su «revisa tu correo» si el alta
 * acabó sin sesión (el señuelo).
 *
 * ⚠️⚠️ **Al entrar bien se NAVEGA, y eso lo obliga una medida**: los textos del área viajan solo con sesión, así que
 * quedarse aquí dejaría el índice con los rótulos en blanco. El porqué completo, en `account/after-auth.js`, que además hace
 * que la pila de retorno no conserve esta pantalla y que el código no sobreviva en un dispositivo compartido.
 */
const props = defineProps({
    /** El grupo `account`, podado: de aquí salen los rótulos de la puerta y del alta. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: el aviso genérico de «inténtalo más tarde». */
    messages: { type: Object, default: () => ({}) },
    /** El grupo `auth`: los literales del «no» del servidor. */
    auth: { type: Object, default: () => ({}) },
    /** Las rutas que compone el servidor. De aquí salen la puerta a la que se aterriza y la ida a Google. */
    urls: { type: Object, default: () => ({}) },
});

const store = useAuthStore();

// Los avisos son de un intento que ya no se ve, y el correo al que fue un código es PII: la puerta empieza en su primera
// cara. El correo ESCRITO se queda: quien vuelve no tiene que teclearlo otra vez.
store.clearNotices();

const texts = () => ({ api, messages: props.messages, auth: props.auth, account: props.account });

async function enter() {
    const result = await store.loginWithCode(texts());

    if (result.ok) landOnAccount({ urls: props.urls });
}
</script>

<template>
    <RegisterZone
        v-if="store.stage === STAGE_REGISTER || store.pendingEmail"
        :account="account"
        :messages="messages"
        :auth="auth"
        :urls="urls" />

    <EntryForm
        v-else
        v-model:email="store.form.email"
        v-model:code="store.form.code"
        v-model:remember="store.form.remember"
        :stage="store.stage"
        :errors="store.loginError"
        :submitting="store.busy"
        :account="account"
        :google-url="urls.google ?? ''"
        :sent-to="store.codeSentTo"
        :resent="store.codeResent"
        @continue="store.requestCode(texts())"
        @enter="enter"
        @resend="store.resendCode(texts())"
        @change-email="store.changeEmail()" />
</template>
