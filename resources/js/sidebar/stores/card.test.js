import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useCardStore } from './card.js';

/**
 * La red del carné QR en el cliente (Fase 6 · A, §9.6 B·2).
 *
 * ⚠️ Aquí no se prueba ninguna REGLA: la forma del token, su unicidad y qué mata una rotación los
 * decide el SERVIDOR (`CustomerCardTest`, `MeCardTest`). Lo que se sostiene es lo que el cliente sí
 * decide: **qué pide**, **qué coloca** y **qué le dice a la pantalla**.
 */

const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };
const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

const card = (token = 'JW0X3K9MABCDEFGH1234', issued = '2026-08-28T07:30:00+02:00') => ({ token, issued_at: issued, png_url: '/api/v1/me/card/png' });
const ok = (data, status = 200) => ({ ok: true, status, data, error: null, offline: false });
const ko = (status, error = null, offline = false) => ({ ok: false, status, data: null, error, offline });

function fakeApi(responses) {
    const calls = [];
    const respond = (method) => async (url, body) => {
        calls.push({ method, url, body });

        return responses[`${method} ${url}`] ?? ko(500, null);
    };

    return { calls, get: respond('GET'), post: respond('POST') };
}

describe('el carné', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('se pide una vez y se coloca tal cual', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()) });

        await store.ensure({ api });
        await store.ensure({ api });

        assert.equal(store.loaded, true);
        assert.equal(store.card.token, 'JW0X3K9MABCDEFGH1234');
        assert.equal(api.calls.length, 1, 'ensure() pide solo si no lo tiene');
    });

    test('dos llamadas mientras la primera está en vuelo esperan a la MISMA petición', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()) });

        await Promise.all([store.ensure({ api }), store.ensure({ api })]);

        assert.equal(api.calls.length, 1);
        assert.equal(store.loaded, true);
    });

    test('una sesión perdida se marca como caducada, no como carné vacío', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ko(401, { code: 'unauthenticated', message: '' }) });

        await store.ensure({ api });

        assert.equal(store.expired, true);
        assert.equal(store.loaded, false);
        assert.equal(store.loading, false);
    });

    test('invalidar olvida el carné y el siguiente ensure() vuelve a preguntar', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()) });

        await store.ensure({ api });
        store.invalidate();
        await store.ensure({ api });

        assert.equal(api.calls.length, 2);
    });
});

describe('renovar', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda el POST sin cuerpo que decidir y SUSTITUYE el carné por el nuevo', async () => {
        const store = useCardStore();
        const api = fakeApi({
            'GET /me/card': ok(card()),
            'POST /me/card/rotate': ok(card('JWNEWNEWNEWNEWNEWNEW', '2026-08-28T08:00:00+02:00'), 201),
        });

        await store.ensure({ api });
        const done = await store.rotate(ctx(api));

        assert.equal(done, true);
        assert.equal(store.done, true);
        assert.equal(store.card.token, 'JWNEWNEWNEWNEWNEWNEW');
        assert.equal(store.card.issued_at, '2026-08-28T08:00:00+02:00', 'con el nuevo issued_at cambia la URL de la imagen');
        assert.deepEqual(api.calls.at(-1), { method: 'POST', url: '/me/card/rotate', body: {} });
    });

    test('el limitador se enseña con los segundos que publica el servidor, y el carné viejo se queda', async () => {
        const store = useCardStore();
        const api = fakeApi({
            'GET /me/card': ok(card()),
            'POST /me/card/rotate': ko(429, { code: 'too_many_requests', message: 'x', params: { retry_after: 42 } }),
        });

        await store.ensure({ api });
        const done = await store.rotate(ctx(api));

        assert.equal(done, false);
        assert.equal(store.notice, 'Espera 42 segundos.');
        assert.equal(store.card.token, 'JW0X3K9MABCDEFGH1234');
    });

    test('una red caída no es un rechazo del servidor: aviso genérico, nada cambia', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()), 'POST /me/card/rotate': ko(0, null, true) });

        await store.ensure({ api });
        await store.rotate(ctx(api));

        assert.equal(store.notice, 'Espera un minuto.');
        assert.equal(store.done, false);
    });

    test('la sesión perdida al renovar se marca como caducada', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()), 'POST /me/card/rotate': ko(401, { code: 'unauthenticated', message: '' }) });

        await store.ensure({ api });
        await store.rotate(ctx(api));

        assert.equal(store.expired, true);
    });

    test('reset() deja los avisos en blanco sin olvidar el carné', async () => {
        const store = useCardStore();
        const api = fakeApi({ 'GET /me/card': ok(card()), 'POST /me/card/rotate': ko(0, null, true) });

        await store.ensure({ api });
        await store.rotate(ctx(api));
        store.reset();

        assert.equal(store.notice, '');
        assert.equal(store.loaded, true);
    });
});
