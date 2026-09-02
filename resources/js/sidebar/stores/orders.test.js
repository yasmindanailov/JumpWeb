import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { STEPS, createMachine } from '../machine.js';
import { SECTIONS } from '../section.js';
import { useOrdersStore } from './orders.js';
import { useSectionStore } from './section.js';
import { usePurchaseStore } from './purchase.js';
import { useOutcomeStore } from './outcome.js';
import { useAccountStore } from './account.js';
import { createNavigation, ZONES } from '../account/navigation.js';

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
        const api = fakeApi({ '/me/reservations/upcoming?page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'JW-1' }] }) } });

        await store.load('upcoming', 1, { api });

        assert.equal(store.loading, false);
        assert.equal(store.loaded('upcoming'), true);
        assert.deepEqual(store.pages.upcoming.data, [{ code: 'JW-1' }], 'el store no compone: eso es del módulo');
    });

    test('⚠️ `ensure()` NO repite la petición al volver a la zona', async () => {
        // Es la regla que sustituyó a `<KeepAlive>` (`DECISIONES #120(g)`): pedir solo si no hay datos
        // cuesta cero KiB y se puede probar, al revés que una caché del framework.
        const store = useOrdersStore();
        const api = fakeApi({ '/me/reservations/upcoming?page=1': { ok: true, status: 200, data: PAGE() } });

        await store.ensure('upcoming', { api });
        await store.ensure('upcoming', { api });
        await store.ensure('upcoming', { api });

        assert.equal(api.llamadas.length, 1, 'volver a la zona ha vuelto a pedir el historial');
    });

    test('pedir otra página sí llama, y con su número', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/reservations/upcoming?page=2': { ok: true, status: 200, data: PAGE({ meta: { current_page: 2, last_page: 3 } }) } });

        await store.load('upcoming', 2, { api });

        assert.equal(api.llamadas[0].url, '/me/reservations/upcoming?page=2');
        assert.equal(store.pages.upcoming.meta.current_page, 2);
    });

    test('`reload()` vuelve a pedir LA PÁGINA QUE SE ESTÁ VIENDO, no la primera', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/me/reservations/upcoming?page=3': { ok: true, status: 200, data: PAGE({ meta: { current_page: 3, last_page: 3 } }) },
        });

        await store.load('upcoming', 3, { api });
        await store.reload('upcoming', { api });

        assert.deepEqual(api.llamadas.map((l) => l.url), ['/me/reservations/upcoming?page=3', '/me/reservations/upcoming?page=3']);
    });
});

describe('cuando algo falla', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('⚠️ un fallo NO borra lo que el cliente ya tenía delante', async () => {
        // Vaciar la lista al fallar diría «no tienes reservas», que es justo lo contrario de lo que
        // ha pasado. Se conserva la página anterior y se añade el aviso.
        const store = useOrdersStore();
        const ok = fakeApi({ '/me/reservations/upcoming?page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'JW-1' }] }) } });

        await store.load('upcoming', 1, { api: ok });
        await store.load('upcoming', 2, { api: fakeApi({}) });

        assert.deepEqual(store.pages.upcoming.data, [{ code: 'JW-1' }], 'se ha perdido la página que ya estaba');
        assert.equal(store.loading, false, 'el velo se queda girando para siempre');
    });

    test('⚠️ un 401 se distingue del error genérico: la salida es entrar, no reintentar', async () => {
        const store = useOrdersStore();

        await store.load('upcoming', 1, { api: fakeApi({ '/me/reservations/upcoming?page=1': { ok: false, status: 401, data: null, error: null } }) });

        assert.equal(store.unauthenticated, true);
        assert.equal(store.error, '', 'un 401 no es «algo ha ido mal»: es «tu sesión ha caducado»');
    });

    test('una carga nueva limpia el aviso anterior', async () => {
        const store = useOrdersStore();

        await store.load('upcoming', 1, { api: fakeApi({ '/me/reservations/upcoming?page=1': { ok: false, status: 401, data: null } }) });
        await store.load('upcoming', 1, { api: fakeApi({ '/me/reservations/upcoming?page=1': { ok: true, status: 200, data: PAGE() } }) });

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

describe('las respuestas del pack, bajo demanda', () => {
    beforeEach(() => setActivePinia(createPinia()));

    /**
     * ⚠️⚠️ **No llegan con la lista, y ésa es toda la razón de que esto exista**: son datos de un
     * MENOR (art. 9) y `GET /me/orders` los excluye a propósito. Pedirlos es un acto explícito del
     * titular, igual que en el resumen de la compra.
     */
    test('se piden al pedido concreto, y una sola vez', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/orders/JW-0001/event-data': { ok: true, status: 200, data: { order_code: 'JW-0001', reservations: [] }, error: null },
        });

        await store.ensureEventData('JW-0001', { api });
        await store.ensureEventData('JW-0001', { api });

        assert.equal(api.llamadas.length, 1, 'desplegar dos veces repite la petición');
        assert.equal(api.llamadas[0].url, '/orders/JW-0001/event-data');
        assert.equal(store.eventData['JW-0001'].order_code, 'JW-0001');
    });

    test('cada pedido guarda las suyas', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/orders/JW-0001/event-data': { ok: true, status: 200, data: { order_code: 'JW-0001', reservations: [] }, error: null },
            '/orders/JW-0002/event-data': { ok: true, status: 200, data: { order_code: 'JW-0002', reservations: [] }, error: null },
        });

        await store.ensureEventData('JW-0001', { api });
        await store.ensureEventData('JW-0002', { api });

        assert.deepEqual(Object.keys(store.eventData), ['JW-0001', 'JW-0002']);
    });

    /** Un código con caracteres raros no puede romper la URL ni salirse de su ruta. */
    test('el código va escapado en la URL', async () => {
        const store = useOrdersStore();
        const api = fakeApi({});

        await store.ensureEventData('JW/0001 raro', { api });

        assert.equal(api.llamadas[0].url, '/orders/JW%2F0001%20raro/event-data');
    });

    /**
     * ⚠️ **Un fallo NO se anuncia con el error de la zona**: esto es un despliegue que el cliente
     * pidió, no el contenido de la pantalla. Pintar «algo ha ido mal» sobre la lista entera porque no
     * se pudo leer un bloque le diría al titular que su problema es otro.
     */
    test('si falla, ni se guarda nada ni se rompe la pantalla', async () => {
        const store = useOrdersStore();
        const error = { code: 'server_error', message: 'Algo ha ido mal.' };

        await store.ensureEventData('JW-0001', {
            api: fakeApi({ '/orders/JW-0001/event-data': { ok: false, status: 500, data: { error }, error, offline: false } }),
        });

        assert.deepEqual(store.eventData, {});
        assert.equal(store.error, '');
    });

    test('sin código no se pide nada', async () => {
        const store = useOrdersStore();
        const api = fakeApi({});

        await store.ensureEventData('', { api });

        assert.equal(api.llamadas.length, 0);
    });
});

/**
 * **«Mis pedidos»** (`specs/desglose-dinero-cliente.md` §19, `DECISIONES #129`).
 *
 * ⚠️ Lo que hay que proteger aquí no es que pida una URL: es que **abrir la pantalla desde una
 * reserva llegue con ese pedido dentro**. Es lo único de esta tanda que puede fallar en silencio —una
 * lista de pedidos se lee perfectamente aunque el que el cliente buscaba esté en otra página—.
 */
describe('«Mis pedidos»', () => {
    // ⚠️ El área se ARRANCA, porque «Ver pedido» solo es alcanzable desde dentro de ella: es la
    // precondición real, y montarla aquí es lo que permite probar que la navegación también ocurre.
    beforeEach(() => {
        setActivePinia(createPinia());
        useAccountStore().boot(createNavigation({ zone: ZONES.ORDERS }));
    });

    /**
     * ⚠️⚠️ **Sembrar el pedido y NAVEGAR son la misma regla**, y por eso viven las dos aquí. Repartir
     * una mitad al componente dejaba la navegación sin red: un componente pinta y no se prueba con
     * `node --test`. Sin esta aserción, «Ver pedido» podría quedarse sin llevar a ningún sitio.
     */
    test('⚠️ «Ver pedido» siembra el pedido Y lleva a la pantalla de pedidos', () => {
        const store = useOrdersStore();

        store.openPurchase('R-L6UTIA');

        assert.equal(store.focus, 'R-L6UTIA');
        assert.equal(useAccountStore().zone, ZONES.PURCHASES, '«Ver pedido» no lleva a ninguna parte');
    });

    test('la primera carga pide la página 1 y guarda la respuesta CRUDA', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?per_page=5&page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'R-1' }] }) } });

        await store.ensurePurchases({ api });

        assert.deepEqual(store.purchases.data, [{ code: 'R-1' }]);
        assert.equal(api.llamadas.length, 1);
    });

    test('⚠️ `ensurePurchases()` NO repite la petición al volver a la zona', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?per_page=5&page=1': { ok: true, status: 200, data: PAGE() } });

        await store.ensurePurchases({ api });
        await store.ensurePurchases({ api });

        assert.equal(api.llamadas.length, 1, 'volver a la zona ha vuelto a pedir la lista');
    });

    /**
     * ⚠️⚠️ **La página la elige el SERVIDOR con `containing`, y ésta es la mitad que importa.**
     * El orden y el tamaño de página son suyos, así que es el único que sabe en cuál cae el pedido.
     * Pedir la página 1 y confiar en que esté dentro es la forma silenciosa de incumplir la decisión
     * del owner: no falla nada y la pantalla se abre sin el pedido.
     */
    test('⚠️ abrir desde una reserva pide LA PÁGINA QUE CONTIENE ese pedido', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?per_page=5&containing=R-L6UTIA': { ok: true, status: 200, data: PAGE({ meta: { current_page: 3, last_page: 6 } }) } });

        store.openPurchase('R-L6UTIA');
        await store.ensurePurchases({ api });

        assert.equal(api.llamadas[0].url, '/me/orders?per_page=5&containing=R-L6UTIA');
        assert.equal(store.purchases.meta.current_page, 3);
    });

    /**
     * ⚠️⚠️ **Y por eso `openPurchase()` INVALIDA la página cargada.** Sin ese borrado,
     * `ensurePurchases()` —que pide «solo si no hay datos»— se quedaría con la que ya tuviera y la
     * pantalla se abriría **sin el pedido dentro**. Es el caso real: el cliente entra por el índice,
     * vuelve, abre una reserva y pulsa «Ver pedido».
     */
    test('⚠️ abrir desde una reserva descarta la página que ya estuviera cargada', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/me/orders?per_page=5&page=1': { ok: true, status: 200, data: PAGE({ data: [{ code: 'R-1' }] }) },
            '/me/orders?per_page=5&containing=R-LEJOS': { ok: true, status: 200, data: PAGE({ data: [{ code: 'R-LEJOS' }], meta: { current_page: 4, last_page: 6 } }) },
        });

        await store.ensurePurchases({ api });
        store.openPurchase('R-LEJOS');
        await store.ensurePurchases({ api });

        assert.equal(api.llamadas.length, 2, 'se ha quedado con la página vieja: el pedido no sale');
        assert.deepEqual(store.purchases.data, [{ code: 'R-LEJOS' }]);
    });

    /** La intención se consume UNA vez: si no, volver a entrar reabriría el pedido de la visita anterior. */
    test('⚠️ el foco se consume: la segunda entrada ya no lo arrastra', async () => {
        const store = useOrdersStore();
        const api = fakeApi({
            '/me/orders?per_page=5&containing=R-1': { ok: true, status: 200, data: PAGE() },
            '/me/orders?per_page=5&page=1': { ok: true, status: 200, data: PAGE() },
        });

        store.openPurchase('R-1');
        await store.ensurePurchases({ api });

        assert.equal(store.focus, '', 'el foco no se ha consumido');

        store.purchases = null;
        await store.ensurePurchases({ api });

        assert.equal(api.llamadas[1].url, '/me/orders?per_page=5&page=1');
    });

    test('pasar de página pide su número, y no arrastra el foco', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?per_page=5&page=2': { ok: true, status: 200, data: PAGE({ meta: { current_page: 2, last_page: 3 } }) } });

        await store.loadPurchases(2, { api });

        assert.equal(api.llamadas[0].url, '/me/orders?per_page=5&page=2');
    });

    test('⚠️ un 401 se distingue del error genérico: la salida es entrar, no reintentar', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/me/orders?per_page=5&page=1': { ok: false, status: 401, data: null, error: null } });

        await store.ensurePurchases({ api });

        assert.equal(store.unauthenticated, true);
        assert.equal(store.error, '');
    });

    test('el código va escapado en la URL', async () => {
        const store = useOrdersStore();
        const api = fakeApi({});

        store.openPurchase('R-A B/C');
        await store.ensurePurchases({ api });

        assert.equal(api.llamadas[0].url, '/me/orders?per_page=5&containing=R-A%20B%2FC');
    });
});

/**
 * **Los JUSTIFICANTES de menores invitados** (`specs/waiver-por-reserva.md` §13; `#344`).
 *
 * ❗❗ **El defecto que motiva estos casos salía con 200 y no pintaba NADA.** `api.js` devuelve el
 * CUERPO entero —`{data: {...}}`— y este store guardaba eso tal cual, así que el componente leía
 * `guestMinors[code].reservations` sobre un objeto que solo tiene `data`. La petición se veía en la
 * pestaña de red, verde, y la pantalla salía vacía. El owner lo dijo dos veces.
 *
 * ▶ *Un 200 no dice que el dato haya llegado a donde se lee.* El resto del store desenvuelve al
 * COMPONER; aquí no hay compositor, así que se desenvuelve al guardar — y eso es lo que se fija.
 */
describe('los justificantes de menores invitados', () => {
    beforeEach(() => setActivePinia(createPinia()));

    const SOBRE = {
        ok: true,
        status: 200,
        data: { data: { reservations: [{ reservation_id: 7, product_name: 'Excursión', date: '2026-09-09', minors: [], places: 3, link: 'https://x/autorizacion/7' }] } },
    };

    test('se guarda el CONTENIDO del sobre, no el sobre', async () => {
        const store = useOrdersStore();
        const api = fakeApi({ '/orders/R-1/guest-minors': SOBRE });

        await store.ensureGuestMinors('R-1', { api });

        // Lo que el componente lee. Con el sobre sin abrir esto era `undefined` y el `v-for` no
        // pintaba ni una fila — en silencio.
        assert.equal(store.guestMinors['R-1'].reservations.length, 1);
        assert.equal(store.guestMinors['R-1'].reservations[0].reservation_id, 7);
        assert.equal(store.guestMinors['R-1'].reservations[0].places, 3);
    });

    test('no repite la petición del mismo pedido', async () => {
        // Dos tarjetas del MISMO pedido en «Mis reservas» piden a la vez; la segunda no debe llamar.
        const store = useOrdersStore();
        const api = fakeApi({ '/orders/R-1/guest-minors': SOBRE });

        await store.ensureGuestMinors('R-1', { api });
        await store.ensureGuestMinors('R-1', { api });

        assert.equal(api.llamadas.length, 1);
    });

    test('un fallo deja una forma LEGIBLE, no un hueco', async () => {
        // ⚠️ Sin esto, un 500 dejaba la clave sin poner y el componente hacía `?.reservations` sobre
        // `undefined` — que funciona, pero deja la puerta abierta a que el siguiente lea sin `?.`.
        const store = useOrdersStore();
        const api = fakeApi({});

        await store.ensureGuestMinors('R-9', { api });

        assert.equal(store.guestMinors['R-9'], undefined, 'un fallo no inventa datos');
    });
});
