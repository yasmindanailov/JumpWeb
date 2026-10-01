<script setup>
import { computed } from 'vue';
import { useCredentialsStore } from '../../stores/credentials.js';
import { useConfirmStore } from '../../stores/confirm.js';
import { useProfileStore } from '../../stores/profile.js';
import { CONFIRM_ACTIONS } from '../confirm-code.js';
import { t as translate } from '../../i18n.js';
import ConfirmCode from '../ConfirmCode.vue';
import GoogleButton from '../../steps/GoogleButton.vue';

/**
 * **Cerrar sesión en los demás dispositivos** y las cuentas vinculadas (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * ⚠️ La credencial en curso **sobrevive**, y eso lo garantiza el servidor (`revokeOtherAccess()`,
 * `RGPD-06`): el titular no debe autoexpulsarse al defenderse.
 *
 * ⚠️ **Cada acción con SU código al correo** (A4b de `acceso-con-codigo.md` §4.11, `#813`; antes, UNA contraseña para las
 * dos): el primer toque lo pide y el segundo —o la sexta cifra— lo usa. El código no va atado a la acción en el servidor,
 * pero pedir el de la otra anula el anterior, así que solo se ve uno. «Desvincular» conserva su nombre: es el botón de la
 * fila, y su código se escribe bajo la lista.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
    /** Las rutas del servidor: aquí, la IDA a Google para VINCULAR (`#347`). */
    urls: { type: Object, default: () => ({}) },
});

const store = useCredentialsStore();
const confirm = useConfirmStore();

store.reset();
// Las cuentas vinculadas son CONTEXTO de esta pantalla: se piden al entrar, como los consentimientos
// en privacidad. Un fallo de lectura no anuncia nada — la zona sigue sirviendo para lo suyo.
store.ensureIdentities();
// El correo al que irá el código lo pone la pieza, del perfil, que se pide una vez.
useProfileStore().ensure();

const { CLOSE_SESSIONS, UNLINK_GOOGLE } = CONFIRM_ACTIONS;
const a = (key) => translate(props.account, key);
const ctx = () => ({ messages: props.messages, auth: props.auth, account: props.account });
const busy = computed(() => store.busy || confirm.busy);
const waiting = (action) => confirm.isShown(action) && ! confirm.ready;

const closeOthers = () => confirm.act(CLOSE_SESSIONS, store, (code) => store.revokeOtherSessions({ code }, ctx()), ctx());
const unlink = () => confirm.act(UNLINK_GOOGLE, store, (code) => store.unlinkIdentity('google', { code }, ctx()), ctx());
</script>

<template>
    <div class="auth">
        <p class="purchase__note">{{ a('account.sessions.intro') }}</p>

        <div v-if="store.notice || confirm.notice" class="auth__errors" role="alert"><p>{{ store.notice || confirm.notice }}</p></div>

        <form class="form auth__form" novalidate @submit.prevent="closeOthers">
            <p v-if="store.done" class="purchase__note" role="status">{{ a('account.sessions.title') }} ✓</p>

            <ConfirmCode id="acct-sessions-code" v-model="confirm.code" :account="account"
                         :shown="confirm.isShown(CLOSE_SESSIONS)" :resends="confirm.resends" :error="confirm.error"
                         :disabled="busy" @complete="closeOthers" @resend="confirm.again(ctx())" />

            <button type="submit" class="btn btn--ink auth__submit" :disabled="busy || waiting(CLOSE_SESSIONS)">
                {{ store.busy ? a('account.sessions.working') : confirm.isShown(CLOSE_SESSIONS) ? a('account.sessions.logout_others') : a('account.confirm.send') }}
            </button>
        </form>

        <!--
          ⚠️⚠️ **Desvincular es el CONTRAPESO del aviso de vinculación** (`specs/auth-con-google.md`
          §5.2): el vínculo se crea solo al entrar con un correo que ya tenía cuenta y se avisa por
          correo — y ese aviso solo sirve de algo si quien lo recibe puede deshacerlo. Sin esto, la
          única salida de un vínculo no pedido era borrar la cuenta.
          ⚠️ El bloque se pinta también SIN vínculos, porque es donde vive el botón de VINCULAR (`#347`):
          esconderlo tras «hay alguno» dejaba el gesto sin puerta (el huevo-y-gallina de `#400`).
          ⚠️ **El envoltorio no es decorativo: es lo que le da AIRE al bloque**, separado del de cerrar
          las otras sesiones (medido en su día: 0 px entre el botón y este título sin él).
          ▶ Desde la A4b va FUERA de aquel formulario: ya no comparten la contraseña, cada uno pide su código.
        -->
        <div v-if="store.identities?.length || urls.google_link" class="account__linked">
            <h3 class="account__card-title">{{ a('account.sessions.identities_title') }}</h3>

            <ul v-if="store.identities?.length" class="account__consents">
                <li v-for="identity in store.identities" :key="identity.provider">
                    <span class="account__consent-type">{{ identity.email_at_link }}</span>
                    <span class="account__consent-meta">
                        <span class="account__consent-part">{{ identity.linked_label }}</span>
                    </span>
                    <button type="button" class="btn btn--ghost" :disabled="busy || waiting(UNLINK_GOOGLE)" @click="unlink()">
                        {{ a('account.sessions.unlink') }}
                    </button>
                </li>
            </ul>

            <ConfirmCode v-if="confirm.isShown(UNLINK_GOOGLE)" id="acct-unlink-code" v-model="confirm.code" :account="account"
                         shown :resends="confirm.resends" :error="confirm.error" :disabled="busy"
                         @complete="unlink()" @resend="confirm.again(ctx())" />

            <!--
              ⚠️⚠️ **Es un `<a>` al SERVIDOR, no un botón con JS**, igual que el de entrar: el flujo
              empieza con una redirección (§6.4) y aquí no hay nada que orquestar.
              ⚠️ Solo si NO hay ya una cuenta de Google vinculada: `UNIQUE(user_id, provider)` es
              una cuenta, una llave por proveedor (`#342`), así que ofrecerlo con una puesta sería
              ofrecer un camino que solo puede acabar en «tu cuenta ya está vinculada a otra».
            -->
            <GoogleButton v-if="urls.google_link && ! store.identities?.length"
                          :href="urls.google_link" :label="a('account.sessions.link_google')" />
        </div>
    </div>
</template>
