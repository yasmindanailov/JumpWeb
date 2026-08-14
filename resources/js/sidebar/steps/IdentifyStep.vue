<script setup>
import { computed, ref } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * Paso 5 — la IDENTIFICACIÓN (Fase 4 · paso 4.4a·2).
 *
 * La web monta aquí `<livewire:auth.login :embedded="true">`; el motor SPA pinta el mismo árbol y
 * habla con `POST /api/v1/auth/login`, que consume **el mismo** `Identity\Services\PasswordLogin`. La
 * decisión de qué se enseña cuando el servidor dice que no vive en `login.js`, con su red.
 *
 * ⚠️ **Cuatro detalles del árbol que no se adivinan leyendo el Blade** y que el diff sí ve:
 *  - este paso **no tiene banda de progreso**, así que trae su propio «Volver» (`bk-back
 *    purchase__back`): la cuenta y la reserva todavía no existen, y retroceder al carrito es seguro;
 *  - los textos NO son del grupo `tickets`: el título, la intro y las pestañas salen de `account.*` y
 *    los avisos de `auth.*`, así que el montaje inyecta esos dos grupos aparte (§4.5);
 *  - `embedded` **quita dos nodos**: el enlace de «¿olvidaste tu contraseña?» y el pie de «¿no tienes
 *    cuenta?». Dentro del cajón esas rutas ya están en las pestañas o llevan fuera de la compra;
 *  - el input de contraseña es un `.pwd-input` con **DOS** `<svg>` —ojo y ojo tachado— que se
 *    alternan: emitir uno solo pasa el contrato de clases y pierde el icono en un estado.
 */
const props = defineProps({
    /** `login` · `register`. Decide qué pestaña va activa y qué formulario cuelga debajo. */
    mode: { type: String, default: 'login' },

    /** Avisos del intento anterior: `{global, fields: {email, password}}` (`login.js`). */
    errors: { type: Object, default: () => ({ global: '', fields: {} }) },

    /** `true` mientras la petición de login está en vuelo: cambia el rótulo del botón. */
    submitting: { type: Boolean, default: false },

    /** El grupo `tickets` (para el «Volver» y el título del paso). */
    messages: { type: Object, default: () => ({}) },

    /** El grupo `account`, podado a `login`: los rótulos del formulario. */
    account: { type: Object, default: () => ({}) },
});

defineEmits(['back', 'set-mode', 'submit']);

const email = defineModel('email', { type: String, default: '' });
const password = defineModel('password', { type: String, default: '' });
const remember = defineModel('remember', { type: Boolean, default: false });

/** Mostrar/ocultar la contraseña. Es estado de ESTA pantalla, no del cajón. */
const revealed = ref(false);

const t = (key) => translate(props.messages, key);
const a = (key) => translate(props.account, key);

const fieldErrors = computed(() => props.errors?.fields ?? {});
</script>

<template>
    <!-- Este paso no lleva banda de progreso, así que el «Volver» es suyo. -->
    <button type="button" class="bk-back purchase__back" @click="$emit('back')">
        <svg class="arrow-ico" viewBox="0 0 24 24" aria-hidden="true"></svg>
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

    <div class="auth">
        <div class="auth__head">
            <span class="eyebrow">{{ a('login.eyebrow') }}</span>
            <h2 class="auth__title">{{ a('login.title') }}</h2>
        </div>

        <!-- `novalidate` como el Blade: quien valida es el servidor, con las mismas reglas para los
             dos motores. La validación del navegador daría un tercer juego de mensajes. -->
        <form class="form auth__form" novalidate @submit.prevent="$emit('submit')">
            <!-- El aviso del LIMITADOR va aquí, en banner, y no bajo el campo: mezclarlo con
                 «credenciales incorrectas» —que es genérico a propósito— fue el hallazgo L-02. -->
            <div v-if="errors.global" class="auth__errors" role="alert">
                <p>{{ errors.global }}</p>
            </div>

            <div class="form__field">
                <label class="form__label" for="login-email">{{ a('login.email') }}</label>
                <input id="login-email" v-model="email" type="email" autocomplete="email" required>
                <span v-if="fieldErrors.email" class="form__error">{{ fieldErrors.email }}</span>
            </div>

            <div class="form__field">
                <label class="form__label" for="login-password">{{ a('login.password') }}</label>
                <div class="pwd-input">
                    <input id="login-password" v-model="password" :type="revealed ? 'text' : 'password'"
                           autocomplete="current-password" required>
                    <button type="button" class="pwd-input__toggle" tabindex="-1" @click="revealed = ! revealed">
                        <svg v-show="! revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"></svg>
                        <svg v-show="revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"></svg>
                    </button>
                </div>
                <span v-if="fieldErrors.password" class="form__error">{{ fieldErrors.password }}</span>
            </div>

            <div class="auth__row">
                <label class="check check--opt">
                    <input v-model="remember" type="checkbox">
                    <span>{{ a('login.remember') }}</span>
                </label>
            </div>

            <button type="submit" class="btn btn--zone auth__submit" :disabled="submitting">
                <span v-show="! submitting">{{ a('login.submit') }}</span>
                <span v-show="submitting" class="btn__loading">
                    <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('login.submitting') }}
                </span>
            </button>
        </form>
    </div>
</template>
