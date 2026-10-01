import { test, describe, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore, STAGE_CODE, STAGE_EMAIL, STAGE_REGISTER } from './auth.js';
import { useWaiverStore } from './waiver.js';
import { MAX_RESENDS, RESEND_COOLDOWN_SECONDS, resendGate } from '../account/verify.js';

/**
 * La red del store de IDENTIFICARSE: la PUERTA con código (A4a de `acceso-con-codigo.md` §4.11), el alta y sus reenvíos.
 *
 * ⚠️ **Se dobla solo la frontera HTTP.** `login.js` y `register.js` corren de verdad —ya tienen sus
 * casos y su paridad contra el servidor—, así que esto prueba el store Y su costura con ellos, que es
 * justo lo que un doble de módulo habría dejado sin comprobar.
 */
const MENSAJES = { errors: { try_later: 'Espera un minuto.' } };

/** Los textos del cajón que la puerta pinta en sus «no». */
const CUENTA = { login: {
    code_wrong: 'El código no es correcto o ha caducado. Pide otro.',
    code_wait: 'Espera :n segundos para pedir otro código.',
} };

const TEXTOS = { messages: MENSAJES, auth: {}, account: CUENTA };

/**
 * ⚠️ **El último store creado se guarda para poder pararle el reloj pase lo que pase.**
 *
 * Desde que la pantalla de verificación tiene cuenta atrás, un caso que falle ANTES de llamar a
 * `stopResendCountdown()` deja un `setInterval` vivo — y con un temporizador abierto **`node --test`
 * no termina**: el runner se queda colgado y el fallo real ni se llega a leer. Pasó al escribir estos
 * casos. Con `afterEach` no puede volver a pasar.
 */
let ultimoStore = null;

function store() {
    setActivePinia(createPinia());
    ultimoStore = useAuthStore();

    return ultimoStore;
}

afterEach(() => {
    ultimoStore?.stopResendCountdown?.();
    ultimoStore = null;
});

/** Un `api` de mentira que responde lo que se le diga y cuenta lo que le piden. */
function fakeApi(respuestas) {
    const llamadas = [];

    return {
        llamadas,
        post: async (url, body) => {
            llamadas.push({ url, body });

            return respuestas[url] ?? { ok: false, status: 500, data: null };
        },
        get: async (url) => {
            llamadas.push({ url });

            return respuestas[url] ?? { ok: false, status: 500, data: null };
        },
    };
}

describe('el store de identificarse', () => {
    test('arranca en la primera cara de la puerta, sin avisos y con el formulario en blanco', () => {
        const a = store();

        assert.equal(a.stage, STAGE_EMAIL);
        assert.equal(a.busy, false);
        assert.equal(a.form.email, '');
        assert.equal(a.form.code, '');
        assert.equal(a.form.remember, false, 'la casilla de recordar nace SIN marcar (`#858`)');
        assert.equal('password' in a.form, false, 'ningún cliente escribe ya una contraseña (A4a)');
        assert.equal(a.form.website, '', 'el señuelo empieza vacío: rellenarlo es lo que delata al bot');
        assert.deepEqual(a.loginError, { global: '', fields: {} });
    });

    /**
     * ⚠️ La guarda vive en el STORE y no en el `disabled` del botón: `disabled` es presentación, y un
     * `Enter` repetido no pasa por él. Dos «Continuar» seguidos serían dos correos con dos códigos, y el
     * primero ya no valdría.
     */
    test('no se puede lanzar una segunda petición con la primera en vuelo', async () => {
        const a = store();
        a.busy = true;

        const api = fakeApi({});

        assert.deepEqual(await a.requestCode({ api, ...TEXTOS }), { next: null, skipped: true });
        assert.deepEqual(await a.loginWithCode({ api, ...TEXTOS }), { ok: false, skipped: true });
        assert.equal(api.llamadas.length, 0, 'no debería haber salido ninguna petición');
    });

    /**
     * ⚠️ El token del anti-bot es de UN SOLO USO y el servidor lo quema **antes** de comprobar si el
     * correo ya existe. Sin vaciarlo, el segundo intento falla por «no eres un robot» aunque el
     * cliente haya corregido lo que estaba mal — y los tres desenlaces llegan indistinguibles.
     */
    test('CUALQUIER fallo del alta vacía el token del anti-bot', async () => {
        const a = store();
        a.form.turnstile_token = 'token-de-un-solo-uso';
        const api = fakeApi({ '/auth/register': { ok: false, status: 422, data: { errors: { email: ['Ya existe'] } } } });

        await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(a.form.turnstile_token, '', 'sin esto, el segundo intento falla por el captcha');
    });

    test('un alta correcta NO vacía el token: no hay segundo intento que proteger', async () => {
        const a = store();
        a.form.turnstile_token = 'token';
        const api = fakeApi({
            '/auth/register': { ok: true, status: 201, data: {} },
            '/me': { ok: true, status: 200, data: { id: 9 } },
        });

        const r = await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, true);
        assert.equal(a.form.turnstile_token, 'token');
    });

    test('reiniciar deja el formulario en blanco, código incluido, y la puerta en su primera cara', () => {
        const a = store();
        a.form.email = 'x@y.z';
        a.showCode('x@y.z', false);
        a.form.code = '482913';
        a.loginError = { global: 'algo', fields: {} };

        a.reset();

        assert.equal(a.form.code, '', 'el código no sobrevive a un cambio de pantalla');
        assert.equal(a.form.email, '');
        assert.equal(a.stage, STAGE_EMAIL);
        assert.equal(a.codeSentTo, '');
        assert.equal(a.loginError.global, '');
    });

    test('la clave del anti-bot es un BIT: vacía significa que no hay captcha', () => {
        const a = store();

        assert.equal(a.signupSiteKey, '');
        a.setSignupSiteKey('0x4AAA');
        assert.equal(a.signupSiteKey, '0x4AAA');
        a.setSignupSiteKey(null);
        assert.equal(a.signupSiteKey, '', 'nulo y vacío son lo mismo: el anti-bot está apagado');
    });
});

describe('el CONTEXTO del alta, que decide quien llama', () => {
    /**
     * ⚠️ **La costura completa, y es donde de verdad puede romperse.** `register.js` ya tiene su caso
     * del default conservador; lo que esto fija es que el store **no se coma el parámetro por el
     * camino** —que es la familia de `DECISIONES #118`: los dos extremos probados y el medio sin
     * cablear—.
     */
    test('el store PASA el contexto que le dan hasta el cuerpo de la petición', async () => {
        const a = store();
        const api = fakeApi({
            '/auth/register': { ok: true, status: 201, data: null },
            '/me': { ok: true, status: 200, data: { id: 3 } },
        });

        await a.register({ api, messages: MENSAJES, auth: {}, context: 'purchase' });

        assert.equal(api.llamadas[0].body.context, 'purchase');
    });

    test('y sin contexto se queda el conservador, nunca el que salta la verificación', async () => {
        const a = store();
        const api = fakeApi({
            '/auth/register': { ok: true, status: 201, data: null },
            '/me': { ok: false, status: 401, data: null },
        });

        await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(api.llamadas[0].body.context, 'standalone');
    });
});

describe('la PUERTA: el correo decide (A4a, `#849`)', () => {
    const CODIGO = { '/auth/code': { ok: true, status: 200, data: { next: 'code' }, error: null } };

    test('con cuenta: la cara del código, para el correo al que fue', async () => {
        const a = store();
        a.form.email = '  ana@correo.es ';
        const api = fakeApi(CODIGO);

        const r = await a.requestCode({ api, ...TEXTOS });

        assert.equal(r.next, 'code');
        assert.equal(a.stage, STAGE_CODE);
        assert.equal(a.codeSentTo, 'ana@correo.es', 'el correo recortado: es el que se pinta y al que se entra');
        assert.equal(a.codeResent, false);
        assert.deepEqual(api.llamadas[0], { url: '/auth/code', body: { email: 'ana@correo.es' } });
    });

    test('nuevo: la cara del alta, sin ningún código', async () => {
        const a = store();
        a.form.email = 'nuevo@correo.es';

        await a.requestCode({ api: fakeApi({ '/auth/code': { ok: true, status: 200, data: { next: 'register' }, error: null } }), ...TEXTOS });

        assert.equal(a.stage, STAGE_REGISTER);
        assert.equal(a.codeSentTo, '');
    });

    test('el tope del CORREO (429 con `next: code`) lleva al código SIN aviso: hay uno recién enviado', async () => {
        const a = store();
        a.form.email = 'ana@correo.es';
        const limite = { ok: false, status: 429, data: null, error: { code: 'too_many_requests', params: { retry_after: 40, next: 'code' } } };

        await a.requestCode({ api: fakeApi({ '/auth/code': limite }), ...TEXTOS });

        assert.equal(a.stage, STAGE_CODE);
        assert.deepEqual(a.loginError, { global: '', fields: {} });
    });

    test('un «no» se queda en el correo, con su aviso', async () => {
        const a = store();
        a.form.email = 'ana@correo.es';

        await a.requestCode({ api: fakeApi({}), ...TEXTOS });

        assert.equal(a.stage, STAGE_EMAIL);
        assert.equal(a.loginError.global, MENSAJES.errors.try_later);
        assert.equal(a.form.email, 'ana@correo.es', 'volver a teclear el correo tras un fallo es hostil');
    });

    /**
     * ⚠️ **Al correo al que FUE el código, no al del campo**: si la persona toca el campo después (o lo cambia otra pantalla
     * que comparte formulario), el código sigue siendo del primero, y entrar con otro correo sería un «no» sin sentido.
     */
    test('entrar manda el código al correo al que fue, y sin recordar el dispositivo si no se marcó', async () => {
        const a = store();
        a.showCode('ana@correo.es', false);
        a.form.email = 'otro@correo.es';
        a.form.code = '482 913';
        const api = fakeApi({ '/auth/login': { ok: true, status: 200, data: { id: 7 }, error: null } });

        const r = await a.loginWithCode({ api, ...TEXTOS });

        assert.equal(r.ok, true);
        assert.equal(a.busy, false, 'el indicador se apaga pase lo que pase');
        assert.deepEqual(api.llamadas[0].body, { email: 'ana@correo.es', code: '482 913', remember: false });

        a.form.remember = true;
        await a.loginWithCode({ api, ...TEXTOS });
        assert.equal(api.llamadas[1].body.remember, true, 'marcada, sí');
    });

    /**
     * ⚠️ **El `CodeInput` del diseño VACÍA el código tras un «no»** (`#861`): con la sexta cifra se comprueba solo, así que
     * dejar el malo escrito obligaría a borrar para volver a probar, y pegar el bueno encima no sustituye nada.
     */
    test('un código que no vale deja su aviso bajo el código y lo VACÍA, para escribirlo entero otra vez', async () => {
        const a = store();
        a.showCode('ana@correo.es', false);
        a.form.code = '000000';
        const no = { ok: false, status: 401, data: null, error: { code: 'invalid_credentials', message: 'otra cosa' } };

        const r = await a.loginWithCode({ api: fakeApi({ '/auth/login': no }), ...TEXTOS });

        assert.equal(r.ok, false);
        assert.deepEqual(a.loginError.fields, { code: CUENTA.login.code_wrong });
        assert.equal(a.form.code, '');
        assert.equal(a.stage, STAGE_CODE);
    });

    test('un corte de red NO vacía el código: ese código puede seguir valiendo', async () => {
        const a = store();
        a.showCode('ana@correo.es', false);
        a.form.code = '482913';

        const r = await a.loginWithCode({ api: fakeApi({}), ...TEXTOS });

        assert.equal(r.ok, false);
        assert.equal(a.loginError.global, MENSAJES.errors.try_later);
        assert.equal(a.form.code, '482913', 'volver a pulsar «Entrar» tiene que bastar');
    });

    test('«Pedir otro código», siempre a mano (como la isla, `#812`): al MISMO correo, y dice «otro»', async () => {
        const a = store();
        a.showCode('ana@correo.es', false);
        a.form.email = 'cambiado@correo.es';
        a.form.code = '48';
        const api = fakeApi(CODIGO);

        await a.resendCode({ api, ...TEXTOS });

        assert.deepEqual(api.llamadas[0].body, { email: 'ana@correo.es' });
        assert.equal(a.codeResent, true);
        assert.equal(a.form.code, '', 'el código anterior ya no vale: el campo, vacío');
    });

    /**
     * ⚠️⚠️ **Antes del minuto NO ha salido ninguno**, y el servidor lo dice con un `429` que trae `next: code`. Con la regla de
     * la PUERTA eso es «ve a escribirlo»; aquí sería decir «te hemos enviado otro» sin haberlo enviado.
     */
    test('pedirlo antes del minuto NO dice «otro»: dice cuánto esperar, bajo el código, y deja lo escrito', async () => {
        const a = store();
        a.showCode('ana@correo.es', false);
        a.form.code = '48';
        const limite = { ok: false, status: 429, data: null, error: { code: 'too_many_requests', params: { retry_after: 41, next: 'code' } } };

        await a.resendCode({ api: fakeApi({ '/auth/code': limite }), ...TEXTOS });

        assert.equal(a.codeResent, false);
        assert.deepEqual(a.loginError, { global: '', fields: { code: 'Espera 41 segundos para pedir otro código.' } });
        assert.equal(a.form.code, '48');
        assert.equal(a.stage, STAGE_CODE);
        assert.equal(a.busy, false);
    });

    test('«Cambiar el correo» vuelve a la primera cara y se lleva el código y el correo al que fue', () => {
        const a = store();
        a.form.email = 'ana@correo.es';
        a.showCode('ana@correo.es', true);
        a.form.code = '48';
        a.loginError = { global: '', fields: { code: 'mal' } };

        a.changeEmail();

        assert.equal(a.stage, STAGE_EMAIL);
        assert.equal(a.codeSentTo, '');
        assert.equal(a.codeResent, false);
        assert.equal(a.form.code, '');
        assert.deepEqual(a.loginError, { global: '', fields: {} });
        assert.equal(a.form.email, 'ana@correo.es', 'el correo se queda escrito para corregirlo');
    });

    /**
     * ⚠️⚠️ **El correo al que fue el código es PII**: salir de la pantalla (la zona al montarse) lo borra, como el del alta
     * pendiente. En una tablet compartida, el siguiente no puede leer «Te hemos enviado un código a …» del anterior.
     */
    test('salir de la pantalla se lleva el correo al que fue el código y la puerta vuelve al correo', () => {
        const a = store();
        a.form.email = 'ana@correo.es';
        a.showCode('ana@correo.es', false);
        a.form.code = '4829';

        a.clearNotices();

        assert.equal(a.stage, STAGE_EMAIL);
        assert.equal(a.codeSentTo, '');
        assert.equal(a.form.code, '', 'el código a medio escribir no sobrevive a un cambio de pantalla');
        assert.equal(a.form.email, 'ana@correo.es', 'el correo ESCRITO sí: quien vuelve no tiene que teclearlo otra vez');
    });
});

describe('el alta SUELTA y su «revisa tu correo»', () => {
    /** Un alta sin sesión: es el camino normal fuera de la compra. */
    function apiDeAltaSuelta() {
        return fakeApi({
            '/auth/register': { ok: true, status: 201, data: null },
            '/me': { ok: false, status: 401, data: null },
            '/auth/email/resend': { ok: true, status: 202, data: null },
        });
    }

    test('manda el contexto suelto y deja la pantalla esperando con el correo', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';

        const api = apiDeAltaSuelta();
        await a.registerStandalone({ api, messages: MENSAJES, auth: {} });

        assert.equal(api.llamadas[0].body.context, 'standalone', 'el alta suelta NO puede ser pay-first');
        assert.equal(a.pendingEmail, 'nuevo@ejemplo.test');

    });

    /**
     * ⚠️ **Los datos del formulario no sobreviven, y el correo SÍ.** `reset()` vacía el formulario —en una tablet
     * compartida el nombre del cliente se queda a la vista— pero el correo hace falta para reenviar, así que
     * se copia antes a `pendingEmail`. Sin esa copia, el botón de reenviar no tendría a quién.
     */
    test('vacía el formulario pero conserva el correo al que reenviar', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';
        a.form.name = 'Nora Pérez';

        await a.registerStandalone({ api: apiDeAltaSuelta(), messages: MENSAJES, auth: {} });

        assert.equal(a.form.name, '', 'los datos del cliente no pueden quedarse en un campo visible');
        assert.equal(a.form.email, '');
        assert.equal(a.pendingEmail, 'nuevo@ejemplo.test');

    });

    /**
     * ⚠️⚠️ **Nace ESPERANDO.** El alta que acaba de ocurrir ya gastó el limitador por IP del servidor,
     * así que ofrecer el botón al llegar sería ofrecer un no-op: el servidor descartaría el reenvío y
     * contestaría 202 igual, y al cliente no le llegaría nada.
     */
    test('la pantalla nace con la cuenta atrás llena y los reenvíos intactos', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';

        await a.registerStandalone({ api: apiDeAltaSuelta(), messages: MENSAJES, auth: {} });

        assert.equal(a.resendSeconds, RESEND_COOLDOWN_SECONDS);
        assert.equal(a.resendsLeft, MAX_RESENDS);

        // ⚠️ Los campos van por NOMBRE. Pasar el store entero fue el fallo que destapó este caso: la
        // puerta se quedaba sin `secondsLeft` y ofrecía el botón siempre (ver `account/verify.js`).
        assert.equal(
            resendGate({ secondsLeft: a.resendSeconds, resendsLeft: a.resendsLeft }).canResend, false,
            'el botón no puede ofrecerse recién llegado'
        );
    });

    test('si el alta consigue sesión NO se queda en «revisa tu correo»', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';

        const api = fakeApi({
            '/auth/register': { ok: true, status: 201, data: null },
            '/me': { ok: true, status: 200, data: { id: 9 } },
        });

        const r = await a.registerStandalone({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.identified, true);
        assert.equal(a.pendingEmail, '', 'con sesión se aterriza; esta pantalla no pinta nada');
    });

    test('un alta rechazada no lleva a la pantalla de verificación', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';

        const api = fakeApi({
            '/auth/register': { ok: false, status: 422, data: null, error: { code: 'validation_failed', message: 'x', fields: { email: ['Ya existe'] } } },
        });

        await a.registerStandalone({ api, messages: MENSAJES, auth: {} });

        assert.equal(a.pendingEmail, '');
        assert.equal(a.form.email, 'nuevo@ejemplo.test', 'un rechazo no puede borrar lo que el cliente escribió');
    });
});

describe('el reenvío del correo de verificación', () => {
    function pantallaLista() {
        const a = store();
        a.awaitVerification('nuevo@ejemplo.test');
        a.resendSeconds = 0;

        return a;
    }

    test('reenvía al correo pendiente, descuenta y vuelve a esperar', async () => {
        const a = pantallaLista();
        const api = fakeApi({ '/auth/email/resend': { ok: true, status: 202, data: null } });

        const r = await a.resendVerification({ api });

        assert.equal(r.ok, true);
        assert.deepEqual(api.llamadas[0].body, { email: 'nuevo@ejemplo.test' });
        assert.equal(a.resendsLeft, MAX_RESENDS - 1);
        assert.equal(a.resendSeconds, RESEND_COOLDOWN_SECONDS);

    });

    /**
     * ⚠️ **Se descuenta PASE LO QUE PASE**, igual que en `Register::resend()` de la web. El endpoint
     * responde 202 aunque descarte el envío, así que condicionar el descuento a «que haya ido bien»
     * dejaría al cliente insistiendo sin tope sobre un servidor que ya lo está tirando. Con un corte
     * de red vale lo mismo: lo que se protege es el buzón, no el contador.
     */
    test('un fallo de red también gasta el reenvío', async () => {
        const a = pantallaLista();
        const api = fakeApi({ '/auth/email/resend': { ok: false, status: 0, data: null, offline: true } });

        const r = await a.resendVerification({ api });

        assert.equal(r.ok, false);
        assert.equal(a.resendsLeft, MAX_RESENDS - 1, 'un reintento sin tope sobre un fallo bombardea el buzón');

    });

    test('no se puede reenviar mientras corre la cuenta atrás', async () => {
        const a = store();
        a.awaitVerification('nuevo@ejemplo.test');
        const api = fakeApi({});

        const r = await a.resendVerification({ api });

        assert.deepEqual(r, { ok: false, skipped: true });
        assert.equal(api.llamadas.length, 0, 'el servidor lo descartaría en silencio: ni se le pide');
        assert.equal(a.resendsLeft, MAX_RESENDS, 'un intento que no sale no puede gastar reenvío');
    });

    test('ni cuando se han agotado, por mucho que la cuenta atrás esté a cero', async () => {
        const a = pantallaLista();
        a.resendsLeft = 0;
        const api = fakeApi({});

        assert.deepEqual(await a.resendVerification({ api }), { ok: false, skipped: true });
        assert.equal(api.llamadas.length, 0);
    });

    /** ⚠️ Dos relojes sobre el mismo número consumirían la espera al doble de velocidad. */
    test('arrancar la cuenta atrás dos veces no deja dos relojes', () => {
        const a = store();
        a.awaitVerification('nuevo@ejemplo.test');

        a.startResendCountdown();
        const primero = a.resendTicker;
        a.startResendCountdown();

        assert.notEqual(a.resendTicker, primero, 'el reloj anterior tiene que morir antes de nacer el nuevo');

        a.stopResendCountdown();

        assert.equal(a.resendTicker, null, 'parar tiene que dejar el campo limpio, o `afterEach` no sabría qué parar');
    });

    test('salir de la pantalla borra el correo pendiente, que es PII', () => {
        const a = store();
        a.awaitVerification('nuevo@ejemplo.test');

        a.clearNotices();

        assert.equal(a.pendingEmail, '', 'el correo del cliente anterior no puede quedarse en pantalla');
        assert.equal(a.resendsLeft, 0);
    });
});

describe('pedir el enlace de recuperar contraseña', () => {
    test('arranca sin haber pedido nada y sin avisos', () => {
        const a = store();

        assert.equal(a.forgotSent, false);
        assert.deepEqual(a.forgotError, { global: '', fields: {} });
    });

    test('el 202 deja la pantalla en «revisa tu correo», con el correo del formulario', async () => {
        const a = store();
        a.form.email = 'cliente@ejemplo.test';

        const api = fakeApi({ '/auth/password/forgot': { ok: true, status: 202, data: null } });
        const r = await a.requestPasswordLink({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, true);
        assert.equal(a.forgotSent, true);
        assert.deepEqual(api.llamadas[0].body, { email: 'cliente@ejemplo.test' });
    });

    test('un límite de intentos NO enseña «revisa tu correo» y deja su aviso bajo el campo', async () => {
        const a = store();
        a.form.email = 'cliente@ejemplo.test';

        const api = fakeApi({
            '/auth/password/forgot': {
                ok: false,
                status: 429,
                data: null,
                error: { code: 'too_many_requests', message: 'x', params: { retry_after: 30 } },
            },
        });

        await a.requestPasswordLink({ api, messages: MENSAJES, auth: { throttle: 'Espera :seconds s.' } });

        assert.equal(a.forgotSent, false, 'decirle «revisa tu correo» a quien no ha recibido nada es mentirle');
        assert.equal(a.forgotError.fields.email, 'Espera 30 s.');
    });

    test('no se puede pedir dos veces con la primera petición en vuelo', async () => {
        const a = store();
        a.busy = true;

        const r = await a.requestPasswordLink({ api: fakeApi({}), messages: MENSAJES, auth: {} });

        assert.deepEqual(r, { ok: false, skipped: true });
    });

    /**
     * ⚠️ **Y reiniciar borra el «enviado»**: sin esto, quien abriera la pantalla se encontraría la
     * confirmación de la visita anterior —o del cliente anterior, en una tablet compartida— sin haber
     * pedido nada. Es la defensa que en la web hacía `$store.auth.completed` con su recarga.
     */
    test('reiniciar borra el «enviado» y su aviso', async () => {
        const a = store();
        a.form.email = 'cliente@ejemplo.test';

        await a.requestPasswordLink({
            api: fakeApi({ '/auth/password/forgot': { ok: true, status: 202, data: null } }),
            messages: MENSAJES,
            auth: {},
        });

        assert.equal(a.forgotSent, true);

        a.reset();

        assert.equal(a.forgotSent, false);
        assert.deepEqual(a.forgotError, { global: '', fields: {} });
        assert.equal(a.form.email, '');
    });
});

describe('el alta esperando verificación', () => {
    test('lo dice mientras hay un correo pendiente', () => {
        const a = store();

        assert.equal(a.awaitingVerification, false, 'nace sin nada esperando');

        a.awaitVerification('nuevo@ejemplo.test');

        assert.equal(a.awaitingVerification, true);
    });

    /**
     * ⚠️ Lo consume el armazón del área para NO pintar la barra de pestañas mientras esa pantalla
     * está delante: pulsarlas salía de la zona, y salir borra el correo pendiente a propósito (PII).
     * La salida deliberada sigue viva dentro de la propia pantalla.
     */
    test('deja de decirlo cuando se sale de la pantalla', () => {
        const a = store();
        a.awaitVerification('nuevo@ejemplo.test');

        a.clearNotices();

        assert.equal(a.awaitingVerification, false);
    });
});

/**
 * `#175` (revisión `#169` §10.3, CAJ-2): el alta con la casilla del waiver marcada y un texto que ya no
 * es el vigente vuelve como 422 sobre `waiver_document_id` («vuelve a leerlo»). Hasta hoy el texto
 * plegado seguía siendo el viejo y cada reenvío mandaba el mismo id: un callejón sin salida.
 */
describe('el alta cuyo texto del waiver caducó', () => {
    test('un 422 sobre waiver_document_id desmarca la casilla y RELEE el texto', async () => {
        const a = store();
        const waiver = useWaiverStore();
        const v2 = { mode: 'interno', document: { id: 7, version: 2, locale: 'es', title: 'Descargo de responsabilidad', sections: [{ h: 'Riesgo', p: 'v2' }], published_at: null } };
        const v3 = { mode: 'interno', document: { id: 9, version: 3, locale: 'es', title: 'Descargo de responsabilidad', sections: [{ h: 'Riesgo', p: 'v3' }], published_at: null } };
        const legal = [v2, v3];
        const api = fakeApi({
            '/auth/register': { ok: false, status: 422, data: null, error: { code: 'validation_failed', message: 'Revisa', fields: { waiver_document_id: ['El texto del waiver ha cambiado. Vuelve a leerlo y acéptalo de nuevo.'] } } },
        });
        api.get = async (url) => { api.llamadas.push({ url }); return url === '/legal/waiver' ? { ok: true, status: 200, data: legal.shift() ?? v3 } : { ok: false, status: 500, data: null }; };

        await waiver.ensureLegal({ api });
        a.form.accept_waiver = true;
        const r = await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, false);
        assert.equal(api.llamadas.find((c) => c.url === '/auth/register').body.waiver_document_id, 7, 'se mandó el id del texto que se había leído');
        assert.equal(a.form.accept_waiver, false, 'lo que se leyó ya no es lo que se firma');
        assert.equal(waiver.document.version, 3, 'el texto plegado es ahora el vigente');
        assert.equal(waiver.reread, true);
        assert.equal(a.registerError.fields.waiver_document_id, 'El texto del waiver ha cambiado. Vuelve a leerlo y acéptalo de nuevo.');
    });

    /** CAJ-422 (`#181`): el 422 de `accept_waiver` también relee — y con `document: null` cacheado, es la única salida. */
    test('un 422 sobre accept_waiver relee el texto aunque el cacheado fuera document: null', async () => {
        const a = store();
        const waiver = useWaiverStore();
        const legal = [{ mode: 'interno', document: null }, { mode: 'interno', document: { id: 7, version: 1, locale: 'es', title: 'Descargo de responsabilidad', sections: [{ h: 'Riesgo', p: 'v1' }], published_at: null } }];
        const api = fakeApi({
            '/auth/register': { ok: false, status: 422, data: null, error: { code: 'validation_failed', message: 'Revisa', fields: { accept_waiver: ['Para crear la cuenta hay que leer y aceptar el descargo de responsabilidad.'] } } },
        });
        api.get = async (url) => { api.llamadas.push({ url }); return url === '/legal/waiver' ? { ok: true, status: 200, data: legal.shift() ?? legal[0] } : { ok: false, status: 500, data: null }; };

        await waiver.ensureLegal({ api });
        assert.equal(waiver.document, null, 'montado antes de publicarse la versión');
        const r = await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, false);
        assert.equal(waiver.document?.version, 1, 'ahora el texto (y la casilla) existen');
        assert.equal(waiver.reread, true);
        assert.equal(a.form.accept_waiver, false);
    });

    test('un 422 por OTRO campo no toca el texto ni la casilla', async () => {
        const a = store();
        const waiver = useWaiverStore();
        const api = fakeApi({
            '/legal/waiver': { ok: true, status: 200, data: { mode: 'interno', document: { id: 7, version: 2, locale: 'es', title: 'Descargo de responsabilidad', sections: [], published_at: null } } },
            '/auth/register': { ok: false, status: 422, data: null, error: { code: 'validation_failed', message: 'Revisa', fields: { email: ['Ya existe'] } } },
        });

        await waiver.ensureLegal({ api });
        a.form.accept_waiver = true;
        await a.register({ api, messages: MENSAJES, auth: {} });

        assert.equal(a.form.accept_waiver, true);
        assert.equal(waiver.reread, false);
        assert.equal(api.llamadas.filter((c) => c.url === '/legal/waiver').length, 1);
    });
});
