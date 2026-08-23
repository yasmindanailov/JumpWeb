<script setup>
import { useAuthStore } from '../../stores/auth.js';
import { useAccountStore } from '../../stores/account.js';
import { api } from '../../api.js';
import { t as translate } from '../../i18n.js';

/**
 * **RECUPERAR LA CONTRASEÑA dentro del cajón** (`specs/auth-en-cajon.md` §4.1).
 *
 * La tercera pantalla de auth, y la única que el cajón no sabía hacer. **Pinta y recoge; no decide
 * nada**: qué se enseña tras el «no» del servidor lo compone `forgot.js`, con sus casos y su
 * `node --test`.
 *
 * ⚠️⚠️ **Las dos caras de esta zona las separa UNA sola condición, y tiene que seguir siendo así.**
 * El servidor responde `202` exista o no la cuenta (`SEC-06`), así que la pantalla de «revisa tu
 * correo» es la misma en los dos casos: **es la propiedad, no una simplificación**. Cualquier
 * condición extra que se cuele aquí —un texto distinto, un estado distinto— reconstruiría en el
 * cliente el oráculo de enumeración que el servidor se cuida de no dar.
 *
 * ⚠️ El aviso del limitador va **bajo el campo**, no en banner: es donde lo pone
 * `Auth\ForgotPassword::sendLink()`, al revés que el login. Lo reparte `forgot.js`.
 */
const props = defineProps({
    /** El grupo `account`, podado: de aquí salen los rótulos de esta pantalla (`forgot.*`). */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: el aviso genérico de «inténtalo más tarde». */
    messages: { type: Object, default: () => ({}) },
    /** El grupo `auth`: el texto del limitador, el MISMO que usa el login. */
    auth: { type: Object, default: () => ({}) },
});

const store = useAuthStore();
const nav = useAccountStore();

// Se entra siempre por el formulario: un «ya te hemos enviado el enlace» de la visita anterior —o del
// cliente anterior, en una tablet compartida— hablaría de algo que este cliente no ha pedido.
store.clearNotices();

const a = (key) => translate(props.account, key);

const submit = () => store.requestPasswordLink({ api, messages: props.messages, auth: props.auth });
</script>

<template>
    <div class="auth">
        <!-- La confirmación GENÉRICA: no revela si el correo existe. Es la misma pantalla para una
             cuenta que existe y para una que no, que es lo único para lo que sirve. -->
        <template v-if="store.forgotSent">
            <div class="auth__head">
                <span class="eyebrow">{{ a('forgot.eyebrow') }}</span>
                <h2 class="auth__title">{{ a('forgot.sent_title') }}</h2>
            </div>
            <p class="auth__sub" role="status">{{ a('forgot.sent_msg') }}</p>
        </template>

        <template v-else>
            <div class="auth__head">
                <span class="eyebrow">{{ a('forgot.eyebrow') }}</span>
                <h2 class="auth__title">{{ a('forgot.title') }}</h2>
                <p class="auth__sub">{{ a('forgot.intro') }}</p>
            </div>

            <!-- `novalidate` como el resto del cajón: quien valida es el servidor, con las mismas
                 reglas para las dos superficies. La del navegador daría un tercer juego de mensajes. -->
            <form class="form auth__form" novalidate @submit.prevent="submit">
                <div v-if="store.forgotError.global" class="auth__errors" role="alert">
                    <p>{{ store.forgotError.global }}</p>
                </div>

                <div class="form__field">
                    <label class="form__label" for="forgot-email">{{ a('forgot.email') }}</label>
                    <input id="forgot-email" v-model="store.form.email" type="email" autocomplete="email" required>
                    <span v-if="store.forgotError.fields.email" class="form__error">{{ store.forgotError.fields.email }}</span>
                </div>

                <button type="submit" class="btn btn--zone auth__submit" :disabled="store.busy">
                    {{ store.busy ? a('forgot.submitting') : a('forgot.submit') }}
                </button>

                <!-- ⚠️⚠️ **`back()` y no `go(LOGIN)`, y la diferencia es lo que hace que este enlace
                     no mienta desde ninguno de sus tres orígenes** (`account/navigation.js`,
                     `parentZoneFor`). Con `go(LOGIN)` siempre iría a la pantalla de entrar de la
                     cuenta — correcto si vienes de ahí, pero **falso si vienes del paso 5 del
                     embudo**: al cliente que estaba comprando lo dejaría en el área de cliente, con
                     su cesta detrás y su compra abandonada. `back()` deshace lo que hizo cada
                     origen: vuelve a entrar si venías de entrar, y **devuelve la compra donde
                     estaba** si venías de ella. -->
                <p class="auth__switch">
                    <button type="button" @click="nav.back()">{{ a('forgot.back_to_login') }}</button>
                </p>
            </form>
        </template>
    </div>
</template>
