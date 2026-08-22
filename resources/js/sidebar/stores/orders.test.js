import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { STEPS, createMachine } from '../machine.js';
import { SECTIONS } from '../section.js';
import { useOrdersStore } from './orders.js';
import { useSectionStore } from './section.js';
import { usePurchaseStore } from './purchase.js';
import { useOutcomeStore } from './outcome.js';

/**
 * La red del store de «Mis reservas».
 *
 * ⚠️ Lo que aquí se prueba y NO está en `account/orders.test.js` son las **costuras**: que no se
 * repita la petición al volver, que un fallo no borre lo que el cliente ya tenía delante, y que el
 * reintento salga por la pantalla de redirección **del embudo** en vez de reimplementarla.
 */

/** Un `api` de mentira que responde lo que se le diga y cuenta lo que le piden. */
function fakeApi(respuestas) {
    const llamadas = [];

    return {
        llamadas,
        get: async (url) => {
            llamadas.push({ method: 'GET', url });

            return respuestas[url] ?? { ok: false, status: 500, data: null, error: null };
        },
        post: async (url, body) => {
            llamadas.push({ method: 'POST', url, body });

            return respuestas[url] ?? { ok: false, status: 500, data: null, error: null };
        },
    };
}

const PAGE = (over = {}) => ({ data: [], meta: { current_page: 1, last_page: 1, total: 0 }, ...over });

describe('pedir el historial', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('la primera carga guarda la respuesta CRUDA', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'JW-1' }] }) } });

        await store.load(1, { api });

        assert.equal(store.loading, false);
        assert.equal(store.loaded, true);
        assert.deepEqual(store.payload.data, [{ code: 'JW-1' }], 'el store no compone: eso es del módulo');
    });

    test('⚠️ `ensure()` NO repite la petición al volver a la zona', async () => {
        // Es la regla que sustituyó a `<KeepAlive>` (`DECISIONES #120(g)`): pedir solo si no hay datos
        // cuesta cero KiB y se puede probar, al revés que una caché del framework.
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?page=1': { ok: true, status: 200, data: PAGE() } });

        await store.ensure({ api });
        await store.ensure({ api });
        await store.ensure({ api });

        assert.equal(api.llamadas.length, 1, 'volver a la zona ha vuelto a pedir el historial');
    });

    test('pedir otra página sí llama, y con su número', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?page=2': { ok: true, status: 200, data: PAGE({ meta: { current_page: 2, last_page: 3 } }) } });

        await store.load(2, { api });

        assert.equal(api.llamadas[0].url, '/me/orders?page=2');
        assert.equal(store.payload.meta.current_page, 2);
    });

    test('`reload()` vuelve a pedir LA PÁGINA QUE SE ESTÁ VIENDO, no la primera', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/me/orders?page=3': { ok: true, status: 200, data: PAGE({ meta: { current_page: 3, last_page: 3 } }) },
        });

        await store.load(3, { api });
        await store.reload({ api });

        assert.deepEqual(api.llamadas.map((l) => l.url), ['/me/orders?page=3', '/me/orders?page=3']);
    });
});

describe('cuando algo falla', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ un fallo NO borra lo que el cliente ya tenía delante', async () => {
        // Vaciar la lista al fallar diría «no tienes reservas», que es justo lo contrario de lo que
        // ha pasado. Se conserva la página anterior y se añade el aviso.
        const store = useOrdersStore();
        const ok = fakeApi({ '/me/orders?page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'JW-1' }] }) } });

        await store.load(1, { api: ok });
        await store.load(2, { api: fakeApi({}) });

        assert.deepEqual(store.payload.data, [{ code: 'JW-1' }], 'se ha perdido la página que ya estaba');
        assert.equal(store.loading, false, 'el velo se queda girando para siempre');
    });

    test('⚠️ un 401 se distingue del error genérico: la salida es entrar, no reintentar', async () => {
        const store = useOrdersStore();

        await store.load(1, { api: fakeApi({ '/me/orders?page=1': { ok: false, status: 401, data: null, error: null } }) });

        assert.equal(store.unauthenticated, true);
        assert.equal(store.error, '', 'un 401 no es «algo ha ido mal»: es «tu sesión ha caducado»');
    });

    test('una carga nueva limpia el aviso anterior', async () => {
        const store = useOrdersStore();

        await store.load(1, { api: fakeApi({ '/me/orders?page=1': { ok: false, status: 401, data: null } }) });
        await store.load(1, { api: fakeApi({ '/me/orders?page=1': { ok: true, status: 200, data: PAGE() } }) });

        assert.equal(store.unauthenticated, false);
        assert.equal(store.error, '');
    });
});

describe('reintentar el pago desde la ficha', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        usePurchaseStore().boot(createMachine());
        useSectionStore().showAccount();
    });

    test('⚠️ sale por la pantalla de redirección DEL EMBUDO, con el formulario firmado', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/orders/JW-1/payment': {
                ok: true,
                status: 201,
                // La forma REAL de `OrderPayment` (`openapi/v1.yaml`): `url` + `method` + `fields`.
                data: { payment: { url: 'https://sis-t.redsys.es/sis/realizarPago', method: 'POST', fields: { Ds_SignatureVersion: 'HMAC_SHA256_V1', Ds_MerchantParameters: 'eyJ...', Ds_Signature: 'abc' } } },
            },
        });

        const done = await store.retry('JW-1', { api, messages: {} });

        assert.equal(done, true);
        assert.equal(useSectionStore().active, SECTIONS.PURCHASE, 'el reintento saca del área de cliente');
        assert.equal(usePurchaseStore().step, STEPS.REDIRECTING);
        assert.ok(useOutcomeStore().gateway, 'sin formulario no hay a dónde ir');
    });

    test('⚠️ se llega con `enter()`, no con `go()`: se viene de FUERA del embudo', async () => {
        // `go()` exige que la transición esté declarada en `FUNNEL_TRANSITIONS`, y desde el catálogo
        // —donde el embudo puede estar parado mientras el cliente mira sus reservas— **no lo está**.
        // Con `go()`, el reintento dejaría al cliente mirando el catálogo con el pago a medias.
        const purchase = usePurchaseStore();
        assert.equal(purchase.step, STEPS.CATALOG);
        assert.equal(purchase.machine.go(STEPS.REDIRECTING), false, 'la transición NO existe, y por eso hace falta `enter`');

        const store = useOrdersStore();
        await store.retry('JW-1', {
            api: fakeApi({ '/orders/JW-1/payment': { ok: true, status: 201, data: { payment: { url: 'https://sis-t.redsys.es/sis/realizarPago', method: 'POST', fields: { Ds_Signature: 'abc' } } } } }),
            messages: {},
        });

        assert.equal(usePurchaseStore().step, STEPS.REDIRECTING);
    });

    test('si la pasarela no da formulario, se avisa y NO se mueve de sitio', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/orders/JW-1/payment': { ok: true, status: 201, data: { payment: null } } });

        const done = await store.retry('JW-1', { api, messages: { errors: { payment_unavailable: 'No se pudo abrir el pago' } } });

        assert.equal(done, false);
        assert.equal(store.error, 'No se pudo abrir el pago');
        assert.equal(useSectionStore().active, SECTIONS.ACCOUNT, 'se queda donde estaba, con su aviso');
    });

    test('⚠️ la sesión perdida por el camino se trata como tal, no como error genérico', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/orders/JW-1/payment': { ok: false, status: 401, data: null, error: null } });

        await store.retry('JW-1', { api, messages: {} });

        assert.equal(store.unauthenticated, true);
    });
});
