import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useProfileStore } from './profile.js';

const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };

function fakeApi(respuestas) {
    const llamadas = [];
    const responder = (method) => async (url, body) => {
        llamadas.push({ method, url, body });
        const key = method + ' ' + url;

        return respuestas[key] ?? respuestas[url] ?? { ok: false, status: 500, data: null, error: null, offline: false };
    };

    return { llamadas, get: responder('GET'), post: responder('POST'), patch: responder('PATCH'), delete: responder('DELETE') };
}

const PERFIL = { id: 7, name: 'Ana', email: 'ana@x.test', phone: '600', locale: 'es', pending_email: null };
const ok = (data, status = 200) => ({ ok: true, status, data, error: null, offline: false });

const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

describe('cargar el perfil', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('lo guarda CRUDO y no lo repide al volver', async () => {
        const store = useProfileStore();
        const api = fakeApi({ '/me': ok(PERFIL) });

        await store.ensure(ctx(api));
        await store.ensure(ctx(api));

        assert.deepEqual(store.user, PERFIL);
        assert.equal(api.llamadas.length, 1, 'volver a la zona ha vuelto a pedir el perfil');
    });

    test('un 401 al cargar se dice como sesión perdida', async () => {
        const store = useProfileStore();

        await store.ensure(ctx(fakeApi({ '/me': { ok: false, status: 401, data: null, error: null, offline: false } })));

        assert.equal(store.expired, true);
        assert.equal(store.loaded, false);
    });
});

describe('guardar el perfil', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ sin cambio de correo NO manda la contraseña', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'PATCH /me': ok(PERFIL) });

        await store.apply({ name: 'Ana', email: 'ana@x.test', phone: '611', locale: 'es', currentPassword: '' }, ctx(api));

        // El contrato la declara opcional porque solo hace falta al cambiar el correo; mandarla vacía
        // la convertiría en un 422 por un campo que el cliente no tenía por qué rellenar.
        assert.deepEqual(api.llamadas[0].body, { name: 'Ana', phone: '611', locale: 'es', email: 'ana@x.test' });
        assert.equal(api.llamadas[0].method, 'PATCH');
    });

    test('con cambio de correo SÍ la manda', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'PATCH /me': ok(PERFIL) });

        await store.apply({ name: 'Ana', email: 'nueva@x.test', phone: '600', locale: 'es', currentPassword: 'secreta' }, ctx(api));

        assert.equal(api.llamadas[0].body.current_password, 'secreta');
    });

    test('⚠️ la respuesta REEMPLAZA el perfil: pedir un cambio de correo no cuesta otra petición', async () => {
        const store = useProfileStore();
        const conPendiente = { ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T11:00:00Z' };
        const api = fakeApi({ 'PATCH /me': ok(conPendiente) });

        await store.apply({ name: 'Ana', email: 'nueva@x.test', phone: '600', locale: 'es', currentPassword: 'secreta' }, ctx(api));

        assert.equal(store.user.pending_email, 'nueva@x.test');
        assert.equal(api.llamadas.length, 1, 'ha hecho una segunda petición para enterarse de lo que ya sabía');
    });

    test('la contraseña equivocada llega al campo y el perfil NO se toca', async () => {
        const store = useProfileStore();
        const api = fakeApi({ '/me': ok(PERFIL) });
        await store.ensure(ctx(api));

        api.patch = async () => ({
            ok: false, status: 422, data: null, offline: false,
            error: { code: 'validation_failed', message: '', fields: { current_password: ['No es correcta.'] } },
        });

        await store.apply({ name: 'Otro', email: 'nueva@x.test', phone: '600', locale: 'es', currentPassword: 'mal' }, ctx(api));

        assert.equal(store.fields.current_password[0], 'No es correcta.');
        assert.equal(store.user.name, 'Ana', 'el perfil de pantalla se ha movido con un guardado que falló');
    });
});

describe('el ciclo del correo pendiente', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ cancelar RELEE el perfil: su 204 no dice cómo quedó', async () => {
        const store = useProfileStore();
        const conPendiente = { ...PERFIL, pending_email: 'nueva@x.test' };
        const api = fakeApi({ '/me': ok(conPendiente), 'DELETE /me/pending-email': ok(null, 204) });

        await store.ensure(ctx(api));
        assert.equal(store.user.pending_email, 'nueva@x.test');

        api.get = async () => ok({ ...PERFIL, pending_email: null });
        await store.cancelPending(ctx(api));

        // Sin la relectura, la pantalla seguiría pintando el bloque de «cambio pendiente» sobre algo
        // que ya no existe.
        assert.equal(store.user.pending_email, null);
    });

    test('reenviar también relee, porque resella la caducidad', async () => {
        const store = useProfileStore();
        const api = fakeApi({
            '/me': ok({ ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T11:00:00Z' }),
            'POST /me/pending-email/resend': ok(null, 204),
        });

        await store.ensure(ctx(api));
        api.get = async () => ok({ ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T12:00:00Z' });

        await store.resendPending(ctx(api));

        assert.equal(store.user.pending_email_expires_at, '2026-08-22T12:00:00Z');
    });

    test('el cooldown del reenvío se enseña con su espera', async () => {
        const store = useProfileStore();
        const api = fakeApi({
            'POST /me/pending-email/resend': {
                ok: false, status: 429, data: null, offline: false,
                error: { code: 'too_many_requests', message: '', params: { retry_after: 42 } },
            },
        });

        await store.resendPending(ctx(api));

        assert.equal(store.notice, 'Espera 42 segundos.');
    });
});

describe('el velo', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ se quita aunque la llamada REVIENTE', async () => {
        const store = useProfileStore();
        const api = fakeApi({});
        api.patch = async () => { throw new Error('boom'); };

        await assert.rejects(() => store.apply({ name: '', email: '', phone: '', locale: '' }, ctx(api)));

        assert.equal(store.busy, false);
    });
});
