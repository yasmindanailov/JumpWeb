<script setup>
import { t as translate } from '../i18n.js';
import EntryForm from './EntryForm.vue';
import RegisterForm from './RegisterForm.vue';

/**
 * Paso 5 — la IDENTIFICACIÓN, con un código al correo (A4a de `docs/specs/acceso-con-codigo.md` §4.11, `#848`/`#849`).
 *
 * UNA puerta y sin pestañas: el correo; con cuenta, el código (`EntryForm`); nuevo, el alta (`RegisterForm`, contexto
 * `purchase`: pay-first). Hablan con `POST /api/v1/auth/code`, `/auth/login` y `/auth/register`; qué se enseña cuando el
 * servidor dice que no vive en `login.js` y `register.js`, con su red, y la cara en `stores/auth.js`.
 *
 * ⚠️ **Dos detalles del árbol que no se adivinan**:
 *  - desde `#555` este paso **sí tiene banda de progreso**, y con ella su «Volver» («Volver al carrito»: la cuenta y la
 *    reserva todavía no existen, así que retroceder es seguro);
 *  - los textos NO son del grupo `tickets`: los rótulos salen de `account.*` y los avisos de `auth.*`, así que el montaje
 *    inyecta esos dos grupos aparte (§4.5).
 * ▶ Sin «¿olvidaste tu contraseña?»: ningún cliente la escribe ya (`#847`).
 */
const props = defineProps({
    /** La cara de la puerta: `email`, `code` o `register` (`stores/auth.js::STAGE_*`). */
    stage: { type: String, default: 'email' },

    /** Avisos de la puerta y del código: `{global, fields}` (`login.js`). */
    entryErrors: { type: Object, default: () => ({ global: '', fields: {} }) },

    /** Avisos del alta: `{summary, fields}` (`register.js`). */
    registerErrors: { type: Object, default: () => ({ summary: [], fields: {} }) },

    /** `true` mientras hay una petición de auth en vuelo. */
    submitting: { type: Boolean, default: false },

    /** El grupo `tickets` (para el título del paso). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `account`: los rótulos de la puerta y del alta. */
    account: { type: Object, default: () => ({}) },

    /** Clave pública del anti-bot; baja tal cual a `RegisterForm` (4.4b·2). */
    turnstileSiteKey: { type: String, default: '' },

    /** La ida a Google. Vacía = esta instalación no la ofrece, y no se pinta nada. Por PROP: el contrato de árbol lo pinta en Node. */
    googleUrl: { type: String, default: '' },

    /** La política de privacidad, para la fila del alta (`#566`). */
    privacyUrl: { type: String, default: '' },

    /** El correo al que fue el código, si ya fue OTRO y la espera de «Pedir otro código» (`stores/auth.js`). */
    sentTo: { type: String, default: '' },
    resent: { type: Boolean, default: false },
    wait: { type: Number, default: 0 },
});

// ⚠️ Sin `back`: el «Volver» de esta pantalla lo trae la banda desde `#555`.
defineEmits(['continue', 'enter', 'resend', 'change-email', 'submit-register']);

/**
 * Los campos de la puerta y del alta, en un solo objeto. Viven en el padre y bajan por `v-model` para que **el código no
 * sobreviva** a un cambio de pantalla: quien orquesta el paso es quien sabe cuándo dejaron de hacer falta.
 */
const form = defineModel('form', { type: Object, default: () => ({}) });

const t = (key) => translate(props.messages, key);
</script>

<template>

    <h3 class="wiz__title">{{ t('identify_title') }}</h3>
    <p class="wiz__lede">{{ t('identify_intro') }}</p>

    <RegisterForm
        v-if="stage === 'register'"
        v-model:name="form.name"
        v-model:email="form.email"
        v-model:born-on="form.born_on"
        v-model:accept-waiver="form.accept_waiver"
        v-model:website="form.website"
        v-model:turnstile-token="form.turnstile_token"
        :turnstile-site-key="turnstileSiteKey"
        :errors="registerErrors"
        :submitting="submitting"
        :account="account"
        :google-url="googleUrl"
        :privacy-url="privacyUrl"
        @submit="$emit('submit-register')"
        @change-email="$emit('change-email')" />

    <EntryForm
        v-else
        v-model:email="form.email"
        v-model:code="form.code"
        v-model:remember="form.remember"
        :stage="stage"
        :errors="entryErrors"
        :submitting="submitting"
        :account="account"
        :google-url="googleUrl"
        :sent-to="sentTo"
        :resent="resent"
        :wait="wait"
        @continue="$emit('continue')"
        @enter="$emit('enter')"
        @resend="$emit('resend')"
        @change-email="$emit('change-email')" />
</template>
