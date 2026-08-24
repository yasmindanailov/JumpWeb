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

        // ⚠️ `consentsLoading` entra en esta foto el 2026-08-23 y NO es cosmético: es la bandera del
        // velo, y este `deepEqual` es lo que asevera que `reset()` no la toca —si algún día se colara
        // en `resetForm()`, reentrar en la zona apagaría el velo con la petición en vuelo—.
        assert.deepEqual(store.$state, { busy: false, fields: {}, notice: '', done: false, expired: false, savedAs: '', consents: null, consentsLoading: false });
    });

    /**
     * ⚠️ **Los consentimientos NO se borran al entrar, y es deliberado**: son un DATO leído, no el
     * resultado de un intento. Vaciarlos aquí obligaría a repedirlos cada vez que el titular abre la
     * pantalla, que es justo lo que `ensureConsents()` existe para evitar.
     */
    test('pero los consentimientos ya leídos se conservan', async () => {
        const store = usePrivacyStore();
        const api = fakeApi({ '/me/consents': { ok: true, status: 200, data: { data: [], meta: { total: 0 } }, error: null } });

        await store.ensureConsents({ api });
        assert.equal(store.consentsLoaded, true);

        store.reset();

        assert.equal(store.consentsLoaded, true, 'reset ha tirado una lista que no cambia sola');
    });
});

describe('la lista de consentimientos', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('se pide UNA vez y no se repite al volver a entrar', async () => {
        const store = usePrivacyStore();
        const api = fakeApi({ '/me/consents': { ok: true, status: 200, data: { data: [{ type: 'privacy' }], meta: { total: 1 } }, error: null } });

        await store.ensureConsents({ api });
        await store.ensureConsents({ api });

        assert.equal(api.llamadas.length, 1, 'volver a entrar en la pantalla repite la petición');
        assert.equal(store.consents.meta.total, 1);
    });

    /**
     * ⚠️⚠️ **El caso que NINGÚN test de este fichero podía ver hasta hoy** (2026-08-23): el estado
     * MIENTRAS la petición está en vuelo. Los demás hacen `await` y solo miran el desenlace, así que
     * `ensureConsents()` llevaba desde su commit fundacional **sin levantar ninguna bandera** y nadie
     * lo notaba: el `v-if` del velo (`consentsLoading && ! consentsLoaded`) era falso, el
     * `v-else-if` del «no hay consentimientos» también, y la tarjeta se pintaba **vacía** durante
     * toda la espera. Encontrado en NAVEGADOR reteniendo `GET /me/consents` 2.500 ms (`V23·6`).
     *
     * ▶ Por eso este caso no se escribe con `await`: se retiene la promesa a propósito y se mira el
     * estado intermedio, que es el único sitio donde el fallo existía.
     */
    test('mientras la lista está EN VUELO, la zona sabe que está cargando', async () => {
        const store = usePrivacyStore();
        let soltar;
        const enVuelo = new Promise((resolve) => { soltar = resolve; });
        const api = { get: () => enVuelo };

        const pendiente = store.ensureConsents({ api });

        assert.equal(store.consentsLoading, true, 'la zona no sabe que hay una petición en vuelo: el velo no se pinta');
        assert.equal(store.consentsLoaded, false, 'se da por leída una lista que aún no ha llegado');

        soltar({ ok: true, status: 200, data: { data: [], meta: { total: 0 } }, error: null });
        await pendiente;

        assert.equal(store.consentsLoading, false, 'la bandera se queda encendida y el velo no se va nunca');
        assert.equal(store.consentsLoaded, true);
    });

    /**
     * ⚠️ **Y la bandera es PROPIA, no `busy`.** `busy` es el estado de los dos formularios de esta
     * pantalla; usarlo para una lectura de cortesía bloquearía «Descargar mis datos» y el borrado
     * mientras se lee una lista — y, peor, `reset()` llama a `resetForm()`, que lo pone a `false`:
     * reentrar en la zona con una carga en vuelo apagaría el velo a mitad.
     */
    test('leer la lista NO bloquea los dos derechos, y reentrar no apaga el velo', async () => {
        const store = usePrivacyStore();
        let soltar;
        const api = { get: () => new Promise((resolve) => { soltar = resolve; }) };

        const pendiente = store.ensureConsents({ api });

        assert.equal(store.busy, false, 'leer una lista ha bloqueado exportar y borrar');

        store.reset();

        assert.equal(store.consentsLoading, true, 'reentrar en la zona ha apagado el velo con la petición en vuelo');

        soltar({ ok: true, status: 200, data: { data: [], meta: { total: 0 } }, error: null });
        await pendiente;
    });

    /**
     * ⚠️ **Un fallo NO se cuela en el aviso del formulario.** Esta lista es contexto; pintar «revisa
     * los datos» encima del formulario de borrado porque no se pudo leer sería decirle al titular que
     * su problema es otro.
     */
    test('si falla, la pantalla no anuncia un error que no es suyo', async () => {
        const store = usePrivacyStore();
        // ⚠️ Una respuesta fallida REAL trae cuerpo: `api.js` mete en `data` el sobre de error ya
        // parseado. Doblarla con `data: null` haría que guardarla o no diera lo mismo, y el caso
        // pasaría con las dos implementaciones — lo destapó una mutación.
        const error = { code: 'server_error', message: 'Algo ha ido mal.' };

        await store.ensureConsents({
            api: fakeApi({ '/me/consents': { ok: false, status: 500, data: { error }, error, offline: false } }),
        });

        assert.equal(store.notice, '');
        assert.equal(store.fields && Object.keys(store.fields).length, 0);
        assert.equal(store.consentsLoaded, false, 'una respuesta fallida se ha guardado como lista');
    });
});
