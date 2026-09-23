import { test, describe, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { request } from './api.js';

/**
 * T1b de la analítica — **un fallo del cliente HTTP se le cuenta a la página** (`jw:api:failed`), que es como
 * `track.js` emite `request_failed` sin que `api.js` sepa que existe (`docs/specs/analitica.md` §4.2).
 *
 * Solo eso: las cuatro trampas del cliente (`credentials`, `Accept`, CSRF, el 419) las midió un `curl` y las
 * vigila la spec de la API; aquí se fija lo que el fallo CUENTA y lo que no (ni cuerpo, ni error, ni query).
 */

let eventos;
let respuesta;

beforeEach(() => {
    eventos = [];
    globalThis.document = { cookie: '', dispatchEvent: (e) => eventos.push({ tipo: e.type, detalle: e.detail }) };
    globalThis.fetch = () => (respuesta instanceof Error ? Promise.reject(respuesta) : Promise.resolve(respuesta));
});

afterEach(() => {
    delete globalThis.document;
    delete globalThis.fetch;
});

describe('lo que un fallo cuenta', () => {
    test('la red caída: ruta sin query, estado 0 y `offline`', async () => {
        respuesta = new TypeError('Failed to fetch');

        const res = await request('/availability?date=2026-09-23&product=7');

        assert.equal(res.ok, false);
        assert.deepEqual(eventos, [{ tipo: 'jw:api:failed', detalle: { route: '/availability', status: 0, offline: true } }]);
    });

    test('un rechazo del servidor: su estado, y nada del sobre de error', async () => {
        respuesta = { ok: false, status: 422, text: async () => JSON.stringify({ error: { code: 'validation', message: 'ana@example.com' } }) };

        const res = await request('/orders');

        assert.equal(res.error.code, 'validation');
        assert.deepEqual(eventos, [{ tipo: 'jw:api:failed', detalle: { route: '/orders', status: 422, offline: false } }]);
    });

    test('una respuesta buena no cuenta nada', async () => {
        respuesta = { ok: true, status: 200, text: async () => '{"data":[]}' };

        await request('/me');

        assert.deepEqual(eventos, []);
    });

    test('abortar es una decisión de quien llama, no un fallo', async () => {
        respuesta = Object.assign(new Error('aborted'), { name: 'AbortError' });

        await assert.rejects(() => request('/me'));
        assert.deepEqual(eventos, []);
    });
});
