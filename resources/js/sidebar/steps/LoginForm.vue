<script setup>
import { computed, ref } from 'vue';
import { t as translate } from '../i18n.js';
import PasswordInput from './PasswordInput.vue';

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

    /**
     * ¿Se ofrece «¿olvidaste tu contraseña?».
     *
     * ⚠️ **Apagado por defecto, y el default es lo que hace este cambio seguro**: el paso 5 del embudo
     * compara su árbol contra el manifiesto congelado de `SidebarDomContractTest`, así que emitir el
     * enlace sin querer lo pondría en rojo. Con el prop apagado, el árbol del embudo es **byte a byte
     * el de ayer** y solo la zona de la cuenta lo enciende (`specs/auth-en-cajon.md` §4.3).
     * ▶ El embudo lo enciende en su propio paso —A7—, que es cuando toca regenerar el manifiesto **y
     * justificarlo en el commit**, que es la condición que ese test pone para no ser una goma de
     * borrar.
     */
    withRecovery: { type: Boolean, default: false },
});

defineEmits(['submit', 'recover']);

const email = defineModel('email', { type: String, default: '' });
const password = defineModel('password', { type: String, default: '' });
const remember = defineModel('remember', { type: Boolean, default: false });

/** Mostrar/ocultar la contraseña. Es estado de ESTA pantalla, no del cajón. */

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
                <PasswordInput :id="'login-password'" v-model="password" autocomplete="current-password" />
                <span v-if="fieldErrors.password" class="form__error">{{ fieldErrors.password }}</span>
            </div>

            <div class="auth__row">
                <label class="check check--opt">
                    <input v-model="remember" type="checkbox">
                    <span>{{ a('login.remember') }}</span>
                </label>
                <!-- Mismo sitio y misma clase que en el Blade de la web: dentro de `.auth__row`, a la
                     derecha del «recuérdame». Lo que cambia es a dónde lleva — a una zona del cajón
                     en vez de a un tercer modal. -->
                <button v-if="withRecovery" type="button" class="auth__link" @click="$emit('recover')">
                    {{ a('login.forgot') }}
                </button>
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
