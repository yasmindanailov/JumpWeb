import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useCredentialsStore } from './credentials.js';

const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };

function fakeApi(respuestas) {
    const llamadas = [];

    const responder = (method) => async (url, body) => {
        llamadas.push({ method, url, body });

        return respuestas[url] ?? { ok: false, status: 500, data: null, error: null, offline: false };
    };

    return { llamadas, post: responder('POST'), get: responder('GET'), delete: responder('DELETE') };
}

const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

const WRONG_CODE = { ok: false, status: 422, data: null, offline: false, error: { code: 'validation_failed', message: '', fields: { code: ['El código no es correcto o ha caducado. Pide otro.'] } } };

describe('cerrar las demás sesiones (con un código: A4b, `#813`)', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('llama a su endpoint con el CÓDIGO y nada más: ni rastro de la contraseña', async () => {
        const store = useCredentialsStore();
        const api = fakeApi({ '/me/sessions/revoke-others': { ok: true, status: 204, data: null, error: null } });

        const ok = await store.revokeOtherSessions({ code: '482913' }, ctx(api));

        assert.equal(ok, true);
        assert.equal(api.llamadas[0].method, 'POST');
        assert.deepEqual(api.llamadas[0].body, { code: '482913' });
        assert.equal(store.done, true);
        assert.equal(store.notice, '');
        assert.deepEqual(store.fields, {});
        assert.equal(store.busy, false);
    });

    test('un código que no vale vuelve por SU campo, y no se da por hecho', async () => {
        const store = useCredentialsStore();

        const ok = await store.revokeOtherSessions({ code: '000000' }, ctx(fakeApi({ '/me/sessions/revoke-others': WRONG_CODE })));

        assert.equal(ok, false);
        assert.equal(store.fields.code[0], 'El código no es correcto o ha caducado. Pide otro.');
        assert.equal(store.done, false);
    });

    test('«cambiar la contraseña» ya no existe en el store', () => {
        assert.equal(useCredentialsStore().changePassword, undefined);
    });
});

describe('desvincular una cuenta externa', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda el código en el CUERPO del DELETE y relee la lista al salir bien', async () => {
        const store = useCredentialsStore();
        store.identities = [{ provider: 'google' }];
        const api = fakeApi({
            '/me/identities/google': { ok: true, status: 204, data: null, error: null },
            '/me/identities': { ok: true, status: 200, data: { data: [] }, error: null },
        });

        const ok = await store.unlinkIdentity('google', { code: '482913' }, ctx(api));

        assert.equal(ok, true);
        assert.deepEqual(api.llamadas[0], { method: 'DELETE', url: '/me/identities/google', body: { code: '482913' } });
        assert.deepEqual(store.identities, [], 'la foto vieja enseñaría el vínculo que se acaba de quitar');
    });

    test('con el código malo la lista NO se relee: el vínculo sigue ahí', async () => {
        const store = useCredentialsStore();
        store.identities = [{ provider: 'google' }];
        const api = fakeApi({ '/me/identities/google': WRONG_CODE });

        assert.equal(await store.unlinkIdentity('google', { code: '000000' }, ctx(api)), false);
        assert.equal(api.llamadas.length, 1);
        assert.deepEqual(store.identities, [{ provider: 'google' }]);
    });
});

describe('el estado entre intentos', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ el error anterior se limpia ANTES de llamar, no después', async () => {
        const store = useCredentialsStore();

        await store.revokeOtherSessions({ code: '000000' }, ctx(fakeApi({ '/me/sessions/revoke-others': WRONG_CODE })));
        assert.ok(store.fields.code);

        // Si no se limpiara antes, el error viejo seguiría en pantalla mientras la petición nueva
        // está en vuelo, y el cliente lo leería como el resultado del intento que acaba de hacer.
        let durante = null;
        const api = fakeApi({});
        api.post = async () => {
            durante = { fields: { ...store.fields }, notice: store.notice, busy: store.busy };

            return { ok: true, status: 204, data: null, error: null };
        };

        await store.revokeOtherSessions({ code: '482913' }, ctx(api));

        assert.deepEqual(durante.fields, {}, 'el error anterior seguía en pantalla durante la llamada');
        assert.equal(durante.busy, true, 'el velo no estaba puesto mientras se llamaba');
    });

    test('`reset()` deja el estado como si nunca se hubiera intentado nada', async () => {
        const store = useCredentialsStore();

        await store.revokeOtherSessions({ code: '000000' }, ctx(fakeApi({})));
        store.reset();

        assert.deepEqual(store.fields, {});
        assert.equal(store.notice, '');
        assert.equal(store.done, false);
        assert.equal(store.expired, false);
    });

    test('⚠️ el velo se quita aunque la llamada REVIENTE', async () => {
        const store = useCredentialsStore();
        const api = fakeApi({});
        api.post = async () => { throw new Error('boom'); };

        await assert.rejects(() => store.revokeOtherSessions({ code: '482913' }, ctx(api)));

        // Sin el `finally`, una excepción dejaría el velo girando para siempre y la pantalla muerta.
        assert.equal(store.busy, false);
    });
});
