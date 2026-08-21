<script setup>
import { computed, ref } from 'vue';
import { t as translate } from '../i18n.js';

/**
 * El formulario de INICIO DE SESIÓN del paso 5 (Fase 4 · paso 4.4a·2).
 *
 * Sale de `IdentifyStep.vue` en 4.4b·1, cuando el registro entra y el paso pasa a tener dos
 * formularios. **No añade ni quita un solo nodo**: Vue no envuelve los componentes hijos, así que el
 * árbol que compara el gate es el mismo — y ese movimiento es justo lo que la red existe para cubrir.
 *
 * ⚠️ **Dos detalles del árbol que no se adivinan leyendo el Blade**: el input de contraseña es un
 * `.pwd-input` con **DOS** `<svg>` que se alternan —emitir uno solo pasa el contrato de clases y
 * pierde el icono en un estado—, y `embedded` **quita dos nodos** (el enlace de «¿olvidaste tu
 * contraseña?» y el pie de «¿no tienes cuenta?»), que dentro del cajón llevarían fuera de la compra.
 */
const props = defineProps({
    /** Avisos del intento anterior: `{global, fields: {email, password}}` (`login.js`). */
    errors: { type: Object, default: () => ({ global: '', fields: {} }) },

    /** `true` mientras la petición está en vuelo: cambia el rótulo del botón. */
    submitting: { type: Boolean, default: false },

    /** El grupo `account`, podado a `login`. */
    account: { type: Object, default: () => ({}) },
});

defineEmits(['submit']);

const email = defineModel('email', { type: String, default: '' });
const password = defineModel('password', { type: String, default: '' });
const remember = defineModel('remember', { type: Boolean, default: false });

/** Mostrar/ocultar la contraseña. Es estado de ESTA pantalla, no del cajón. */
const revealed = ref(false);

const a = (key) => translate(props.account, key);

const fieldErrors = computed(() => props.errors?.fields ?? {});
</script>

<template>
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
                        <svg v-show="! revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.512 7.36 4.5 12 4.5s8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644C20.577 16.488 16.64 19.5 12 19.5s-8.577-3.012-9.964-7.178Z"
                                  stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                        <svg v-show="revealed" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83M9.363 5.365A9.466 9.466 0 0 1 12 5c4.64 0 8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644 9.46 9.46 0 0 1-3.07 4.385M6.61 6.61C4.547 7.97 2.999 9.984 2.036 12.178a1 1 0 0 0 0 .644C3.423 16.988 7.36 19.5 12 19.5a9.46 9.46 0 0 0 5.39-1.61"
                                  stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
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
