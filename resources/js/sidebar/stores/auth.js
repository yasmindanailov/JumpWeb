import { defineStore } from 'pinia';
import { runLogin } from '../login.js';
import { runRegister, CONTEXT_STANDALONE } from '../register.js';
import { runForgot } from '../forgot.js';
import { MAX_RESENDS, RESEND_COOLDOWN_SECONDS, nextSecond, resendGate } from '../account/verify.js';
import { ZONES } from '../account/navigation.js';
import { useAccountStore } from './account.js';
import { useSectionStore } from './section.js';
import { useWaiverStore } from './waiver.js';

/**
 * El estado del paso 5 — **IDENTIFICARSE sin salir del cajón** (reorganización del SPA, 2026-08-22).
 *
 * ⚠️ **Ninguna regla de auth vive aquí.** Qué credenciales valen, qué errores se enseñan y si un alta
 * consiguió sesión lo deciden `login.js`, `register.js` y `forgot.js` —módulos planos con sus casos de
 * `node --test`—; y los literales que el servidor manda los fijan `Api\V1\AuthSessionTest` y
 * `Api\V1\AuthRegistrationTest`. ⚠️ Hasta el 2026-08-23 los comparaban además dos paridades de árbol
 * contra el modal de Livewire, que se retiró con él (`DECISIONES #122`). Este store guarda los campos,
 * los avisos del último intento y la pestaña activa, y ofrece las secuencias de petición.
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
    // Fase 6 · waiver: la casilla SEPARADA y desmarcada por defecto. El id del texto NO vive aquí:
    // lo pone `register.js` a partir del documento que el store del waiver tiene en memoria.
    accept_waiver: false,
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

        /**
         * El correo del alta que está **esperando verificación**, y la pantalla que lo pinta.
         *
         * ⚠️ **Existe porque el alta SUELTA no abre sesión** (§4.3): tras ella no hay a dónde navegar,
         * así que el cajón se queda en «revisa tu correo» — y para reenviar hace falta el correo, que
         * es lo único que pide `POST /auth/email/resend`. En el embudo no hacía falta: allí el alta es
         * pay-first, abre sesión y la compra sigue.
         *
         * ⚠️⚠️ **Es PII y se limpia con los avisos**, no solo en `reset()`: dejarlo escrito en pantalla
         * es enseñarle el correo del cliente anterior al siguiente que use una tablet compartida. Es
         * la defensa que en la web hacía `$store.auth.completed` con su recarga.
         */
        pendingEmail: '',

        /** Reenvíos que quedan desde esta pantalla, y segundos hasta poder pulsar (`account/verify.js`). */
        resendsLeft: 0,
        resendSeconds: 0,

        /**
         * Pestillo de {@see allowVerificationResend()} (`#327`): el área de cuenta arma el contador
         * UNA vez. Sin él, entrar y salir del índice devolvería los reenvíos gastados.
         */
        resendArmed: false,

        /**
         * El temporizador de la cuenta atrás.
         *
         * ⚠️ **Vive en el store y no en el componente, y no es estilo: es el techo de componentes
         * haciendo su trabajo.** Con el reloj arriba, la pantalla del alta pasaba de 40 líneas de
         * código y `SidebarComponentBudgetTest` la paró (`DECISIONES #120(r)`: cuando aprieta, la
         * pregunta es qué sobra ahí, no cuánto subir el techo). Lo que sobraba era la SECUENCIA —
         * arrancar, descontar, parar—, que además así se prueba con `node --test` en vez de montar un
         * componente.
         */
        resendTicker: null,

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

    getters: {
        /**
         * **¿Hay un alta esperando que el titular abra su correo?**
         *
         * ⚠️ Lo consume el ARMAZÓN del área para no pintar la barra de pestañas mientras esa pantalla
         * está delante (`sections/AccountSection.vue`), y esa es toda su razón de ser: ofrecer
         * «Entrar / Crear cuenta» junto a un «acabas de crear tu cuenta, revisa tu correo» invita a
         * abandonar un paso a medias — y **pulsarlo destruía la pantalla**, porque salir de la zona
         * borra el correo pendiente a propósito (es PII de un cliente que puede no ser el siguiente
         * en usar el dispositivo).
         *
         * ▶ Esa defensa NO se toca: lo que se retira es la forma ACCIDENTAL de dispararla. La salida
         * deliberada sigue existiendo, dentro de la propia pantalla («¿ya tienes cuenta?»).
         */
        awaitingVerification: (state) => state.pendingEmail !== '',
    },
    actions: {
        /**
         * Cambia de pestaña.
         *
         * Los avisos son de un intento que ya no se ve: arrastrarlos entre pestañas confunde.
         */
        setMode(mode) {
            this.mode = mode === 'register' ? 'register' : 'login';
            this.clearNotices();
        },

        /**
         * Borra los avisos del intento anterior **sin tocar los campos**.
         *
         * ⚠️ Es lo que pide una zona de auth al montarse, y la diferencia con `reset()` importa: en el
         * área de cliente las tres pantallas comparten formulario, así que quien escribe su correo,
         * pulsa «he olvidado mi contraseña» y vuelve **no tiene que escribirlo otra vez**. Lo que no
         * puede sobrevivir a un cambio de pantalla es un aviso —que describiría un intento que ya no
         * se ve— ni el «ya te hemos enviado el enlace».
         */
        clearNotices() {
            this.loginError = NO_LOGIN_ERROR();
            this.registerError = NO_REGISTER_ERROR();
            this.forgotError = NO_FORGOT_ERROR();
            this.forgotSent = false;
            this.pendingEmail = '';
            this.resendsLeft = 0;
            this.resendSeconds = 0;
            this.resendArmed = false;
        },

        /**
         * Deja la pantalla de «revisa tu correo» lista para ese correo.
         *
         * ⚠️ **Nace ESPERANDO, con la cuenta atrás llena**, y no es un detalle de presentación: el alta
         * que acaba de ocurrir ya ha gastado el limitador por IP del servidor, así que ofrecer el botón
         * al llegar sería ofrecer un no-op —el servidor descartaría el reenvío y contestaría 202
         * igual—. El porqué completo, en `account/verify.js`.
         */
        awaitVerification(email) {
            this.pendingEmail = email || '';
            this.resendsLeft = MAX_RESENDS;
            this.resendSeconds = RESEND_COOLDOWN_SECONDS;
        },

        /**
         * `#327` — deja el reenvío listo en el ÁREA DE CUENTA, para quien entró sin verificar y tiene
         * la exención aceptada esperando a ese correo.
         *
         * ⚠️ **Nace LISTO, no esperando, y ésa es la diferencia con {@see awaitVerification()}**: allí
         * la cuenta atrás arranca llena porque el alta acaba de gastar el limitador por IP del
         * servidor; aquí el cliente puede llegar horas después y ofrecerle un botón bloqueado 60 s
         * sería inventarle una espera que el servidor no impone.
         *
         * ⚠️⚠️ **No RELLENA el cupo**: sin el pestillo, salir del índice y volver a entrar devolvería
         * los reenvíos gastados y el tope de 4 dejaría de existir — bastaría con navegar en círculos
         * para bombardear un buzón. El pestillo se suelta con el resto de avisos.
         */
        allowVerificationResend() {
            if (this.resendArmed) {
                return;
            }

            this.resendArmed = true;
            this.resendsLeft = MAX_RESENDS;
            this.resendSeconds = 0;
        },

        /** Descuenta un segundo de la cuenta atrás. La regla —el suelo en cero— vive en el módulo. */
        tickResend() {
            this.resendSeconds = nextSecond(this.resendSeconds);
        },

        /**
         * Arranca (o reinicia) la cuenta atrás.
         *
         * ⚠️ **Siempre para la anterior primero.** Sin eso, pulsar «reenviar» dejaría dos relojes
         * corriendo sobre el mismo número y la espera se consumiría al doble de velocidad — un botón
         * que se ofrece antes de tiempo, que es justo lo que el servidor va a descartar en silencio.
         */
        startResendCountdown() {
            this.stopResendCountdown();
            this.resendTicker = setInterval(() => this.tickResend(), 1000);
        },

        /** Para el reloj. Lo llama la pantalla al desmontarse: un temporizador huérfano no muere solo. */
        stopResendCountdown() {
            if (this.resendTicker !== null) {
                clearInterval(this.resendTicker);
                this.resendTicker = null;
            }
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
            this.clearNotices();
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
                // El texto del waiver que el formulario tiene en memoria (o `null`): con él viaja el
                // id que el servidor sirvió, y sin él la casilla no manda nada (`register.js`).
                const result = await runRegister({ form: this.form, api, messages, auth, context, waiver: useWaiverStore().document });
                this.registerError = result.errors;

                if (! result.ok) {
                    this.clearCaptchaToken();
                }

                // El servidor rechazó el texto del waiver (caducó, o el modo cambió): «vuelve a leerlo»
                // solo se puede cumplir si se RELEE — el texto plegado era de una sola lectura y cada
                // reenvío mandaba el mismo id (revisión `#169` §10.3, CAJ-2). Y la casilla se desmarca:
                // lo que se leyó ya no es lo que se firma.
                // CAJ-422 (`#181`): el 422 de `accept_waiver` (obligatoria en interno) también relee — si el
                // formulario cacheó `document: null` (montado antes de publicarse la versión), la casilla ni existe.
                if (result.errors?.fields?.waiver_document_id || result.errors?.fields?.accept_waiver) {
                    this.form.accept_waiver = false;
                    await useWaiverStore().reloadLegal({ api });
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
        /**
         * **Lleva a RECUPERAR la contraseña sin salir del cajón** (`specs/auth-en-cajon.md` §3.4).
         *
         * ⚠️ **Cierra un atasco real**: hasta hoy, quien estaba comprando y no recordaba su
         * contraseña **tenía que abandonar el cajón** —el enlace era una rama `@unless ($embedded)`
         * que dentro de la compra no se pintaba—, y con él perdía de vista su cesta.
         *
         * ⚠️⚠️ **Entra SIN sembrar nada debajo, y eso es lo que hace que «volver» funcione.** De donde
         * viene el cliente —el paso 5 del embudo— **no es una zona**, así que no hay ninguna a la que
         * volver: con la pila vacía, `back()` sale de la sección y la compra reaparece donde estaba,
         * con su cesta. Sembrar `LOGIN` aquí —que es lo correcto cuando se llega por una PUERTA— le
         * dejaría en el área de cliente. La regla, con sus tres casos, en `parentZoneFor()`.
         *
         * ▶ **Y vive en el STORE y no en el componente del embudo por dos razones**, las dos medidas:
         * `sections/PurchaseSection.vue` está clavado en su presupuesto de 431 líneas y los tres
         * `import` que esto necesita lo habrían roto; y, sobre todo, **el embudo no tiene por qué
         * saber de zonas de cuenta** — enseñarle ese vocabulario es volver a mezclar los dos dominios
         * que `DECISIONES #119` separó.
         */
        startPasswordRecovery() {
            this.clearNotices();
            useAccountStore().enter(ZONES.FORGOT);
            useSectionStore().showAccount();
        },

        /**
         * **El alta SUELTA, con su desenlace.** La usa el área de cliente; el embudo llama a
         * `register()` con su propio contexto.
         *
         * ⚠️⚠️ **La secuencia vive aquí y no en la pantalla**, y es la misma razón por la que el reloj
         * está en el store: son cuatro decisiones encadenadas —qué contexto, si hubo sesión, qué
         * limpiar y qué dejar en pantalla— y encadenarlas dentro de un `.vue` las deja sin red, porque
         * los componentes se comparan por su ÁRBOL y un árbol no dice qué se llamó ni en qué orden.
         *
         * ⚠️ **El correo se captura ANTES de llamar**: `reset()` vacía el formulario —la contraseña no
         * puede sobrevivir a un cambio de pantalla— y sin esa copia no quedaría a quién reenviarle.
         *
         * ⚠️⚠️ **Y el desenlace es el MISMO para un alta buena y para un señuelo que actuó.** El 201
         * del servidor es idéntico en los dos casos y `runRegister()` lo desempata preguntando por
         * `GET /me`; sin sesión se enseña «revisa tu correo», que es exactamente lo que hace la web.
         * Cualquier rama extra aquí delataría el señuelo.
         */
        async registerStandalone({ api, messages, auth }) {
            const email = this.form.email;
            const result = await this.register({ api, messages, auth, context: CONTEXT_STANDALONE });

            if (! result.ok || result.identified) {
                return result;
            }

            this.reset();
            this.awaitVerification(email);
            this.startResendCountdown();

            return result;
        },

        /**
         * Reenvía el correo de verificación.
         *
         * ⚠️⚠️ **Se descuenta el reenvío PASE LO QUE PASE, y se reinicia la espera igual.** Es lo que
         * hace `Register::resend()` en la web y por el mismo motivo: el endpoint responde **202
         * siempre** —no dice si envió— así que condicionar el descuento a «que haya ido bien» dejaría
         * al cliente insistiendo sin tope sobre un servidor que ya lo está descartando. Con un fallo de
         * red pasa lo mismo: lo que se protege es el buzón, no el contador.
         *
         * ⚠️ La guarda de reentrada es la del propio `gate`: si no se puede reenviar, no se pide.
         */
        async resendVerification({ api }) {
            // ⚠️ **Los campos se pasan por NOMBRE y no `resendGate(this)`**, aunque tiente: el store
            // los llama `resendSeconds`/`resendsLeft` y el módulo espera `secondsLeft`/`resendsLeft`.
            // Pasarle el store entero **no falla** —`secondsLeft` llega `undefined`, cae al default y
            // la puerta ignora la cuenta atrás—, así que el botón se ofrecería siempre y el servidor
            // descartaría el reenvío en silencio. Lo cazaron los casos de este store, no el ojo.
            if (! resendGate({ secondsLeft: this.resendSeconds, resendsLeft: this.resendsLeft }).canResend) {
                return { ok: false, skipped: true };
            }

            this.resendsLeft -= 1;
            this.resendSeconds = RESEND_COOLDOWN_SECONDS;
            this.startResendCountdown();

            // `#327` — **dos endpoints, y los elige el hecho de tener o no el correo delante.** Tras
            // el alta suelta no hay sesión y el correo es lo único que identifica (`pendingEmail`);
            // desde el área de cuenta hay sesión y no hay correo — el contexto de cuenta no lo publica
            // y no debería, así que el que sabe quién pregunta es el servidor.
            const response = this.pendingEmail !== ''
                ? await api.post('/auth/email/resend', { email: this.pendingEmail })
                : await api.post('/me/email/resend', {});

            return { ok: response.ok === true, response };
        },

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
