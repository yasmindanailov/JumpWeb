import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { usePrivacyStore } from './privacy.js';

/**
 * La red de los dos derechos RGPD en el cliente (tanda 2 · paso 8).
 *
 * ⚠️ Aquí no se prueba ninguna REGLA: si la contraseña es correcta, qué lleva el documento y qué se
 * purga lo decide el SERVIDOR (`CE-4`, y lo cubre `MePrivacyTest`). Lo que se sostiene es lo que el
 * cliente sí decide: **qué manda**, **qué NO retiene** y **qué le dice a la pantalla**.
 */

const MESSAGES = { errors: { try_later: 'Espera un minuto.' } };
const AUTH = { throttle: 'Espera :seconds segundos.' };

const DOCUMENT = { exported_at: '2026-08-22T16:07:39+00:00', profile: { email: 'ana@ejemplo.test' } };

function fakeApi(respuestas) {
    const llamadas = [];

    const responder = (method) => async (url, body) => {
        llamadas.push({ method, url, body });

        return respuestas[url] ?? { ok: false, status: 500, data: null, error: null, offline: false };
    };

    return { llamadas, get: responder('GET'), delete: responder('DELETE') };
}

/** El DOM de mentira que `saveExport` necesita, con memoria de lo que se descargó. */
function fakeDom() {
    const saved = [];
    const link = { href: '', download: '', click() { saved.push(this.download); }, remove() {} };

    return {
        saved,
        dom: {
            doc: { body: { appendChild() {} }, createElement: () => link },
            urls: { createObjectURL: () => 'blob:x', revokeObjectURL() {} },
            blob: class { constructor(parts) { this.parts = parts; } },
        },
    };
}

const ctx = (api, dom = {}) => ({ api, messages: MESSAGES, auth: AUTH, dom });

describe('descargar mis datos', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('pide el documento y lo entrega como fichero', async () => {
        const store = usePrivacyStore();
        const api = fakeApi({ '/me/export': { ok: true, status: 200, data: DOCUMENT, error: null } });
        const { saved, dom } = fakeDom();

        const ok = await store.exportData(ctx(api, dom));

        assert.equal(ok, true);
        assert.deepEqual(api.llamadas, [{ method: 'GET', url: '/me/export', body: undefined }]);
        assert.deepEqual(saved, ['mis-datos-2026-08-22.json']);
        assert.equal(store.savedAs, 'mis-datos-2026-08-22.json');
    });

    /**
     * ⚠️⚠️ **RGPD: el documento NO se queda en el store.** Es la PII más densa del producto —perfil,
     * consentimientos con IP y las alergias de un menor—, y retenerlo la dejaría colgando de
     * cualquier cosa que inspeccione el estado. Es la misma regla por la que la cesta no persiste las
     * respuestas del evento.
     */
    test('y NO lo retiene: del documento solo queda el nombre del fichero', async () => {
        const store = usePrivacyStore();
        const { dom } = fakeDom();

        await store.exportData(ctx(fakeApi({ '/me/export': { ok: true, status: 200, data: DOCUMENT, error: null } }), dom));

        assert.equal(
            JSON.stringify(store.$state).includes('ana@ejemplo.test'), false,
            'el documento de portabilidad se ha quedado guardado en el store'
        );
    });

    test('si la sesión se perdió por el camino, lo dice y no descarga nada', async () => {
        const store = usePrivacyStore();
        const { saved, dom } = fakeDom();

        const ok = await store.exportData(ctx(
            fakeApi({ '/me/export': { ok: false, status: 401, data: null, error: { code: 'unauthenticated', message: '' } } }),
            dom,
        ));

        assert.equal(ok, false);
        assert.equal(store.expired, true);
        assert.deepEqual(saved, [], 'se ha descargado un fichero a partir de una respuesta de error');
        assert.equal(store.savedAs, '');
    });

    test('un intento nuevo borra la confirmación del anterior', async () => {
        const store = usePrivacyStore();
        const { dom } = fakeDom();

        await store.exportData(ctx(fakeApi({ '/me/export': { ok: true, status: 200, data: DOCUMENT, error: null } }), dom));
        assert.notEqual(store.savedAs, '');

        await store.exportData(ctx(fakeApi({}), dom));

        assert.equal(store.savedAs, '', 'la pantalla seguiría diciendo que se descargó un fichero que no se descargó');
    });
});

describe('borrar la cuenta', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('manda la contraseña en el CUERPO del DELETE, y nada más', async () => {
        const store = usePrivacyStore();
        const api = fakeApi({ '/me': { ok: true, status: 204, data: null, error: null } });

        const ok = await store.deleteAccount({ currentPassword: 'la-mia' }, ctx(api));

        assert.equal(ok, true);
        assert.equal(api.llamadas[0].method, 'DELETE');
        assert.equal(api.llamadas[0].url, '/me');
        // ⚠️ En el cuerpo y no en la URL: una contraseña por query acaba en los logs del servidor y
        // en el historial del navegador.
        assert.deepEqual(api.llamadas[0].body, { current_password: 'la-mia' });
    });

    test('la contraseña equivocada vuelve por su campo, y la cuenta sigue ahí', async () => {
        const store = usePrivacyStore();

        const ok = await store.deleteAccount({ currentPassword: 'no-es' }, ctx(fakeApi({
            '/me': {
                ok: false, status: 422, data: null, offline: false,
                error: { code: 'validation_failed', message: '', fields: { current_password: ['No es correcta.'] } },
            },
        })));

        assert.equal(ok, false);
        assert.equal(store.done, false);
        assert.deepEqual(store.fields, { current_password: ['No es correcta.'] });
    });

    test('el límite se enseña con el MISMO texto que el login, no con uno nuevo', async () => {
        const store = usePrivacyStore();

        await store.deleteAccount({ currentPassword: 'x' }, ctx(fakeApi({
            '/me': {
                ok: false, status: 429, data: null, offline: false,
                error: { code: 'too_many_requests', message: '', params: { retry_after: 42 } },
            },
        })));

        assert.equal(store.notice, 'Espera 42 segundos.');
    });

    /** Ni la contraseña de reconfirmación se queda en el estado: vive en el formulario y se va con él. */
    test('la contraseña no se guarda en el store', async () => {
        const store = usePrivacyStore();

        await store.deleteAccount({ currentPassword: 'secreto-larguísimo' },
            ctx(fakeApi({ '/me': { ok: true, status: 204, data: null, error: null } })));

        assert.equal(JSON.stringify(store.$state).includes('secreto-larguísimo'), false);
    });
});

describe('al entrar en la zona', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('se limpia lo que dijo el servidor la vez anterior', async () => {
        const store = usePrivacyStore();
        const { dom } = fakeDom();

        await store.exportData(ctx(fakeApi({ '/me/export': { ok: true, status: 200, data: DOCUMENT, error: null } }), dom));
        store.notice = 'algo viejo';

        store.reset();

        assert.deepEqual(store.$state, { busy: false, fields: {}, notice: '', done: false, expired: false, savedAs: '' });
    });
});
