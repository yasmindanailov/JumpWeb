import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { CONFIRM_ACTIONS, confirmCodeOutcome, requestConfirmCode } from './confirm-code.js';

/**
 * La red de PEDIR el código que confirma una acción de Mi cuenta (A4b de `docs/specs/acceso-con-codigo.md` §4.11, `#813`).
 * El `202`, el tope y su `retry_after` los fija el servidor (`Api\V1\MeConfirmationCodeTest`); aquí, lo que la pieza
 * enseña con cada respuesta.
 */

const TEXTS = {
    account: { login: { code_wait: 'Espera :n segundos para pedir otro código.' } },
    messages: { errors: { try_later: 'Espera un minuto y vuelve a intentarlo.' } },
    auth: { throttle: 'Demasiados intentos. Inténtalo de nuevo en :seconds segundos.' },
};

const fail = (status, error) => ({ ok: false, status, data: { error }, error, offline: false });

function fakeApi(response) {
    const calls = [];

    return { calls, post: async (url, body) => { calls.push({ url, body }); return response; } };
}

describe('pedir el código de una acción', () => {
    test('salió: el campo a la vista y sin aviso; al endpoint de confirmar, con la acción y nada más', async () => {
        const api = fakeApi({ ok: true, status: 202, data: null, error: null, offline: false });

        const r = await requestConfirmCode(CONFIRM_ACTIONS.CLOSE_SESSIONS, { api, ...TEXTS });

        assert.deepEqual(r, { sent: true, shown: true, error: '', notice: '', expired: false });
        assert.deepEqual(api.calls, [{ url: '/me/confirm-code', body: { action: 'close_sessions' } }]);
    });

    /**
     * ⚠️⚠️ **El tope enseña el campo** (hay uno recién enviado, y no va atado a la acción) **pero NO lo da por enviado**: la
     * pista diría «te hemos enviado otro» de un correo que no ha salido. La espera, bajo el código.
     */
    test('el tope (429): el campo a la vista, sin darlo por enviado, y cuánto esperar bajo el código', () => {
        const r = confirmCodeOutcome(fail(429, { code: 'too_many_requests', params: { retry_after: 37 } }), TEXTS);

        assert.deepEqual(r, { sent: false, shown: true, error: 'Espera 37 segundos para pedir otro código.', notice: '', expired: false });
    });

    test('el tope sin `retry_after`: se dice el minuto del límite, nunca «Espera  segundos»', () => {
        const r = confirmCodeOutcome(fail(429, { code: 'too_many_requests' }), TEXTS);

        assert.equal(r.error, 'Espera 60 segundos para pedir otro código.');
    });

    test('la sesión caducó por el camino: ni campo ni aviso, a volver a entrar', () => {
        const r = confirmCodeOutcome(fail(401, { code: 'unauthenticated' }), TEXTS);

        assert.deepEqual(r, { sent: false, shown: false, error: '', notice: '', expired: true });
    });

    test('sin conexión: el campo sigue escondido y el genérico arriba', () => {
        const r = confirmCodeOutcome({ ok: false, status: 0, data: null, error: null, offline: true }, TEXTS);

        assert.equal(r.shown, false);
        assert.equal(r.notice, TEXTS.messages.errors.try_later);
    });

    test('un «no» sin texto (un 422 por campo) no deja el aviso mudo', () => {
        const r = confirmCodeOutcome(fail(422, { code: 'validation_failed', fields: { action: ['x'] } }), TEXTS);

        assert.equal(r.shown, false);
        assert.equal(r.notice, TEXTS.messages.errors.try_later);
    });

    test('un «no» con mensaje del servidor: se enseña ese, no el genérico', () => {
        const r = confirmCodeOutcome(fail(500, { code: 'server_error', message: 'El servicio de correo no responde.' }), TEXTS);

        assert.equal(r.notice, 'El servicio de correo no responde.');
    });

    test('las cuatro acciones son las del servidor (`AccountCredentials::CONFIRM_ACTIONS`)', () => {
        assert.deepEqual(Object.values(CONFIRM_ACTIONS).sort(), ['change_email', 'close_sessions', 'delete_account', 'unlink_google']);
    });
});
