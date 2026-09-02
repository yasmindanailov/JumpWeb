<script setup>
import { t as translate } from '../i18n.js';
import LoginForm from './LoginForm.vue';
import RegisterForm from './RegisterForm.vue';

/**
 * Paso 5 — la IDENTIFICACIÓN (Fase 4 · pasos 4.4a·2 y 4.4b·1).
 *
 * La web monta aquí `<livewire:auth.login|register :embedded="true">`; el motor SPA pinta el mismo
 * árbol y habla con `POST /api/v1/auth/login` y `POST /api/v1/auth/register`, que consumen **los
 * mismos servicios de dominio** que los modales de la web. La decisión de qué se enseña cuando el
 * servidor dice que no vive en `login.js` y `register.js`, con su red.
 *
 * ⚠️ **Tres detalles del árbol que no se adivinan leyendo el Blade** y que el diff sí ve:
 *  - este paso **no tiene banda de progreso**, así que trae su propio «Volver» (`bk-back
 *    purchase__back`): la cuenta y la reserva todavía no existen, y retroceder al carrito es seguro;
 *  - los textos NO son del grupo `tickets`: los rótulos salen de `account.*` y los avisos de `auth.*`,
 *    así que el montaje inyecta esos dos grupos aparte (§4.5);
 *  - las dos pestañas se emiten SIEMPRE; lo que cambia con el modo es cuál lleva `active` y qué
 *    formulario cuelga debajo.
 *
 * **Los formularios son componentes hijos y eso no cambia el árbol**: Vue no envuelve a sus hijos, así
 * que lo que compara el gate es idéntico. Están separados porque son dos responsabilidades distintas
 * —y porque juntos serían 250 líneas de plantilla—.
 */
const props = defineProps({
    /** `login` · `register`. Decide qué pestaña va activa y qué formulario cuelga debajo. */
    mode: { type: String, default: 'login' },

    /** Avisos del login: `{global, fields}` (`login.js`). */
    loginErrors: { type: Object, default: () => ({ global: '', fields: {} }) },

    /** Avisos del alta: `{summary, fields}` (`register.js`). */
    registerErrors: { type: Object, default: () => ({ summary: [], fields: {} }) },

    /** `true` mientras hay una petición de auth en vuelo. */
    submitting: { type: Boolean, default: false },

    /** El grupo `tickets` (para el «Volver» y el título del paso). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `account`: los rótulos de los dos formularios. */
    account: { type: Object, default: () => ({}) },

    /** Clave pública del anti-bot; baja tal cual a `RegisterForm` (4.4b·2). */
    turnstileSiteKey: { type: String, default: '' },

    /**
     * La ida a Google (`specs/auth-con-google.md`). Vacía = esta instalación no la ofrece, y entonces
     * no se pinta nada: el hueco falla hacia invisible.
     *
     * ⚠️ Llega por PROP y no de un store porque este paso ya recibe todo así — es lo que permite
     * renderizarlo en Node para el contrato de árbol.
     */
    googleUrl: { type: String, default: '' },
});

defineEmits(['back', 'set-mode', 'submit-login', 'submit-register', 'recover']);

/**
 * Los campos de los dos formularios, en un solo objeto.
 *
 * Viven en el padre (`Sidebar.vue`) y bajan por `v-model` para que **la contraseña no sobreviva** a un
 * cambio de pantalla: quien orquesta el paso es quien sabe cuándo dejaron de hacer falta.
 */
const form = defineModel('form', { type: Object, default: () => ({}) });

const t = (key) => translate(props.messages, key);
const a = (key) => translate(props.account, key);
</script>

<template>
    <!-- Este paso no lleva banda de progreso, así que el «Volver» es suyo. -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <!-- `arrow-left` del sistema de diseño, copiado byte a byte (`SidebarIconParityTest`). -->
        <svg class="arrow-ico" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"
             stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
             aria-hidden="true" focusable="false">
            <g transform="translate(24 0) scale(-1 1)">
                <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
                <path d="M4.6 12h9.4" fill="none" />
            </g>
        </svg>
        <span>{{ t('back_to_cart') }}</span>
    </button>

    <h3 class="wiz__title">{{ t('identify_title') }}</h3>
    <p class="purchase__note">{{ t('identify_intro') }}</p>

    <div class="zone-tabs purchase__authtabs">
        <button type="button" class="zone-tab" :class="{ active: mode === 'login' }"
                @click="$emit('set-mode', 'login')">{{ a('login.cta') }}</button>
        <button type="button" class="zone-tab" :class="{ active: mode === 'register' }"
                @click="$emit('set-mode', 'register')">{{ a('register.cta') }}</button>
    </div>

    <!-- ⚠️ La ida a Google baja a los DOS formularios y no cuelga de aquí: dentro va entre la cabecera
         y los campos, que es donde el owner la pidió (T8·d). Sigue ofreciéndose en las dos pestañas —
         desde aquí sirve para las dos cosas, porque el retorno decide si hay cuenta o hay que crearla. -->
    <LoginForm
        v-if="mode === 'login'"
        v-model:email="form.email"
        v-model:password="form.password"
        v-model:remember="form.remember"
        :errors="loginErrors"
        :submitting="submitting"
        :account="account"
        :with-recovery="true"
        :google-url="googleUrl"
        @submit="$emit('submit-login')"
        @recover="$emit('recover')" />

    <RegisterForm
        v-else
        v-model:name="form.name"
        v-model:email="form.email"
        v-model:phone="form.phone"
        v-model:password="form.password"
        v-model:accept-waiver="form.accept_waiver"
        v-model:website="form.website"
        v-model:turnstile-token="form.turnstile_token"
        :turnstile-site-key="turnstileSiteKey"
        :errors="registerErrors"
        :submitting="submitting"
        :account="account"
        :google-url="googleUrl"
        @submit="$emit('submit-register')" />
</template>
