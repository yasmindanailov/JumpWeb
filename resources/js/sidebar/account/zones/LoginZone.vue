<script setup>
import { useAuthStore } from '../../stores/auth.js';
import { useAccountStore } from '../../stores/account.js';
import { ZONES } from '../navigation.js';
import { landOnAccount } from '../after-auth.js';
import { api } from '../../api.js';
import LoginForm from '../../steps/LoginForm.vue';
import GoogleButton from '../../steps/GoogleButton.vue';
import { t as translate } from '../../i18n.js';

/**
 * **IDENTIFICARSE dentro del cajón, fuera de la compra** (`specs/auth-en-cajon.md` §4.1).
 *
 * Es la zona en la que aterriza un invitado que entra al área de cliente, y la que sustituye al modal
 * de login de la cabecera — el último de los tres sitios que `DECISIONES #66` quería unificar.
 *
 * ⚠️ **Reutiliza `steps/LoginForm.vue`, no lo copia**: es el mismo formulario del paso 5 del embudo,
 * ya probado contra el servidor. Lo único que enciende esta zona es el enlace de «¿olvidaste tu
 * contraseña?», que en el embudo sigue apagado hasta su propio paso.
 *
 * ⚠️⚠️ **Al entrar bien se NAVEGA, y eso lo obliga una medida**: los textos del área viajan solo con
 * sesión, así que quedarse aquí dejaría el índice con los rótulos en blanco. El porqué completo está
 * en `account/after-auth.js`, que además es lo que hace que la pila de retorno no conserve esta
 * pantalla y que el formulario no sobreviva en un dispositivo compartido.
 */
const props = defineProps({
    /** El grupo `account`, podado: de aquí salen los rótulos del formulario. */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: el aviso genérico de «inténtalo más tarde». */
    messages: { type: Object, default: () => ({}) },
    /** El grupo `auth`: los literales del «no» del servidor. */
    auth: { type: Object, default: () => ({}) },
    /** Las rutas que compone el servidor. De aquí sale la puerta a la que se aterriza. */
    urls: { type: Object, default: () => ({}) },
});

const store = useAuthStore();
const nav = useAccountStore();

// Los avisos son de un intento que ya no se ve; los CAMPOS no se tocan, para que quien vaya a
// recuperar su contraseña y vuelva no tenga que escribir su correo dos veces.
store.clearNotices();

const a = (key) => translate(props.account, key);

async function submit() {
    const result = await store.login({ api, messages: props.messages, auth: props.auth });

    if (result.ok) landOnAccount({ urls: props.urls });
}
</script>

<template>
    <LoginForm
        v-model:email="store.form.email"
        v-model:password="store.form.password"
        v-model:remember="store.form.remember"
        :errors="store.loginError"
        :submitting="store.busy"
        :account="account"
        :with-recovery="true"
        @submit="submit"
        @recover="nav.go(ZONES.FORGOT)" />

    <!-- La otra forma de entrar. Va DEBAJO del formulario y no encima: quien ya tiene contraseña la
         teclea, y quien no, lee hasta abajo. Sin claves configuradas no se pinta nada. -->
    <GoogleButton :href="urls.google ?? ''" :label="a('register.google_cta')" />
</template>
