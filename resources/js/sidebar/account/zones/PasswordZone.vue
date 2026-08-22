<script setup>
import { ref, watch } from 'vue';
import { useCredentialsStore } from '../../stores/credentials.js';
import { fieldError } from '../form-outcome.js';
import { t as translate } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';

/**
 * **Cambiar la contraseña** (`specs/area-cliente.md` §9, tanda 2 · paso 6b).
 *
 * **Pinta y recoge; no decide nada.** Si la actual es correcta, si la nueva cumple la política y
 * cuántos intentos quedan lo dice el SERVIDOR, y `stores/credentials.js` coloca su respuesta.
 *
 * ⚠️ **Lo único que se comprueba aquí es que las dos copias coincidan**, y es correcto que sea así:
 * repetir la contraseña es cosa de ESTE formulario —el contrato no lo pide— y comprobarlo antes
 * ahorra un viaje que el servidor rechazaría por un motivo que el cliente ya conoce.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = useCredentialsStore();

// Al entrar se limpia lo que dijo el servidor la vez anterior: un aviso de hace cinco minutos leído
// sobre un formulario vacío diría algo falso del intento que aún no se ha hecho.
store.reset();

const current = ref('');
const password = ref('');
const confirmation = ref('');
const mismatch = ref(false);

const a = (key) => translate(props.account, key);

function submit() {
    mismatch.value = password.value !== confirmation.value;

    if (mismatch.value) {
        // ⚠️ **Y se limpia lo que dijo el servidor la vez anterior.** Sin esto, quien corrige su
        // contraseña actual y se equivoca repitiendo la nueva sigue viendo «la contraseña actual no
        // es correcta» —un aviso que ya no describe nada— junto al de «no coinciden». Medido en
        // navegador: pasaba, y el primer test no lo distinguía porque solo miraba que hubiera error.
        store.reset();

        return;
    }

    store.changePassword({ currentPassword: current.value, password: password.value },
        { messages: props.messages, auth: props.auth });
}

// ⚠️ Al salir bien, el formulario se VACÍA solo: dejar una contraseña escrita en un campo visible es
// regalarla a quien mire por encima del hombro — y en un panel que se queda abierto sobre la página,
// eso dura hasta que el cliente lo cierre.
watch(() => store.done, (done) => {
    if (done) { current.value = ''; password.value = ''; confirmation.value = ''; mismatch.value = false; }
});
</script>

<template>
    <div class="auth">
        <p class="purchase__note">{{ a('account.password.intro') }}</p>

        <!-- `novalidate` como el resto del cajón: quien valida es el servidor, con las mismas reglas
             para las dos superficies. La del navegador daría un tercer juego de mensajes. -->
        <form class="form auth__form" novalidate @submit.prevent="submit">
            <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

            <p v-if="store.done" class="purchase__note" role="status">{{ a('account.password.title') }} ✓</p>

            <div class="form__field">
                <label class="form__label" for="acct-current-password">{{ a('account.password.current') }}</label>
                <PasswordInput :id="'acct-current-password'" v-model="current" autocomplete="current-password" />
                <span v-if="fieldError(store.fields, 'current_password')" class="form__error">{{ fieldError(store.fields, 'current_password') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-new-password">{{ a('account.password.new') }}</label>
                <PasswordInput :id="'acct-new-password'" v-model="password" autocomplete="new-password" />
                <span v-if="fieldError(store.fields, 'password')" class="form__error">{{ fieldError(store.fields, 'password') }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="acct-confirm-password">{{ a('account.password.confirm') }}</label>
                <PasswordInput :id="'acct-confirm-password'" v-model="confirmation" autocomplete="new-password" />
                <span v-if="mismatch" class="form__error">{{ a('account.password.mismatch') }}</span>
            </div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="store.busy">
                {{ store.busy ? a('account.password.saving') : a('account.password.save') }}
            </button>
        </form>
    </div>
</template>
