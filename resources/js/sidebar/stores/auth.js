import { defineStore } from 'pinia';
import { runLogin } from '../login.js';
import { runRegister, CONTEXT_STANDALONE } from '../register.js';
import { runForgot } from '../forgot.js';

/**
 * El estado del paso 5 — **IDENTIFICARSE sin salir del cajón** (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Ninguna regla de auth vive aquí.** Qué credenciales valen, qué errores se enseñan y si un alta
 * consiguió sesión lo deciden `login.js` y `register.js` —módulos planos con sus casos y su paridad
 * contra el servidor (`SidebarLoginParityTest`, `SidebarRegisterParityTest`)—. Este store guarda los
 * campos, los avisos del último intento y la pestaña activa, y ofrece las dos secuencias de petición.
 *
 * ⚠️⚠️ **Lo que NO hace, y es el corte deliberado**: lo que pasa DESPUÉS de conseguir sesión —avisar a
 * Livewire, aplicar la identidad a la cesta y continuar el checkout— cruza tres dominios y se queda en
 * la raíz. El store devuelve el resultado y quien llama decide qué hacer con él.
 *
 * ▶ **Y este es el dominio que el ÁREA DE CLIENTE va a compartir** (`DECISIONES #66`): entrar y darse
 * de alta serán suyos también. Tener aquí el estado, y no en el componente del embudo, es
 * exactamente lo que esa fase necesitaba encontrarse hecho.
 */

/**
 * Los campos de los DOS formularios, en un solo objeto.
 *
 * Juntos y no en dos porque el paso es uno: al salir se limpian de una vez, y **la contraseña no
 * puede sobrevivir** a un cambio de pantalla en una tablet compartida.
 */
const emptyForm = () => ({
    email: '', password: '', remember: false,
    name: '', phone: '', accept_privacy: false, accept_terms: false, marketing: false,
    // El señuelo: un cliente legítimo lo deja vacío y el servidor finge un alta si llega relleno.
    // Y el token del anti-bot, que escribe Cloudflare por callback (`turnstile.js`), no el usuario.
    website: '', turnstile_token: '',
});

const NO_LOGIN_ERROR = () => ({ global: '', fields: {} });
const NO_REGISTER_ERROR = () => ({ summary: [], fields: {} });
const NO_FORGOT_ERROR = () => ({ global: '', fields: {} });

export const useAuthStore = defineStore('auth', {
    state: () => ({
        form: emptyForm(),

        /** Lo que enseña cada formulario del último intento (`login.js` · `register.js` · `forgot.js`). */
        loginError: NO_LOGIN_ERROR(),
        registerError: NO_REGISTER_ERROR(),
        forgotError: NO_FORGOT_ERROR(),

        /**
         * El enlace de recuperación **ya se pidió**: la pantalla pasa a «revisa tu correo».
         *
         * ⚠️ **Es lo único que separa las dos caras de esa zona, y tiene que seguir siéndolo.** El
         * servidor responde 202 exista o no la cuenta (`SEC-06`), así que cualquier condición extra
         * que se cuele aquí reconstruiría en el cliente el oráculo de enumeración que el servidor se
         * cuida de no dar. Lo explica `forgot.js` y lo vigila su `node --test`.
         */
        forgotSent: false,

        /** `true` mientras hay una petición en vuelo: el botón cambia de rótulo, como en la web. */
        busy: false,

        /** La pestaña activa del paso 5. */
        mode: 'login',

        /**
         * ¿El alta exige captcha en esta instalación? Sale de `GET /config` (`turnstile_site_key`).
         *
         * ⚠️ **No nulo ⟺ el anti-bot está ACTIVO**, y esa equivalencia costó un arreglo del contrato:
         * el endpoint publicaba la clave pública aunque faltara la secreta, estado en el que la web
         * **no pinta el widget** y el servidor no verifica nada. Hoy el campo es el bit que decide.
         */
        signupSiteKey: '',
    }),

    actions: {
        /**
         * Cambia de pestaña.
         *
         * Los avisos son de un intento que ya no se ve: arrastrarlos entre pestañas confunde.
         */
        setMode(mode) {
            this.mode = mode === 'register' ? 'register' : 'login';
            this.loginError = NO_LOGIN_ERROR();
            this.registerError = NO_REGISTER_ERROR();
        },

        setSignupSiteKey(key) {
            this.signupSiteKey = key || '';
        },

        /**
         * Deja los TRES formularios en blanco. La contraseña no se queda en memoria de más.
         *
         * ⚠️ **`forgotSent` también se limpia, y no es simetría gratuita**: es lo que impide que quien
         * abra la pantalla de recuperar se encuentre el «revisa tu correo» de la visita anterior —o
         * del cliente anterior, en una tablet compartida— sin haber pedido nada.
         */
        reset() {
            this.form = emptyForm();
            this.loginError = NO_LOGIN_ERROR();
            this.registerError = NO_REGISTER_ERROR();
            this.forgotError = NO_FORGOT_ERROR();
            this.forgotSent = false;
        },

        /**
         * Vacía el token del anti-bot, que es la señal de «pide otro» para el widget
         * (`RegisterForm` lo observa).
         *
         * ⚠️ Va en CUALQUIER fallo del alta, no solo en el del captcha: el token es de un solo uso y
         * el servidor lo quema ANTES de comprobar si el correo ya existe, así que un segundo intento
         * con el mismo token daría «no eres un robot» con el tick verde puesto. Afinar más es
         * imposible: los tres desenlaces llegan como `validation_failed` bajo `fields.email`.
         */
        clearCaptchaToken() {
            this.form.turnstile_token = '';
        },

        /**
         * Envía las credenciales. Devuelve el resultado tal cual lo compone `login.js`.
         *
         * ⚠️ **La guarda de reentrada vive aquí y no en el botón**: `disabled` es presentación y un
         * `Enter` repetido no pasa por él.
         */
        async login({ api, messages, auth }) {
            if (this.busy) {
                return { ok: false, skipped: true };
            }

            this.busy = true;

            try {
                const result = await runLogin({ credentials: this.form, api, messages, auth });
                this.loginError = result.errors;

                return result;
            } finally {
                this.busy = false;
            }
        },

        /**
         * Crea la cuenta. Devuelve el resultado tal cual lo compone `register.js`.
         *
         * ⚠️ **El 201 NO dice si hubo cuenta**, y por eso `runRegister()` pregunta después por
         * `GET /me`: la respuesta del alta es idéntica para un alta real y para un señuelo que actuó
         * —si no lo fuera, un bot distinguiría las dos de un vistazo—.
         *
         * ⚠️⚠️ **`context` lo decide QUIEN LLAMA, y por eso viaja como parámetro** (§4.3 de
         * `specs/auth-en-cajon.md`): el embudo manda `purchase` —pay-first: sin correo de verificación
         * y con sesión— y el área de cliente, `standalone`. El store no puede elegirlo, porque no sabe
         * desde qué pantalla se está pintando el formulario: es exactamente la misma forma. Sin
         * parámetro se queda el conservador, que es el del servidor.
         */
        async register({ api, messages, auth, context = CONTEXT_STANDALONE }) {
            if (this.busy) {
                return { ok: false, skipped: true };
            }

            this.busy = true;

            try {
                const result = await runRegister({ form: this.form, api, messages, auth, context });
                this.registerError = result.errors;

                if (! result.ok) {
                    this.clearCaptchaToken();
                }

                return result;
            } finally {
                this.busy = false;
            }
        },

        /**
         * Pide el enlace de recuperación. Devuelve el resultado tal cual lo compone `forgot.js`.
         *
         * ⚠️ **La guarda de reentrada es la MISMA `busy` que los otros dos**, y eso es deliberado: los
         * tres formularios comparten pantalla dentro de la sección de cuenta, y dos peticiones de auth
         * a la vez desde el mismo cajón no es un estado que nadie quiera razonar. Vive aquí y no en el
         * botón porque `disabled` es presentación y un `Enter` repetido no pasa por él.
         */
        async requestPasswordLink({ api, messages, auth }) {
            if (this.busy) {
                return { ok: false, skipped: true };
            }

            this.busy = true;

            try {
                const result = await runForgot({ email: this.form.email, api, messages, auth });

                this.forgotError = result.errors;
                this.forgotSent = result.sent;

                return result;
            } finally {
                this.busy = false;
            }
        },
    },
});
