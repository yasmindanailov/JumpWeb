<script setup>
import { ref, watch } from 'vue';
import { fieldError } from '../credentials.js';
import { t as translate } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';

/**
 * **Cerrar sesión en los demás dispositivos** (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * ⚠️ La credencial en curso **sobrevive**, y eso lo garantiza el servidor (`revokeOtherAccess()`,
 * `RGPD-06`): el titular no debe autoexpulsarse al defenderse. Aquí solo se recoge la contraseña.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
    fields: { type: Object, default: () => ({}) },
    notice: { type: String, default: '' },
    done: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const current = ref('');

const a = (key) => translate(props.account, key);

/** Al salir bien, el formulario se vacía solo: una contraseña escrita a la vista es una filtración. */
watch(() => props.done, (done) => { if (done) { current.value = ''; } });
</script>

<template>
    <div class="auth">
        <p class="purchase__note">{{ a('account.sessions.intro') }}</p>

        <form class="form auth__form" novalidate @submit.prevent="emit('submit', { currentPassword: current })">
            <div v-if="notice" class="auth__errors" role="alert"><p>{{ notice }}</p></div>

            <p v-if="done" class="purchase__note" role="status">{{ a('account.sessions.title') }} ✓</p>

            <div class="form__field">
                <label class="form__label" for="acct-sessions-password">{{ a('account.sessions.current_password') }}</label>
                <PasswordInput :id="'acct-sessions-password'" v-model="current" autocomplete="current-password" />
                <span v-if="fieldError(fields, 'current_password')" class="form__error">{{ fieldError(fields, 'current_password') }}</span>
            </div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="busy">
                {{ busy ? a('account.sessions.working') : a('account.sessions.logout_others') }}
            </button>
        </form>
    </div>
</template>
