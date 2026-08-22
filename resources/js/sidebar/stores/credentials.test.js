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

    return { llamadas, put: responder('PUT'), post: responder('POST') };
}

const ctx = (api) => ({ api, messages: MESSAGES, auth: AUTH });

describe('cambiar la contraseña', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda lo que el contrato pide, y NADA más', () => {
        const store = useCredentialsStore();
        const api = fakeApi({ '/me/password': { ok: true, status: 204, data: null, error: null } });

        return store.changePassword({ currentPassword: 'vieja', password: 'nueva' }, ctx(api)).then(() => {
            // ⚠️ Sin `password_confirmation`: repetir la contraseña es de ESTE formulario, no del
            // contrato. Mandarlo haría que el servidor rechazara el cuerpo por propiedad de más.
            assert.deepEqual(api.llamadas[0].body, { current_password: 'vieja', password: 'nueva' });
            assert.equal(api.llamadas[0].method, 'PUT');
        });
    });

    test('al salir bien, lo dice y no deja avisos', async () => {
        const store = useCredentialsStore();

        const ok = await store.changePassword({ currentPassword: 'v', password: 'n' },
            ctx(fakeApi({ '/me/password': { ok: true, status: 204, data: null, error: null } })));

        assert.equal(ok, true);
        assert.equal(store.done, true);
        assert.equal(store.notice, '');
        assert.deepEqual(store.fields, {});
        assert.equal(store.busy, false);
    });

    test('la contraseña equivocada llega al CAMPO', async () => {
        const store = useCredentialsStore();

        await store.changePassword({ currentPassword: 'mal', password: 'n' }, ctx(fakeApi({
            '/me/password': { ok: false, status: 422, data: null, offline: false, error: { code: 'validation_failed', message: '', fields: { current_password: ['No es correcta.'] } } },
        })));

        assert.equal(store.fields.current_password[0], 'No es correcta.');
        assert.equal(store.done, false);
    });
});

describe('cerrar las demás sesiones', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('llama a su endpoint con la contraseña y nada más', async () => {
        const store = useCredentialsStore();
        const api = fakeApi({ '/me/sessions/revoke-others': { ok: true, status: 204, data: null, error: null } });

        await store.revokeOtherSessions({ currentPassword: 'v' }, ctx(api));

        assert.equal(api.llamadas[0].method, 'POST');
        assert.deepEqual(api.llamadas[0].body, { current_password: 'v' });
        assert.equal(store.done, true);
    });
});

describe('el estado entre intentos', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ el error anterior se limpia ANTES de llamar, no después', async () => {
        const store = useCredentialsStore();

        await store.changePassword({ currentPassword: 'mal', password: 'n' }, ctx(fakeApi({
            '/me/password': { ok: false, status: 422, data: null, offline: false, error: { fields: { current_password: ['No es correcta.'] } } },
        })));
        assert.equal(store.fields.current_password[0], 'No es correcta.');

        // Si no se limpiara antes, el error viejo seguiría en pantalla mientras la petición nueva
        // está en vuelo, y el cliente lo leería como el resultado del intento que acaba de hacer.
        let durante = null;
        const api = fakeApi({});
        api.put = async () => {
            durante = { fields: { ...store.fields }, notice: store.notice, busy: store.busy };

            return { ok: true, status: 204, data: null, error: null };
        };

        await store.changePassword({ currentPassword: 'buena', password: 'n' }, ctx(api));

        assert.deepEqual(durante.fields, {}, 'el error anterior seguía en pantalla durante la llamada');
        assert.equal(durante.busy, true, 'el velo no estaba puesto mientras se llamaba');
    });

    test('`reset()` deja el estado como si nunca se hubiera intentado nada', async () => {
        const store = useCredentialsStore();

        await store.changePassword({ currentPassword: 'mal', password: 'n' }, ctx(fakeApi({})));
        store.reset();

        assert.deepEqual(store.fields, {});
        assert.equal(store.notice, '');
        assert.equal(store.done, false);
        assert.equal(store.expired, false);
    });

    test('⚠️ el velo se quita aunque la llamada REVIENTE', async () => {
        const store = useCredentialsStore();
        const api = fakeApi({});
        api.put = async () => { throw new Error('boom'); };

        await assert.rejects(() => store.changePassword({ currentPassword: 'v', password: 'n' }, ctx(api)));

        // Sin el `finally`, una excepción dejaría el velo girando para siempre y la pantalla muerta.
        assert.equal(store.busy, false);
    });
});
