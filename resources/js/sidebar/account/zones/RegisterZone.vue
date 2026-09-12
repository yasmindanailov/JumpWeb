<script setup>
import { onUnmounted, computed } from 'vue';
import { useAuthStore } from '../../stores/auth.js';
import { useAccountStore } from '../../stores/account.js';
import { ZONES } from '../navigation.js';
import { resendGate } from '../verify.js';
import { landOnAccount } from '../after-auth.js';
import { api } from '../../api.js';
import { t as translate, tp as translateWith } from '../../i18n.js';
import RegisterForm from '../../steps/RegisterForm.vue';

/**
 * **CREAR CUENTA dentro del cajón, fuera de la compra** (`specs/auth-en-cajon.md` §4.3).
 *
 * ⚠️⚠️ **Manda `context: standalone`, y ahí está toda la diferencia con el paso 5 del embudo.** Con él
 * el servidor **envía el correo de verificación y NO abre sesión**; con `purchase` haría lo contrario,
 * porque allí el pago sustituye a la verificación —un bot no paga—. Reutilizar el formulario del
 * embudo sin decir el contexto habría convertido esta alta en *pay-first* sin que nadie lo decidiera.
 *
 * ⚠️ **Sin sesión no hay a dónde aterrizar**, así que esta zona tiene DOS caras: el formulario y
 * «revisa tu correo». Es la única de las tres de auth que no acaba navegando.
 *
 * ⚠️⚠️ **Y la segunda cara es la MISMA para un alta buena y para un señuelo que actuó.** El 201 del
 * servidor es idéntico en los dos casos y `runRegister()` lo desempata preguntando por `GET /me`; si
 * no hay sesión, se enseña esta pantalla — exactamente lo que hace la web—. Un texto distinto aquí
 * delataría el señuelo y lo dejaría sin servir para lo único que sirve.
 */
const props = defineProps({
    /** El grupo `account`, podado: los rótulos del formulario y de «revisa tu correo». */
    account: { type: Object, default: () => ({}) },
    /** El grupo `tickets`: el aviso genérico de «inténtalo más tarde». */
    messages: { type: Object, default: () => ({}) },
    /** El grupo `auth`: los literales del «no» del servidor. */
    auth: { type: Object, default: () => ({}) },
    /** Las rutas del servidor. Solo hacen falta si el alta acabara con sesión (no es el caso normal). */
    urls: { type: Object, default: () => ({}) },
});

const store = useAuthStore();
const nav = useAccountStore();

store.clearNotices();

const a = (key) => translate(props.account, key);
// ⚠️ Por NOMBRE, no `resendGate(store)`: el store los llama `resendSeconds`/`resendsLeft` y el módulo
// espera `secondsLeft`/`resendsLeft`. Pasarle el store entero no falla — la puerta se queda sin la
// cuenta atrás y ofrece el botón siempre, que es lo que el servidor descarta en silencio.
const gate = computed(() => resendGate({ secondsLeft: store.resendSeconds, resendsLeft: store.resendsLeft }));

// Un temporizador huérfano no muere solo: si la pantalla se va, el reloj también.
onUnmounted(() => store.stopResendCountdown());

/**
 * ⚠️ **La secuencia del alta NO está aquí, y eso lo decidió un presupuesto.** Con el reloj y el
 * desenlace dentro, este componente llegó a **43 de 40** líneas de código y
 * `SidebarComponentBudgetTest` lo paró. La respuesta correcta no era subir el techo (`#120(r)`): era
 * que la secuencia —contexto, sesión sí o no, qué limpiar, qué dejar en pantalla— viva en el store,
 * donde se prueba con `node --test`. Aquí queda lo único que es de esta pantalla: a dónde ir si el
 * alta acabó con sesión, que no es el camino normal del alta suelta.
 */
async function submit() {
    const result = await store.registerStandalone({ api, messages: props.messages, auth: props.auth });

    if (result.ok && result.identified) landOnAccount({ urls: props.urls });
}
</script>

<template>
    <!-- La segunda cara: «revisa tu correo». `role="status"` lo anuncia un lector de pantalla sin
         robar el foco, igual que el paso 7 del embudo. -->
    <div v-if="store.pendingEmail" class="auth">
        <div class="auth__head">
            <h2 class="auth__title">{{ a('verify.title') }}</h2>
        </div>

        <!-- ⚠️ **El correo se pinta como TEXTO, no con `v-html`**, y es lo único que separa esta línea
             de la de la web: allí el Blade la emite con `{!! !!}` para poner el correo en `<strong>`,
             escapándolo antes con `e()`. Aquí el valor lo escribió el usuario y llega sin pasar por
             el servidor, así que `v-html` sería un XSS de manual. El énfasis no vale eso. -->
        <p class="auth__sent" role="status">{{ translateWith(account, 'verify.sent_to', { email: store.pendingEmail }) }}</p>
        <p class="auth__sub">{{ a('verify.spam_hint') }}</p>

        <template v-if="! gate.exhausted">
            <button type="button" class="btn btn--ink auth__submit" :disabled="! gate.canResend"
                    @click="store.resendVerification({ api })">
                <span v-if="gate.waiting">{{ a('verify.resend_in') }} {{ store.resendSeconds }}s</span>
                <span v-else>{{ a('verify.resend') }}</span>
            </button>
            <p class="form__hint">{{ translateWith(account, 'verify.resends_left', { n: store.resendsLeft }) }}</p>
        </template>

        <p v-else class="auth__sub">{{ a('verify.resend_limit') }}</p>

        <!-- El escape. Sin él, quien ya tenía cuenta se queda en una pantalla sin salida: la página
             de verificación exige sesión, y el alta suelta no la abre. -->
        <p class="auth__switch">
            {{ a('verify.already_have_account') }}
            <button type="button" @click="nav.go(ZONES.LOGIN)">{{ a('login.cta') }}</button>
        </p>
    </div>

    <RegisterForm
        v-else
        v-model:name="store.form.name"
        v-model:email="store.form.email"
        v-model:phone="store.form.phone"
        v-model:password="store.form.password"
        v-model:accept-waiver="store.form.accept_waiver"
        v-model:website="store.form.website"
        v-model:turnstile-token="store.form.turnstile_token"
        :turnstile-site-key="store.signupSiteKey"
        :errors="store.registerError"
        :submitting="store.busy"
        :account="account"
        :google-url="urls.google ?? ''"
        :privacy-url="urls.privacy ?? ''"
        @submit="submit" />
</template>
