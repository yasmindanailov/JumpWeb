<script setup>
import { computed, ref } from 'vue';
import { usePrivacyStore } from '../../stores/privacy.js';
import ZoneLoading from '../ZoneLoading.vue';
import { consentRows } from '../privacy.js';
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
    /** El grupo `ui`: el rótulo del spinner mientras la zona trae sus datos. */
    ui: { type: Object, default: () => ({}) },
    messages: { type: Object, default: () => ({}) },
    auth: { type: Object, default: () => ({}) },
});

const store = usePrivacyStore();

// Al entrar se limpia lo que dijo el servidor la vez anterior, como en las demás zonas.
store.reset();

// Y se piden los consentimientos, solo si no están: la lista es contexto, no cambia sola.
store.ensureConsents();

const consents = computed(() => consentRows(store.consents));

const current = ref('');

const a = (key) => translate(props.account, key);

const ctx = () => ({ messages: props.messages, auth: props.auth });

async function remove() {
    if (! window.confirm(a('account.privacy.delete_confirm'))) return;

    if (await store.deleteAccount({ currentPassword: current.value }, ctx())) window.location.assign('/');
}
</script>

<template>
    <!--
      ⚠️⚠️ **Las DOS secciones van en tarjeta, y son las que la página retirada ya tenía**
      (2026-08-23). Hasta hoy colgaban de un `<div class="auth">` que **no tiene ninguna regla**, así
      que la de descargar y la de borrar salían pegadas, sin separación ni jerarquía: dos derechos
      distintos leídos como un bloque. `/mi-cuenta` las pintaba con `account__card` y
      `account__card--danger`, y ésas son las que se usan aquí.
      ▶ `account__grid` es quien pone la separación (su `gap`), así que **el ritmo no lo inventa esta
      pantalla**: lo hereda de la que sustituye.
    -->
    <div class="auth account__grid">
        <p class="purchase__note">{{ a('account.privacy.intro') }}</p>

        <div v-if="store.notice" class="auth__errors" role="alert"><p>{{ store.notice }}</p></div>

        <!--
          La prueba visible del art. 7.1: a qué dijo que sí, cuándo y sobre qué versión. Se publica
          porque `/mi-cuenta` lo enseña y esa página se retira (tanda 3).
          ⚠️ Sin IP, igual que la página: es parte de la prueba y viaja en el export, que es un acto
          explícito del titular.
        -->
        <section class="account__card">
            <h3 class="account__card-title">{{ a('account.privacy.consents_title') }}</h3>

                <!-- ⚠️ El spinner ANTES del «no hay consentimientos»: mientras se piden, decir que no hay
                 es decir algo falso. -->
            <ZoneLoading v-if="store.busy && ! store.consentsLoaded" :ui="ui" />

            <p v-else-if="store.consentsLoaded && ! consents.length" class="account__card-sub">{{ a('account.privacy.no_consents') }}</p>

            <ul v-else-if="consents.length" class="account__consents">
                <li v-for="consent in consents" :key="consent.key">
                    <span class="account__consent-type">{{ consent.label }}</span>
                    <span class="account__consent-meta">{{ consent.meta }}</span>
                </li>
            </ul>

            <!-- El derecho de PORTABILIDAD (art. 20). No pide contraseña: descargarse los datos propios
                 no destruye ni cede nada, y es lo que hace hoy la web. -->
            <button type="button" class="btn btn--zone auth__submit" :disabled="store.busy" @click="store.exportData(ctx())">
                {{ a('account.privacy.export_btn') }}
            </button>

            <p v-if="store.savedAs" class="account__card-sub" role="status">{{ store.savedAs }} ✓</p>
        </section>

        <!-- El derecho de SUPRESIÓN (art. 17). En su propia tarjeta y con el borde de peligro que la
             página retirada ya le daba: es irreversible y no debe leerse como una opción más. -->
        <section class="account__card account__card--danger">
            <h3 class="account__card-title">{{ a('account.privacy.delete_title') }}</h3>
            <p class="account__card-sub">{{ a('account.privacy.delete_intro') }}</p>

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
        </section>
    </div>
</template>
