import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from './auth.js';

/**
 * La red del store de IDENTIFICARSE.
 *
 * ⚠️ **Se dobla solo la frontera HTTP.** `login.js` y `register.js` corren de verdad —ya tienen sus
 * casos y su paridad contra el servidor—, así que esto prueba el store Y su costura con ellos, que es
 * justo lo que un doble de módulo habría dejado sin comprobar.
 */
const MENSAJES = { errors: { try_later: 'Espera un minuto.' } };

function store() {
    setActivePinia(createPinia());

    return useAuthStore();
}

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
