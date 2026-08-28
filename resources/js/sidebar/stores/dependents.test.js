import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useDependentsStore } from './dependents.js';

/**
 * La red de los menores a cargo en el cliente (Fase 6 · C, tanda 3).
 *
 * ⚠️ Aquí no se prueba ninguna REGLA: el tope, la edad, la minoría y qué hace «quitar» los decide el
 * SERVIDOR (`CE-4`; `DependentRegistryTest`, `MeDependentsTest`, `MeDependentWaiverTest`). Lo que se
 * sostiene es lo que el cliente sí decide: **qué manda**, **qué coloca** y **qué le dice a la pantalla**.
 */

const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };
const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

const lucas = (waiver = {}) => ({ id: 1, name: 'Lucas', born_on: '2017-03-12', age: 9, is_minor: true, adult_from: '2035-03-12', waiver: { mode: 'interno', signed: false, outdated: false, ...waiver } });
const ok = (data, status = 200) => ({ ok: true, status, data, error: null, offline: false });
const ko = (status, error) => ({ ok: false, status, data: null, error, offline: false });

function fakeApi(responses) {
    const calls = [];
    const respond = (method) => async (url, body) => {
        calls.push({ method, url, body });

        return responses[`${method} ${url}`] ?? ko(500, null);
    };

    return { calls, get: respond('GET'), post: respond('POST'), delete: respond('DELETE') };
}

describe('lo que el EMBUDO lee de la lista (Fase 6 · tanda 4)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('las opciones, los ids asignables y el mapa por id salen de la lista viva', async () => {
        const store = useDependentsStore();
        const signed = lucas({ signed: true });
        const unsigned = { ...lucas(), id: 2, name: 'Vera' };
        const api = fakeApi({ 'GET /me/dependents': ok({ data: [signed, unsigned], meta: { total: 2 } }) });

        await store.ensure({ api });

        const options = store.optionsFor({ dependents: { age: ':age años' } });
        assert.deepEqual(options.map((o) => [o.id, o.assignable]), [[1, true], [2, false]]);
        // ⚠️ Nombre y edad viajan POR SEPARADO desde el 2026-08-28 (`menores-a-cargo.md` §9.11 D·2):
        // el `label` de una sola cadena se retiró porque impedía al selector darles peso distinto.
        assert.equal(options[0].name, 'Lucas');
        assert.equal(options[0].age, '9 años');
        assert.equal(options[0].label, undefined, 'la etiqueta fundida ya no existe');
        assert.deepEqual(store.assignable, [1]);
        assert.deepEqual(store.byId, { 1: { id: 1, name: 'Lucas' }, 2: { id: 2, name: 'Vera' } });

        store.invalidate();
        assert.deepEqual(store.assignable, [], 'sin lista no hay nadie asignable');
    });
});

describe('la lista', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('se pide una vez y se coloca tal cual', async () => {
        const store = useDependentsStore();
        const api = fakeApi({ 'GET /me/dependents': ok({ data: [lucas()], meta: { total: 1 } }) });

        await store.ensure({ api });
        await store.ensure({ api });

        assert.equal(store.loaded, true);
        assert.deepEqual(store.items.map((d) => d.name), ['Lucas']);
        assert.equal(api.calls.length, 1, 'ensure() pide solo si no la tiene');
    });

    test('una sesión perdida se marca como caducada, no como lista vacía', async () => {
        const store = useDependentsStore();
        await store.ensure({ api: fakeApi({ 'GET /me/dependents': ko(401, { code: 'unauthenticated', message: '' }) }) });

        assert.equal(store.expired, true);
        assert.equal(store.loaded, false);
    });

    test('dos ensure() a la vez esperan a la MISMA petición: una llamada, y la segunda vuelve con la lista', async () => {
        const store = useDependentsStore();
        let release;
        const api = { calls: [], get(path) { this.calls.push(path); return new Promise((resolve) => { release = () => resolve(ok({ data: [{ id: 7, name: 'Lucas', age: 9, waiver: { signed: true, outdated: false } }] })); }); } };

        const first = store.ensure({ api });
        const second = store.ensure({ api });
        assert.equal(store.listLoading, true);
        release();
        await Promise.all([first, second]);

        assert.equal(api.calls.length, 1, 'la segunda llamada NO pide otra vez');
        assert.equal(store.loaded, true);
        assert.equal(store.listLoading, false);
        assert.equal(store.inflight, null);
    });

    test('invalidate() hace que el siguiente ensure() vuelva a preguntar', async () => {
        const store = useDependentsStore();
        const api = fakeApi({ 'GET /me/dependents': ok({ data: [], meta: { total: 0 } }) });
        await store.ensure({ api });
        store.invalidate();
        await store.ensure({ api });

        assert.equal(api.calls.length, 2);
    });
});

describe('declarar un menor', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda SOLO nombre y fecha, y añade la respuesta al final', async () => {
        const store = useDependentsStore();
        store.list = [lucas()];
        const vera = { ...lucas(), id: 2, name: 'Vera' };
        const api = fakeApi({ 'POST /me/dependents': ok(vera, 201) });

        const done = await store.add({ name: 'Vera', born_on: '2019-11-02', extra: 'no' }, ctx(api));

        assert.equal(done, true);
        assert.deepEqual(api.calls[0], { method: 'POST', url: '/me/dependents', body: { name: 'Vera', born_on: '2019-11-02' } });
        assert.deepEqual(store.items.map((d) => d.name), ['Lucas', 'Vera']);
        assert.equal(store.done, true);
    });

    test('un 422 con campos se coloca por campo; un rechazo de dominio, como aviso con el texto del servidor', async () => {
        const store = useDependentsStore();
        store.list = [];

        await store.add({ name: '', born_on: 'x' }, ctx(fakeApi({ 'POST /me/dependents': ko(422, { code: 'validation_failed', message: 'Revisa', fields: { born_on: ['Fecha no válida'] } }) })));
        assert.deepEqual(store.fields, { born_on: ['Fecha no válida'] });
        assert.equal(store.notice, '');

        await store.add({ name: 'Ana', born_on: '2000-01-01' }, ctx(fakeApi({ 'POST /me/dependents': ko(422, { code: 'dependent_not_minor', message: 'Tiene que ser menor.' }) })));
        assert.equal(store.notice, 'Tiene que ser menor.');
        assert.deepEqual(store.fields, {});
        assert.equal(store.items.length, 0, 'nada se añadió');
    });

    test('el tope de la cuenta llega como aviso, con el texto que el servidor ya interpoló', async () => {
        const store = useDependentsStore();
        await store.add({ name: 'Uno', born_on: '2017-01-01' }, ctx(fakeApi({ 'POST /me/dependents': ko(422, { code: 'dependents_limit_reached', message: 'Máximo 20.', params: { max: 20 } }) })));

        assert.equal(store.notice, 'Máximo 20.');
    });
});

describe('quitar un menor', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('borra de la lista al salir bien y marca cuál está en vuelo', async () => {
        const store = useDependentsStore();
        store.list = [lucas(), { ...lucas(), id: 2, name: 'Vera' }];
        const api = fakeApi({ 'DELETE /me/dependents/1': ok(null, 204) });

        const promise = store.remove(1, ctx(api));
        assert.equal(store.removingId, 1);
        assert.equal(await promise, true);

        assert.equal(store.removingId, null);
        assert.deepEqual(store.items.map((d) => d.id), [2]);
        assert.deepEqual(api.calls[0], { method: 'DELETE', url: '/me/dependents/1', body: undefined });
    });

    test('un 404 (ajeno, retirado o inexistente) se enseña y no toca la lista', async () => {
        const store = useDependentsStore();
        store.list = [lucas()];

        const done = await store.remove(9, ctx(fakeApi({ 'DELETE /me/dependents/9': ko(404, { code: 'not_found', message: 'No está.' }) })));

        assert.equal(done, false);
        assert.equal(store.notice, 'No está.');
        assert.equal(store.items.length, 1);
    });
});

describe('firmar la exención en su nombre', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda el id del texto ENSEÑADO y coloca al menor firmado en su sitio', async () => {
        const store = useDependentsStore();
        store.list = [lucas(), { ...lucas(), id: 2, name: 'Vera' }];
        const api = fakeApi({ 'POST /me/dependents/1/waiver': ok(lucas({ signed: true }), 201) });

        const result = await store.signWaiver({ id: 1, documentId: 31 }, ctx(api));

        assert.deepEqual(result, { ok: true, stale: false });
        assert.deepEqual(api.calls[0], { method: 'POST', url: '/me/dependents/1/waiver', body: { document_id: 31 } });
        assert.deepEqual(store.items.map((d) => [d.id, d.waiver.signed]), [[1, true], [2, false]]);
        assert.equal(store.signedId, 1);
        assert.equal(store.signingId, null);
    });

    test('⚠️ sin texto enseñado no se firma nada: ni una petición', async () => {
        const store = useDependentsStore();
        const api = fakeApi({});

        assert.deepEqual(await store.signWaiver({ id: 1, documentId: null }, ctx(api)), { ok: false, stale: false });
        assert.equal(api.calls.length, 0);
    });

    test('el texto caducado (409) se devuelve como `stale` para que quien llame relea', async () => {
        const store = useDependentsStore();
        store.list = [lucas()];
        const api = fakeApi({ 'POST /me/dependents/1/waiver': ko(409, { code: 'waiver_document_stale', message: 'El texto cambió.' }) });

        const result = await store.signWaiver({ id: 1, documentId: 30 }, ctx(api));

        assert.deepEqual(result, { ok: false, stale: true });
        assert.equal(store.notice, 'El texto cambió.');
        assert.equal(store.items[0].waiver.signed, false);
        assert.equal(store.signedId, null);
    });

    test('los otros 409 y el 422 de «ya tiene 18» se enseñan sin ser `stale`', async () => {
        const store = useDependentsStore();
        store.list = [lucas()];

        const unverified = await store.signWaiver({ id: 1, documentId: 30 }, ctx(fakeApi({ 'POST /me/dependents/1/waiver': ko(409, { code: 'waiver_email_unverified', message: 'Verifica tu correo.' }) })));
        assert.deepEqual(unverified, { ok: false, stale: false });
        assert.equal(store.notice, 'Verifica tu correo.');

        const adult = await store.signWaiver({ id: 1, documentId: 30 }, ctx(fakeApi({ 'POST /me/dependents/1/waiver': ko(422, { code: 'dependent_not_minor', message: 'Ya tiene 18.' }) })));
        assert.deepEqual(adult, { ok: false, stale: false });
        assert.equal(store.notice, 'Ya tiene 18.');
    });

    test('reset() deja la zona como recién abierta, sin olvidar la lista', () => {
        const store = useDependentsStore();
        store.list = [lucas()];
        store.notice = 'algo';
        store.signedId = 1;

        store.reset();

        assert.equal(store.notice, '');
        assert.equal(store.signedId, null);
        assert.equal(store.items.length, 1);
    });

    /**
     * ⚠️ La zona llama a `forget()` al DESPLEGAR el alta (2026-08-28): sin él, el error del intento
     * anterior se leería bajo un formulario recién abierto y vacío, como si fuera de éste.
     */
    test('forget() borra el veredicto del último intento y CONSERVA la sesión caducada', () => {
        const store = useDependentsStore();
        store.list = [lucas()];
        store.fields = { name: ['Ya lo has declarado.'] };
        store.notice = 'Has alcanzado el máximo (20).';
        store.done = true;
        store.expired = true;

        store.forget();

        assert.deepEqual(store.fields, {});
        assert.equal(store.notice, '');
        assert.equal(store.done, false);
        assert.equal(store.expired, true, 'esconder una sesión caducada dejaría al cliente tecleando en balde');
        assert.equal(store.items.length, 1, 'no toca la lista');
    });
});
