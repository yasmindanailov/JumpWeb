<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import { t as translate, tp as translateWith } from '../i18n.js';
import { CODE_LENGTH, codeDigits, formatWait } from '../code-input.js';
import GoogleButton from './GoogleButton.vue';

// El `CodeInput` baja con SU cara y no con el motor, que se descarga entero en la primera apertura
// (`SidebarBundleBudgetTest`): se pinta tras «Continuar», que ya es una ida y vuelta al servidor.
const CodeInput = defineAsyncComponent(() => import('./CodeInput.vue'));

/**
 * **LA PUERTA del cajón, con un código al correo** (A4a de `docs/specs/acceso-con-codigo.md` §4.11, `#848`/`#849`): la
 * pintan el paso 5 de la compra y la zona de entrar de Mi cuenta, y sustituye a las dos pestañas de entrar y crear cuenta.
 * **Pinta y recoge; no decide nada**: a dónde lleva el correo, el «no» y la espera de «Reenviar el código» son de
 * `login.js` y `stores/auth.js`, con su `node --test`. La tercera cara —el alta, si el correo es nuevo— es `RegisterForm`.
 *
 * ⚠️ **Dos caras en el MISMO sitio, sin pestañas** (`#849`): el correo y «Continuar»; con cuenta, «Te hemos enviado un
 * código de 6 cifras a …» con «Cambiar el correo» al lado, el `CodeInput` del diseño (`#861`: seis casillas, se comprueba
 * solo con la sexta, la pista, «Reenviar el código» con su espera), «Mantener la sesión iniciada en este dispositivo» SIN
 * marcar (`#858`) y «Entrar», apagado hasta tener las seis. Los textos del código, los del diseño; los de la puerta, los de
 * la isla que el owner ya vio (A3a, `#857`: «Continuar», porque un correo nuevo no recibe código, `#849`).
 *
 * ⚠️ Google va ARRIBA con su «o» (T8·d, `#350`), y solo en la primera cara: en la del código el correo ya está elegido.
 * ⚠️ «¿Querías decir…?» al salir del campo (`ui/correo.js`, la regla de todo el sistema): el correo de la puerta es el de la
 * cuenta que nace si es nuevo, y una errata la dejaría sin el código de la próxima vez (`acceso-con-codigo.md` §4.4).
 * La regla baja con `import()` AL SALIR del campo y no con el motor: son 1,45 KiB que solo sirven entonces, y el motor se
 * descarga entero en la primera apertura (`SidebarBundleBudgetTest`). Si al llegar el correo ya es otro, no sugiere nada.
 */
const props = defineProps({
    /** La cara: `email` o `code` (`stores/auth.js::STAGE_*`). */
    stage: { type: String, default: 'email' },
    /** Avisos del intento anterior: `{global, fields: {email, code}}` (`login.js`). */
    errors: { type: Object, default: () => ({ global: '', fields: {} }) },
    /** `true` mientras la petición está en vuelo: cambia el rótulo del botón. */
    submitting: { type: Boolean, default: false },
    /** El grupo `account`: los rótulos de `login` y los dos del botón de Google (`register`). */
    account: { type: Object, default: () => ({}) },
    /** La ida a Google. Vacía = esta instalación no la ofrece, y no se pinta nada (ni el «o»). */
    googleUrl: { type: String, default: '' },
    /** El correo al que se mandó el código, y si ya fue OTRO. */
    sentTo: { type: String, default: '' },
    resent: { type: Boolean, default: false },
    /** Segundos hasta poder pedir otro código (el servidor admite uno por minuto). */
    wait: { type: Number, default: 0 },
});

const emit = defineEmits(['continue', 'enter', 'resend', 'change-email']);

const email = defineModel('email', { type: String, default: '' });
const code = defineModel('code', { type: String, default: '' });
const remember = defineModel('remember', { type: Boolean, default: false });

const a = (key) => translate(props.account, key);
const fieldErrors = computed(() => props.errors?.fields ?? {});
const suggestion = ref(null);
// «Entrar» se apaga hasta tener las seis (el foco al campo lo pone el `CodeInput` al montarse, en la cara del código).
const complete = computed(() => codeDigits(code.value).length === CODE_LENGTH);

// Los dos eventos, por su NOMBRE (y no calculado): `SidebarEmitWiringTest` comprueba que cada evento declarado se emite.
const submit = () => (props.stage === 'code' ? emit('enter') : emit('continue'));
const suggest = async () => {
    const typed = String(email.value ?? '');
    const { sugerirCorreo } = await import('../../ui/correo.js');
    if (String(email.value ?? '') === typed) suggestion.value = sugerirCorreo(typed);
};
const accept = () => { email.value = suggestion.value; suggestion.value = null; };
</script>

<template>
    <div class="auth">
        <div class="auth__head">
            <h2 class="auth__title">{{ a('login.title') }}</h2>
            <p v-if="stage !== 'code'" class="auth__sub">{{ a('login.intro') }}</p>
        </div>

        <GoogleButton v-if="stage !== 'code'" :href="googleUrl" :label="a('register.google_cta')" :separator="a('register.or')" />

        <!-- `novalidate` como el resto del cajón: quien valida es el servidor. -->
        <form class="form auth__form" novalidate @submit.prevent="submit">
            <!-- El limitador y el corte de red, aquí; el código mal escrito, bajo su campo (`login.js`). -->
            <div v-if="errors.global" class="auth__errors" role="alert">
                <p>{{ errors.global }}</p>
            </div>

            <template v-if="stage === 'code'">
                <!-- ⚠️ El correo como TEXTO, nunca `v-html`: lo escribió el cliente. Y «Cambiar el correo» a su lado, como en
                     el alta y en el diseño; fuera de la región que se anuncia, que solo dice a dónde fue el código. -->
                <p class="auth__sent">
                    <span role="status">{{ translateWith(account, resent ? 'login.code_resent' : 'login.code_sent', { email: sentTo }) }}</span>
                    <button type="button" class="auth__link" @click="$emit('change-email')">{{ a('login.change_email') }}</button>
                </p>

                <!-- El `CodeInput` del diseño (`#861`): con la sexta cifra se comprueba solo; «Reenviar el código», dentro. -->
                <CodeInput id="login-code" v-model="code" :label="a('login.code')" :hint="a('login.code_hint')"
                           :error="fieldErrors.code ?? ''" :disabled="submitting" :wait="wait"
                           :wait-label="translateWith(account, 'login.code_again_in', { t: formatWait(wait) })"
                           :resend-label="a('login.code_again')" @complete="$emit('enter')" @resend="$emit('resend')" />

                <div class="auth__row">
                    <label class="check check--opt">
                        <input v-model="remember" type="checkbox">
                        <span>{{ a('login.remember') }}</span>
                    </label>
                </div>

                <button type="submit" class="btn btn--ink auth__submit" :disabled="submitting || ! complete">
                    <span v-show="! submitting">{{ a('login.submit') }}</span>
                    <span v-show="submitting" class="btn__loading">
                        <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('login.submitting') }}
                    </span>
                </button>
            </template>

            <template v-else>
                <div class="form__field">
                    <label class="form__label" for="login-email">{{ a('login.email') }}</label>
                    <input id="login-email" v-model="email" type="email" autocomplete="email" required
                           @blur="suggest" @input="suggestion = null">
                    <button v-if="suggestion" type="button" class="auth__link" @click="accept">
                        {{ translateWith(account, 'login.suggest', { email: suggestion }) }}
                    </button>
                    <span v-if="fieldErrors.email" class="form__error">{{ fieldErrors.email }}</span>
                </div>

                <button type="submit" class="btn btn--ink auth__submit" :disabled="submitting">
                    <span v-show="! submitting">{{ a('login.continue') }}</span>
                    <span v-show="submitting" class="btn__loading">
                        <span class="jj-spinner jj-spinner--xs" aria-hidden="true"></span> {{ a('login.sending') }}
                    </span>
                </button>
            </template>
        </form>
    </div>
</template>
