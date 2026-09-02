<script setup>
import { ref, watch } from 'vue';
import { useCredentialsStore } from '../../stores/credentials.js';
import { fieldError } from '../form-outcome.js';
import { t as translate } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';
import NoPasswordHint from '../NoPasswordHint.vue';

/**
 * **Cerrar sesión en los demás dispositivos** (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * ⚠️ La credencial en curso **sobrevive**, y eso lo garantiza el servidor (`revokeOtherAccess()`,
 * `RGPD-06`): el titular no debe autoexpulsarse al defenderse. Aquí solo se recoge la contraseña.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useCredentialsStore();

store.reset();
// Las cuentas vinculadas son CONTEXTO de esta pantalla: se piden al entrar, como los consentimientos
// en privacidad. Un fallo de lectura no anuncia nada — la zona sigue sirviendo para lo suyo.
store.ensureIdentities();

const current = ref('');

const a = (key) => translate(props.account, key);

/** Al salir bien el campo se vacía: una contraseña escrita a la vista es una filtración. */
watch(() => store.done, (done) => { if (done) current.value = ''; });
</script>

<template>
    <div class="auth">
        <p class="purchase__note">{{ a('account.sessions.intro') }}</p>

        <form class="form auth__form" novalidate @submit.prevent="store.revokeOtherSessions({ currentPassword: current }, { messages, auth })">
            <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

            <p v-if="store.done" class="purchase__note" role="status">{{ a('account.sessions.title') }} ✓</p>

            <div class="form__field">
                <label class="form__label" for="acct-sessions-password">{{ a('account.sessions.current_password') }}</label>
                <PasswordInput :id="'acct-sessions-password'" v-model="current" autocomplete="current-password" />
                <span v-if="fieldError(store.fields, 'current_password')" class="form__error">{{ fieldError(store.fields, 'current_password') }}</span>
                <NoPasswordHint :account="account" />
            </div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="store.busy">
                {{ store.busy ? a('account.sessions.working') : a('account.sessions.logout_others') }}
            </button>

            <!--
              ⚠️⚠️ **Desvincular es el CONTRAPESO del aviso de vinculación** (`specs/auth-con-google.md`
              §5.2): el vínculo se crea solo al entrar con un correo que ya tenía cuenta y se avisa por
              correo — y ese aviso solo sirve de algo si quien lo recibe puede deshacerlo. Sin esto, la
              única salida de un vínculo no pedido era borrar la cuenta.
              ▶ Va DENTRO del mismo formulario a propósito: reutiliza la contraseña que ya se pide
              arriba, que es la que el servidor exige también aquí. Un segundo campo para lo mismo
              sería pedirla dos veces en la misma pantalla.
              ▶ Sin ninguna vinculada no se pinta nada: un bloque vacío con título no dice nada.
            -->
            <template v-if="store.identities?.length">
                <h3 class="account__card-title">{{ a('account.sessions.identities_title') }}</h3>

                <ul class="account__consents">
                    <li v-for="identity in store.identities" :key="identity.provider">
                        <span class="account__consent-type">{{ identity.email_at_link }}</span>
                        <span class="account__consent-meta">{{ identity.linked_label }}</span>
                        <button type="button" class="btn btn--ghost" :disabled="store.busy"
                                @click="store.unlinkIdentity(identity.provider, { currentPassword: current }, { messages, auth })">
                            {{ a('account.sessions.unlink') }}
                        </button>
                    </li>
                </ul>
            </template>
        </form>
    </div>
</template>
