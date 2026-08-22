<script setup>
import { ref } from 'vue';
import { usePrivacyStore } from '../../stores/privacy.js';
import { fieldError } from '../form-outcome.js';
import { t as translate } from '../../i18n.js';
import PasswordInput from '../../steps/PasswordInput.vue';

/**
 * **Privacidad y datos**: los dos derechos RGPD del titular (`specs/area-cliente.md` §9, paso 8).
 *
 * **Pinta y recoge; no decide nada.** Componer el documento, comprobar la contraseña y purgar la
 * cuenta es del SERVIDOR; `stores/privacy.js` coloca lo que responda.
 *
 * ⚠️ **La confirmación nativa antes de borrar no es adorno**: es la ÚNICA acción irreversible del
 * producto, y la web la lleva desde siempre (`wire:confirm`). Quitarla aquí habría dejado el borrado
 * a un clic de distancia justo en la superficie donde el cliente está tocando otras cosas.
 *
 * ⚠️ **Al borrar bien se SALE de la página**, no se repinta el cajón: la sesión ya no vale —el
 * servidor revoca todas las credenciales— y todo lo que hay alrededor (cabecera, bloque de cuenta,
 * cesta) habla de una cuenta que acaba de dejar de existir. La web hace lo mismo (`redirect('/')`) y
 * el aviso de despedida lo deja el servidor en la sesión nueva, así que los dos caminos acaban igual.
 *
 * ⚠️ **Y el campo NO se vacía al rechazar**, igual que en las otras tres pantallas que reconfirman
 * contraseña: quien se equivoca escribiendo tiene que poder corregir, no volver a teclear. La primera
 * versión lo vaciaba y el recorrido de navegador lo cazó (`V10`) — vaciarlo solo tiene sentido al
 * SALIR bien, y aquí salir bien significa que esta página deja de existir.
 */
const props = defineProps({
    account: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = usePrivacyStore();

// Al entrar se limpia lo que dijo el servidor la vez anterior, como en las demás zonas.
store.reset();

const current = ref('');

const a = (key) => translate(props.account, key);

const ctx = () => ({ messages: props.messages, auth: props.auth });

async function remove() {
    if (! window.confirm(a('account.privacy.delete_confirm'))) return;

    if (await store.deleteAccount({ currentPassword: current.value }, ctx())) window.location.assign('/');
}
</script>

<template>
    <div class="auth">
        <p class="purchase__note">{{ a('account.privacy.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

        <!-- El derecho de PORTABILIDAD (art. 20). No pide contraseña: descargarse los datos propios
             no destruye ni cede nada, y es lo que hace hoy la web. -->
        <button type="button" class="btn btn--zone auth__submit" :disabled="store.busy" @click="store.exportData(ctx())">
            {{ a('account.privacy.export_btn') }}
        </button>

        <p v-if="store.savedAs" class="purchase__note" role="status">{{ store.savedAs }} ✓</p>

        <!-- El derecho de SUPRESIÓN (art. 17). Debajo, y con su propia advertencia delante. -->
        <h3 class="wiz__title">{{ a('account.privacy.delete_title') }}</h3>
        <p class="purchase__note">{{ a('account.privacy.delete_intro') }}</p>

        <form class="form auth__form" novalidate @submit.prevent="remove">
            <div class="form__field">
                <label class="form__label" for="acct-delete-password">{{ a('account.privacy.delete_password') }}</label>
                <PasswordInput :id="'acct-delete-password'" v-model="current" autocomplete="current-password" />
                <span v-if="fieldError(store.fields, 'current_password')" class="form__error">{{ fieldError(store.fields, 'current_password') }}</span>
            </div>

            <button type="submit" class="btn account__delete-btn" :disabled="store.busy">
                {{ store.busy ? a('account.privacy.deleting') : a('account.privacy.delete_btn') }}
            </button>
        </form>
    </div>
</template>
