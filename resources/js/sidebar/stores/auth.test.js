import { test, describe, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth.js';
import { useWaiverStore } from './waiver.js';
import { MAX_RESENDS, RESEND_COOLDOWN_SECONDS, resendGate } from '../account/verify.js';
import { createNavigation } from '../account/navigation.js';
import { useAccountStore } from './account.js';
import { useSectionStore } from './section.js';

/**
 * La red del store de IDENTIFICARSE.
 *
 * ⚠️ **Se dobla solo la frontera HTTP.** `login.js` y `register.js` corren de verdad —ya tienen sus
 * casos y su paridad contra el servidor—, así que esto prueba el store Y su costura con ellos, que es
 * justo lo que un doble de módulo habría dejado sin comprobar.
 */
const MENSAJES = { errors: { try_later: 'Espera un minuto.' } };

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
    test('arranca en la pestaña de entrar, sin avisos y con el formulario en blanco', () => {
        const a = store();

        assert.equal(a.mode, 'login');
        assert.equal(a.busy, false);
        assert.equal(a.form.email, '');
        assert.equal(a.form.password, '');
        assert.equal(a.form.website, '', 'el señuelo empieza vacío: rellenarlo es lo que delata al bot');
        assert.deepEqual(a.loginError, { global: '', fields: {} });
    });

    test('cambiar de pestaña BORRA los avisos del intento anterior', () => {
        const a = store();
        a.loginError = { global: 'Credenciales incorrectas', fields: { email: ['x'] } };
        a.registerError = { summary: ['algo'], fields: {} };

        a.setMode('register');

        assert.equal(a.mode, 'register');
        assert.equal(a.loginError.global, '', 'arrastrar un aviso a la otra pestaña confunde');
        assert.deepEqual(a.registerError.summary, []);

        a.setMode('cualquier-cosa');
        assert.equal(a.mode, 'login', 'lo que no es «register» es entrar');
    });

    test('entrar con credenciales buenas devuelve el perfil y no deja avisos', async () => {
        const a = store();
        a.form.email = 'cliente@ejemplo.test';
        a.form.password = 'secreto';
        const api = fakeApi({ '/auth/login': { ok: true, status: 200, data: { id: 7, email: 'cliente@ejemplo.test' } } });

        const r = await a.login({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, true);
        assert.equal(a.busy, false, 'el indicador se apaga pase lo que pase');
        assert.equal(a.loginError.global, '');
        assert.deepEqual(api.llamadas[0].body, { email: 'cliente@ejemplo.test', password: 'secreto', remember: false });
    });

    test('un login rechazado deja su aviso y NO limpia el formulario', async () => {
        const a = store();
        a.form.email = 'cliente@ejemplo.test';
        const api = fakeApi({ '/auth/login': { ok: false, status: 422, data: { errors: { email: ['Credenciales incorrectas'] } } } });

        const r = await a.login({ api, messages: MENSAJES, auth: {} });

        assert.equal(r.ok, false);
        assert.equal(a.form.email, 'cliente@ejemplo.test', 'volver a teclear el correo tras un fallo es hostil');
        assert.equal(a.busy, false);
    });

    /**
     * ⚠️ La guarda vive en el STORE y no en el `disabled` del botón: `disabled` es presentación, y un
     * `Enter` repetido no pasa por él. Dos altas simultáneas con el mismo token del anti-bot serían
     * un «no eres un robot» con el tick verde puesto.
     */
    test('no se puede lanzar una segunda petición con la primera en vuelo', async () => {
        const a = store();
        a.busy = true;

        const api = fakeApi({});
        const r = await a.login({ api, messages: MENSAJES, auth: {} });

        assert.deepEqual(r, { ok: false, skipped: true });
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

    test('reiniciar deja el formulario en blanco, contraseña incluida', () => {
        const a = store();
        a.form.email = 'x@y.z';
        a.form.password = 'secreto';
        a.loginError = { global: 'algo', fields: {} };

        a.reset();

        assert.equal(a.form.password, '', 'la contraseña no sobrevive a un cambio de pantalla');
        assert.equal(a.form.email, '');
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

describe('ir a recuperar la contraseña desde el EMBUDO', () => {
    /**
     * ⚠️⚠️ **Lo que se fija aquí es que la pila quede VACÍA, y no es un detalle**
     * (`specs/auth-en-cajon.md` §3.4). El cliente viene del paso 5 de la compra, que **no es una
     * zona**: con la pila vacía, «volver» sale de la sección y la compra reaparece donde estaba, con
     * su cesta. Si esto sembrara `LOGIN` debajo —que es lo correcto cuando se llega por una PUERTA—
     * el cliente que estaba comprando acabaría en el área de cliente con su compra abandonada.
     */
    test('lleva a recuperar y deja «volver» significando SALIR a la compra', () => {
        const a = store();
        const cuenta = useAccountStore();
        cuenta.boot(createNavigation());

        a.startPasswordRecovery();

        assert.equal(cuenta.zone, 'forgot');
        assert.equal(useSectionStore().onAccount, true, 'no ha conmutado de sección: seguiría viéndose el embudo');
        assert.equal(cuenta.canBack, false, 'con algo debajo, «volver» dejaría al cliente en el área en vez de en su compra');

        // Y «volver» devuelve de verdad a la compra.
        assert.equal(cuenta.back(), false);
        assert.equal(useSectionStore().onPurchase, true);
    });

    test('y limpia los avisos del intento anterior sin borrar el correo escrito', () => {
        const a = store();
        useAccountStore().boot(createNavigation());
        a.form.email = 'cliente@ejemplo.test';
        a.loginError = { global: 'Credenciales incorrectas', fields: {} };

        a.startPasswordRecovery();

        assert.equal(a.loginError.global, '', 'un aviso del login sobre la pantalla de recuperar no describe nada');
        assert.equal(a.form.email, 'cliente@ejemplo.test', 'volver a teclear el correo aquí sería hostil');
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
        a.form.password = 'un-secreto-muy-largo';

        const api = apiDeAltaSuelta();
        await a.registerStandalone({ api, messages: MENSAJES, auth: {} });

        assert.equal(api.llamadas[0].body.context, 'standalone', 'el alta suelta NO puede ser pay-first');
        assert.equal(a.pendingEmail, 'nuevo@ejemplo.test');

    });

    /**
     * ⚠️ **La contraseña no sobrevive, y el correo SÍ.** `reset()` vacía el formulario —en una tablet
     * compartida esa contraseña se queda a la vista— pero el correo hace falta para reenviar, así que
     * se copia antes a `pendingEmail`. Sin esa copia, el botón de reenviar no tendría a quién.
     */
    test('vacía el formulario pero conserva el correo al que reenviar', async () => {
        const a = store();
        a.form.email = 'nuevo@ejemplo.test';
        a.form.password = 'un-secreto-muy-largo';

        await a.registerStandalone({ api: apiDeAltaSuelta(), messages: MENSAJES, auth: {} });

        assert.equal(a.form.password, '', 'la contraseña no puede quedarse en un campo visible');
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
        const v2 = { mode: 'interno', document: { id: 7, version: 2, locale: 'es', title: 'Exención', sections: [{ h: 'Riesgo', p: 'v2' }], published_at: null } };
        const v3 = { mode: 'interno', document: { id: 9, version: 3, locale: 'es', title: 'Exención', sections: [{ h: 'Riesgo', p: 'v3' }], published_at: null } };
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

    test('un 422 por OTRO campo no toca el texto ni la casilla', async () => {
        const a = store();
        const waiver = useWaiverStore();
        const api = fakeApi({
            '/legal/waiver': { ok: true, status: 200, data: { mode: 'interno', document: { id: 7, version: 2, locale: 'es', title: 'Exención', sections: [], published_at: null } } },
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
