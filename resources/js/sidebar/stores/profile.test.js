import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { profileBody, useProfileStore } from './profile.js';

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

    test('⚠️ sin cambio de correo NO manda código', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'PATCH /me': ok(PERFIL) });

        await store.apply({ name: 'Ana', email: 'ana@x.test', phone: '611', locale: 'es', code: '' }, ctx(api));

        // Solo hace falta al cambiar el correo; mandarlo vacío lo convertiría en un 422 por un campo que el cliente no tenía
        // por qué rellenar.
        assert.deepEqual(api.llamadas[0].body, { name: 'Ana', phone: '611', locale: 'es', email: 'ana@x.test' });
        assert.equal(api.llamadas[0].method, 'PATCH');
    });

    test('con cambio de correo SÍ manda el código, y nunca la contraseña (A4b, `#813`)', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'PATCH /me': ok(PERFIL) });

        await store.apply({ name: 'Ana', email: 'nueva@x.test', phone: '600', locale: 'es', code: '482913', currentPassword: 'vieja' }, ctx(api));

        assert.equal(api.llamadas[0].body.code, '482913');
        assert.equal('current_password' in api.llamadas[0].body, false);
    });

    test('⚠️ la respuesta REEMPLAZA el perfil: pedir un cambio de correo no cuesta otra petición', async () => {
        const store = useProfileStore();
        const conPendiente = { ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T11:00:00Z' };
        const api = fakeApi({ 'PATCH /me': ok(conPendiente) });

        await store.apply({ name: 'Ana', email: 'nueva@x.test', phone: '600', locale: 'es', code: '482913' }, ctx(api));

        assert.equal(store.user.pending_email, 'nueva@x.test');
        assert.equal(api.llamadas.length, 1, 'ha hecho una segunda petición para enterarse de lo que ya sabía');
    });

    test('el código que no vale llega a su campo y el perfil NO se toca', async () => {
        const store = useProfileStore();
        const api = fakeApi({ '/me': ok(PERFIL) });
        await store.ensure(ctx(api));

        api.patch = async () => ({
            ok: false, status: 422, data: null, offline: false,
            error: { code: 'validation_failed', message: '', fields: { code: ['El código no es correcto o ha caducado. Pide otro.'] } },
        });

        await store.apply({ name: 'Otro', email: 'nueva@x.test', phone: '600', locale: 'es', code: '000000' }, ctx(api));

        assert.equal(store.fields.code[0], 'El código no es correcto o ha caducado. Pide otro.');
        assert.equal(store.user.name, 'Ana', 'el perfil de pantalla se ha movido con un guardado que falló');
    });
});

describe('la fecha de nacimiento al guardar (TP·1, `#792`)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    const FORM = { name: 'Ana', email: 'ana@x.test', phone: '611', locale: 'es' };

    async function sent(form) {
        const api = fakeApi({ 'PATCH /me': ok(PERFIL) });

        await useProfileStore().apply(form, ctx(api));

        return api.llamadas[0].body;
    }

    test('con fecha, viaja tal cual', async () => {
        assert.equal((await sent({ ...FORM, born_on: '1985-01-02' })).born_on, '1985-01-02');
    });

    test('vaciada en «Tus datos», viaja como `null` y la borra (vacía sería un 422 por esquema)', async () => {
        assert.equal((await sent({ ...FORM, born_on: '' })).born_on, null);
        assert.equal((await sent({ ...FORM, born_on: '  ' })).born_on, null);
    });

    test('⚠️⚠️ sin la clave NO viaja: es como guarda la ISLA, y el servidor no la toca', async () => {
        assert.equal('born_on' in (await sent(FORM)), false);
        assert.equal('born_on' in profileBody(FORM), false);
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

    test('`refresh()` relee el perfil aunque ya lo tenga (pedir otro código resella la caducidad)', async () => {
        const store = useProfileStore();
        const api = fakeApi({ '/me': ok({ ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T11:00:00Z' }) });

        await store.ensure(ctx(api));
        api.get = async () => ok({ ...PERFIL, pending_email: 'nueva@x.test', pending_email_expires_at: '2026-08-22T12:00:00Z' });
        await store.refresh(ctx(api));

        assert.equal(store.user.pending_email_expires_at, '2026-08-22T12:00:00Z');
    });
});

describe('confirmar el correo NUEVO con su código (A2b; en el cajón, la A4b)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda el código al endpoint del correo nuevo y coloca el perfil que devuelve', async () => {
        const store = useProfileStore();
        const cambiado = { ...PERFIL, email: 'nueva@x.test' };
        const api = fakeApi({ 'POST /me/pending-email/confirm': ok(cambiado), 'GET /me/account-context': ok({ data: {} }) });

        const done = await store.confirmPending('482913', ctx(api));

        assert.equal(done, true);
        assert.deepEqual(api.llamadas[0], { method: 'POST', url: '/me/pending-email/confirm', body: { code: '482913' } });
        assert.equal(store.user.email, 'nueva@x.test');
    });

    test('el código que no vale vuelve por su campo', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'POST /me/pending-email/confirm': {
            ok: false, status: 422, data: null, offline: false,
            error: { code: 'validation_failed', message: '', fields: { code: ['El código no es correcto o ha caducado. Pide otro.'] } },
        } });

        assert.equal(await store.confirmPending('000000', ctx(api)), false);
        assert.equal(store.fields.code[0], 'El código no es correcto o ha caducado. Pide otro.');
    });

    /**
     * ⚠️ **Otra cuenta tomó el correo mientras tanto**: el «no» llega sobre `email`, que en «Tus datos» es el campo del correo
     * de AHORA; bajo él diría que el tuyo está ocupado. Va arriba, como la isla.
     */
    test('el correo ya ocupado por otra cuenta va ARRIBA, no bajo el correo de ahora', async () => {
        const store = useProfileStore();
        const api = fakeApi({ 'POST /me/pending-email/confirm': {
            ok: false, status: 422, data: null, offline: false,
            error: { code: 'validation_failed', message: '', fields: { email: ['Ese email ya está en uso.'] } },
        } });

        await store.confirmPending('482913', ctx(api));

        assert.equal(store.notice, 'Ese email ya está en uso.');
        assert.equal(store.fields.email, undefined);
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
