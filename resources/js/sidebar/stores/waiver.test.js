import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useWaiverStore } from './waiver.js';

/**
 * `stores/waiver.js` — Fase 6 · waiver (`specs/waiver-probatorio.md` §4.4, §9.9).
 *
 * Lo que se fija: que el texto y el estado se piden UNA vez, que al aceptar viaja **el id que el
 * servidor sirvió**, y que un `409 waiver_document_stale` hace releer en vez de insistir.
 */
const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };

const LEGAL = { mode: 'interno', document: { id: 7, version: 2, locale: 'es', title: 'Exención', sections: [{ h: 'Riesgo', p: 'Saltar implica riesgos.' }], published_at: null } };
const UNSIGNED = { mode: 'interno', signed: false, outdated: false, accepted_at: null, accepted_label: null, version: null, current_document_id: 7, signatures: [] };
const SIGNED = { ...UNSIGNED, signed: true, version: 2, signatures: [{ id: 3, version_label: 'v2·es', accepted_at: 'x', accepted_label: '26/08/2026', channel: 'web', declared: false, subject: 'holder', pdf_url: '/api/v1/me/waiver/3/pdf' }] };

const ok = (data, status = 200) => ({ ok: true, status, data, error: null, offline: false });
const fail = (status, error) => ({ ok: false, status, data: null, error, offline: false });

/** Un cliente de mentira que responde por URL y recuerda las llamadas; las respuestas pueden ser colas. */
function fakeApi(responses) {
    const calls = [];
    const next = (url) => {
        const queue = responses[url];
        if (Array.isArray(queue)) return queue.length > 1 ? queue.shift() : queue[0];

        return queue ?? fail(500, { code: 'server_error', message: 'boom' });
    };

    return {
        calls,
        get: async (url) => { calls.push({ method: 'GET', url }); return next(url); },
        post: async (url, body) => { calls.push({ method: 'POST', url, body }); return next(url); },
    };
}

const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

describe('leer el texto y el estado', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('el texto se pide una vez y se cachea', async () => {
        const store = useWaiverStore();
        const api = fakeApi({ '/legal/waiver': ok(LEGAL) });

        await store.ensureLegal({ api });
        await store.ensureLegal({ api });

        assert.equal(api.calls.length, 1);
        assert.deepEqual(store.document, LEGAL.document);
        assert.equal(store.currentDocumentId, 7);
    });

    test('sin texto que firmar el documento es null y no hay id', async () => {
        const store = useWaiverStore();
        const api = fakeApi({ '/legal/waiver': ok({ mode: 'externo', document: null }) });

        await store.ensureLegal({ api });

        assert.equal(store.document, null);
        assert.equal(store.currentDocumentId, null);
    });

    test('un fallo de lectura no rompe nada: se queda sin texto y se puede reintentar', async () => {
        const store = useWaiverStore();
        const api = { get: async () => { throw new Error('sin red'); } };

        await store.ensureLegal({ api });

        assert.equal(store.legal, null);
        assert.equal(store.legalLoading, false);
    });

    test('el estado propio manda sobre el texto público para el id que se acepta', async () => {
        const store = useWaiverStore();
        const api = fakeApi({ '/legal/waiver': ok(LEGAL), '/me/waiver': ok({ ...UNSIGNED, current_document_id: 9 }) });

        await store.ensureLegal({ api });
        await store.ensureStatus({ api });

        assert.equal(store.currentDocumentId, 9);
        assert.equal(store.signed, false);
    });
});

describe('aceptar', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda el id servido y coloca el estado nuevo que devuelve el servidor', async () => {
        const store = useWaiverStore();
        const api = fakeApi({ '/me/waiver': [ok(UNSIGNED), ok(SIGNED, 201)] });

        await store.ensureStatus({ api });
        const result = await store.accept(ctx(api));

        assert.equal(result, true);
        assert.deepEqual(api.calls[1], { method: 'POST', url: '/me/waiver', body: { document_id: 7 } });
        assert.equal(store.signed, true);
        assert.equal(store.signatures.length, 1);
        assert.equal(store.done, true);
        assert.equal(store.busy, false);
    });

    test('sin id no hay nada que aceptar', async () => {
        const store = useWaiverStore();
        const api = fakeApi({});

        assert.equal(await store.accept(ctx(api)), false);
        assert.equal(api.calls.length, 0);
    });

    /**
     * ⚠️ El texto cambió entre servirlo y aceptarlo: el servidor NO acepta el viejo. Aquí se olvida lo
     * leído y se vuelve a pedir, para que quien firme lea lo que va a firmar — insistir con el mismo id
     * sería exactamente lo que la spec prohíbe.
     */
    test('un texto caducado hace releer en vez de insistir', async () => {
        const store = useWaiverStore();
        const stale = fail(409, { code: 'waiver_document_stale', message: 'El texto ha cambiado.' });
        const api = fakeApi({
            '/legal/waiver': [ok(LEGAL), ok({ ...LEGAL, document: { ...LEGAL.document, id: 8, version: 3 } })],
            '/me/waiver': [ok(UNSIGNED), stale, ok({ ...UNSIGNED, current_document_id: 8 })],
        });

        await store.ensureLegal({ api });
        await store.ensureStatus({ api });
        const result = await store.accept(ctx(api));

        assert.equal(result, false);
        assert.equal(store.notice, 'El texto ha cambiado.');
        assert.equal(store.currentDocumentId, 8, 'tras el 409 el id vigente es el NUEVO');
        assert.equal(store.document.version, 3);
    });

    test('fuera del modo interno el servidor dice que no y se enseña tal cual', async () => {
        const store = useWaiverStore();
        const api = fakeApi({
            '/legal/waiver': ok(LEGAL),
            '/me/waiver': [ok(UNSIGNED), fail(409, { code: 'waiver_not_internal', message: 'Aquí no.' })],
        });

        await store.ensureLegal({ api });
        await store.ensureStatus({ api });

        assert.equal(await store.accept(ctx(api)), false);
        assert.equal(store.notice, 'Aquí no.');
        assert.equal(api.calls.length, 3, 'un 409 que no es «caducado» no relee');
    });

    test('reset() deja los avisos limpios y conserva lo leído', async () => {
        const store = useWaiverStore();
        const api = fakeApi({ '/legal/waiver': ok(LEGAL) });

        await store.ensureLegal({ api });
        store.notice = 'algo';
        store.reset();

        assert.equal(store.notice, '');
        assert.deepEqual(store.document, LEGAL.document);
    });
});
