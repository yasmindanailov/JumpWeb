import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { doorErrors, runCodeLogin, runDoor, runResend, INVALID_CREDENTIALS, NEXT_CODE, NEXT_REGISTER } from './login.js';

/**
 * La red de ENTRAR CON UN CÓDIGO en el cajón (A4a de `docs/specs/acceso-con-codigo.md` §4.11): la puerta, el código y el
 * REPARTO de sus «no» entre el banner y los campos. Los códigos y el sobre los fija el servidor (`Api\V1\AuthCodeTest`,
 * `AuthSessionTest`); aquí, lo que el cajón hace con ellos, incluidos los estados degradados.
 */

const MESSAGES = { errors: { try_later: 'Espera un minuto y vuelve a intentarlo.' } };

const AUTH = { throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.' };

const ACCOUNT = { login: {
    code_wrong: 'El código no es correcto o ha caducado. Pide otro.',
    code_wait: 'Espera :n segundos para pedir otro código.',
} };

const TEXTS = { messages: MESSAGES, auth: AUTH, account: ACCOUNT };

const ok = (data) => ({ ok: true, status: 200, data, error: null, offline: false });

const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });

const offline = () => ({ ok: false, status: 0, data: null, error: null, offline: true });

/** Un `api` de mentira que responde lo que se le diga y apunta lo que se le pide. */
function fakeApi(response) {
    const calls = [];

    return { calls, post: async (url, body) => { calls.push({ url, body }); return response; } };
}

describe('la puerta: el correo decide', () => {
    test('con cuenta, a escribir el código (que ya va de camino)', async () => {
        const api = fakeApi(ok({ next: 'code' }));

        const r = await runDoor({ email: '  ana@correo.es ', api, ...TEXTS });

        assert.equal(r.next, NEXT_CODE);
        assert.deepEqual(r.errors, { global: '', fields: {} });
        assert.deepEqual(api.calls, [{ url: '/auth/code', body: { email: 'ana@correo.es' } }], 'el correo va recortado, y solo él');
    });

    test('nuevo, al alta: sin código delante (`#849`)', async () => {
        const r = await runDoor({ email: 'nuevo@correo.es', api: fakeApi(ok({ next: 'register' })), ...TEXTS });

        assert.equal(r.next, NEXT_REGISTER);
    });

    /**
     * ⚠️ **El tope del CORREO no es un «no»**: el servidor acaba de mandar un código a ese buzón (`params.next = code`), así
     * que se va a escribirlo SIN aviso. Pintarlo como error haría creer que no llegó nada.
     */
    test('el tope del correo (429 con `next: code`) lleva al código sin ningún aviso', async () => {
        const r = await runDoor({ email: 'ana@correo.es', api: fakeApi(fail(429, { code: 'too_many_requests', params: { retry_after: 41, next: 'code' } })), ...TEXTS });

        assert.equal(r.next, NEXT_CODE);
        assert.deepEqual(r.errors, { global: '', fields: {} });
    });

    test('el tope de la IP (429 sin `next`) se queda en la puerta, con su espera en el banner', async () => {
        const r = await runDoor({ email: 'ana@correo.es', api: fakeApi(fail(429, { code: 'too_many_requests', params: { retry_after: 30 } })), ...TEXTS });

        assert.equal(r.next, null);
        assert.equal(r.errors.global, 'Demasiados intentos. Inténtalo de nuevo en 30 segundos.');
        assert.deepEqual(r.errors.fields, {});
    });

    test('un correo mal formado lo dice el servidor, bajo el campo del correo', async () => {
        const r = await runDoor({ email: 'no-es-un-correo', api: fakeApi(fail(422, { code: 'validation_failed', fields: { email: ['El correo no es válido.'] } })), ...TEXTS });

        assert.equal(r.next, null);
        assert.deepEqual(r.errors, { global: '', fields: { email: 'El correo no es válido.' } });
    });

    test('un 200 que no dice a dónde ir NO deja la pantalla muda: el genérico', async () => {
        const r = await runDoor({ email: 'ana@correo.es', api: fakeApi(ok({ next: 'otra-cosa' })), ...TEXTS });

        assert.equal(r.next, null);
        assert.equal(r.errors.global, MESSAGES.errors.try_later);
    });

    test('sin conexión: el genérico, arriba', async () => {
        const r = await runDoor({ email: 'ana@correo.es', api: fakeApi(offline()), ...TEXTS });

        assert.equal(r.next, null);
        assert.equal(r.errors.global, MESSAGES.errors.try_later);
    });
});

describe('pedir otro código (siempre a mano, como la isla: `#812`)', () => {
    test('salió uno nuevo: `sent`, sin aviso, y al MISMO correo, recortado', async () => {
        const api = fakeApi(ok({ next: 'code' }));

        const r = await runResend({ email: ' ana@correo.es ', api, ...TEXTS });

        assert.equal(r.sent, true);
        assert.deepEqual(r.errors, { global: '', fields: {} });
        assert.deepEqual(api.calls, [{ url: '/auth/code', body: { email: 'ana@correo.es' } }]);
    });

    /**
     * ⚠️⚠️ **El tope del correo, AQUÍ, es un «no»**: en la puerta el `429` con `next: code` lleva al código sin aviso (hay uno
     * recién enviado), pero al pedir OTRO no ha salido ninguno. Darlo por enviado haría esperar un correo que no llega.
     */
    test('antes del minuto (429 con `next: code`) NO se da por enviado: dice cuánto esperar, bajo el código', async () => {
        const r = await runResend({ email: 'ana@correo.es', api: fakeApi(fail(429, { code: 'too_many_requests', params: { retry_after: 41, next: 'code' } })), ...TEXTS });

        assert.equal(r.sent, false);
        assert.equal(r.next, null);
        assert.deepEqual(r.errors, { global: '', fields: { code: 'Espera 41 segundos para pedir otro código.' } });
    });

    test('la cuenta se borró entre los dos correos: no sale código, lleva al alta', async () => {
        const r = await runResend({ email: 'ana@correo.es', api: fakeApi(ok({ next: 'register' })), ...TEXTS });

        assert.equal(r.sent, false);
        assert.equal(r.next, NEXT_REGISTER);
    });

    test('sin conexión: no salió nada, el genérico arriba', async () => {
        const r = await runResend({ email: 'ana@correo.es', api: fakeApi(offline()), ...TEXTS });

        assert.equal(r.sent, false);
        assert.equal(r.errors.global, MESSAGES.errors.try_later);
    });
});

describe('entrar con el código', () => {
    test('manda el correo, el código y SIN recordar el dispositivo si no se pidió (`#858`)', async () => {
        const api = fakeApi(ok({ id: 7 }));

        const r = await runCodeLogin({ email: ' ana@correo.es', code: ' 482 913 ', api, ...TEXTS });

        assert.equal(r.ok, true);
        assert.deepEqual(api.calls[0], { url: '/auth/login', body: { email: 'ana@correo.es', code: '482 913', remember: false } });
    });

    test('recuerda el dispositivo SOLO si la casilla va marcada de verdad', async () => {
        for (const [remember, esperado] of [[true, true], ['true', false], [1, false], [undefined, false]]) {
            const api = fakeApi(ok({ id: 7 }));
            await runCodeLogin({ email: 'a@b.es', code: '1', remember, api, ...TEXTS });
            assert.equal(api.calls[0].body.remember, esperado, `remember = ${String(remember)}`);
        }
    });

    /**
     * ⚠️ **Bajo el campo del CÓDIGO y con el literal del cajón**, no con el `message` del sobre: el fixture usa a propósito
     * un mensaje distinto, o este caso no podría distinguir qué se pinta.
     */
    test('un código que no vale: bajo su campo, con la frase del cajón y no la del sobre', async () => {
        const r = await runCodeLogin({ email: 'a@b.es', code: '000000', api: fakeApi(fail(401, { code: INVALID_CREDENTIALS, message: 'El correo o la contraseña no son correctos.' })), ...TEXTS });

        assert.equal(r.ok, false);
        assert.deepEqual(r.errors, { global: '', fields: { code: ACCOUNT.login.code_wrong } });
    });

    test('demasiados intentos: al banner, con su espera', async () => {
        const r = await runCodeLogin({ email: 'a@b.es', code: '1', api: fakeApi(fail(429, { code: 'too_many_requests', params: { retry_after: 59 } })), ...TEXTS });

        assert.equal(r.errors.global, 'Demasiados intentos. Inténtalo de nuevo en 59 segundos.');
    });

    test('la validación del código, bajo el código', async () => {
        const r = await runCodeLogin({ email: 'a@b.es', code: '', api: fakeApi(fail(422, { code: 'validation_failed', fields: { code: ['Escribe el código.'] } })), ...TEXTS });

        assert.deepEqual(r.errors.fields, { code: 'Escribe el código.' });
    });
});

describe('el reparto de los avisos, sin petición', () => {
    test('entrar bien no deja ningún aviso', () => {
        assert.deepEqual(doorErrors(ok({ id: 7 }), TEXTS), { global: '', fields: {} });
    });

    test('un código de error que no se conoce cae en el genérico, arriba', () => {
        assert.deepEqual(doorErrors(fail(500, { code: 'server_error' }), TEXTS), { global: MESSAGES.errors.try_later, fields: {} });
    });
});
