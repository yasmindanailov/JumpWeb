import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { reactive } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import { createMissingReporter } from '../../sidebar/missing.js';
import { useCartStore } from '../../sidebar/stores/cart.js';
import { useCatalogStore } from '../../sidebar/stores/catalog.js';
import { useSelectionStore } from '../../sidebar/stores/selection.js';
import { useTimeStore } from '../../sidebar/stores/time.js';
import { useCalculadora } from '../calculadora/useCalculadora.js';
import { useCalculadoraFiesta } from '../calculadora/useCalculadoraFiesta.js';
import { informarDemanda } from './demanda.js';
import { borradorDeIntencion } from './intencion.js';
import { usePantallaCero } from './usePantallaCero.js';

/**
 * **La demanda sin hueco en la isla** (`DECISIONES #758`; `specs/isla-y-landing-nueva.md` §4.26): la regla, el reportero
 * de la PÁGINA y dónde informa cada superficie. La API se dobla en `fetch` (la de verdad, `sidebar/api.js`) y los meses
 * se calculan desde HOY: un producto que solo se vende dentro de dos meses deja sin hueco el en curso y el siguiente.
 *
 * ⚠️ El reportero de la página vive en el MÓDULO y dura lo que este fichero: cada prueba usa sus propios productos.
 */
const mes = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
const ahora = new Date();
const lejanos = [{ date: `${mes(new Date(ahora.getFullYear(), ahora.getMonth() + 2, 1))}-10`, price_cents: 800 }];
const sinHueco = [mes(ahora), mes(new Date(ahora.getFullYear(), ahora.getMonth() + 1, 1))];
const eventos = (id) => sinHueco.map((month) => ['availability_missing', { product: String(id), month }]);

let enviados = [];
let dias = {};
let fichas = {};

/** La API doblada: los días de cada producto (o `'falla'`), su ficha (si la prueba la da) y lo demás vacío. */
function responder(ruta, metodo) {
    const d = ruta.match(/^\/availability\/(\d+)\/dates$/);

    if (d) return dias[d[1]] === 'falla' ? [500, {}] : [200, { data: dias[d[1]] ?? [] }];
    if (/^\/availability\/\d+\/times$/.test(ruta)) return [200, { data: [] }];
    if (metodo === 'POST' && /^\/catalog\/products\/\d+\/addons$/.test(ruta)) return [200, { groups: [], singles: [] }];
    const p = ruta.match(/^\/catalog\/products\/(\d+)$/);

    return p ? [200, fichas[p[1]] ?? { id: Number(p[1]), addons: [], min_quantity: 1 }] : [404, {}];
}

beforeEach(() => {
    enviados = [];
    dias = {};
    fichas = {};
    setActivePinia(createPinia());
    globalThis.window = { JumpWeb: { track: (name, props) => enviados.push([name, props]) } };
    globalThis.document = { cookie: 'XSRF-TOKEN=prueba', dispatchEvent() {} };
    globalThis.fetch = async (url, init = {}) => {
        const [status, cuerpo] = responder(String(url).replace(/^\/api\/v1/, ''), init.method ?? 'GET');

        return new Response(JSON.stringify(cuerpo), { status, headers: { 'Content-Type': 'application/json' } });
    };
});

/** Lo que queda en cola tras un `await`: los `then` de la calculadora corren después de quien la toca. */
const asentar = () => new Promise((r) => setImmediate(r));

describe('la regla: la fila que se mira, si su oferta llegó', () => {
    test('informa los meses sin días desde el en curso, una vez por producto y mes', () => {
        const propios = [];
        const reportar = createMissingReporter((name, props) => propios.push([name, props]));

        assert.deepEqual(informarDemanda({ id: '7', dias: lejanos, llegaron: [7] }, reportar), sinHueco, 'el id, como texto o como número');
        assert.deepEqual(informarDemanda({ id: 7, dias: lejanos, llegaron: [7] }, reportar), [], 'la misma otra vez, nada');
        assert.deepEqual(propios, eventos(7));
    });

    test('una oferta que NO llegó no informa: una red caída no es un mes lleno', () => {
        const propios = [];
        const reportar = createMissingReporter((name, props) => propios.push([name, props]));

        assert.deepEqual(informarDemanda({ id: 7, dias: [], llegaron: [8] }, reportar), []);
        assert.deepEqual(informarDemanda({ id: null, dias: [], llegaron: [] }, reportar), [], 'sin fila, nada');
        assert.deepEqual(propios, []);
    });
});

describe('un reportero por PÁGINA', () => {
    test('la calculadora y luego la compra, con el mismo producto, cuentan UNA vez (el cuadro cuenta filas)', () => {
        informarDemanda({ id: 31, dias: lejanos, llegaron: [31] });
        informarDemanda({ id: 31, dias: lejanos, llegaron: [31] });

        assert.deepEqual(enviados, eventos(31), 'por `JumpWeb.track`, que publica el cajón');
    });

    test('sin tracker en la página no revienta', () => {
        globalThis.window = {};

        assert.deepEqual(informarDemanda({ id: 32, dias: lejanos, llegaron: [32] }), sinHueco);
    });
});

describe('la calculadora de la página', () => {
    const pagina = (ids) => ({ filas: ids.map((id) => ({ id })), textos: { inicio: {} } });

    test('arrancar ella sola (al acercarse la pieza) NO informa; tocarla informa la fila que mira, y otra fila, la suya', async () => {
        dias = { 300: lejanos, 301: lejanos };
        const c = useCalculadora({ pagina: pagina([300, 301]), textos: {}, locale: 'es' });

        await c.arrancar();
        await asentar();
        assert.deepEqual(enviados, [], 'pasar por la página no es mirar un producto');

        await c.cambiar('n', 3);
        await asentar();
        assert.deepEqual(enviados, eventos(300));

        await c.cambiar('fila', 301);
        await asentar();
        assert.deepEqual(enviados, [...eventos(300), ...eventos(301)], 'la de DESPUÉS del cambio, en ese mismo cambio');

        await c.cambiar('n', 4);
        await asentar();
        assert.deepEqual(enviados, [...eventos(300), ...eventos(301)], 'cada una, una vez');
    });

    test('el primer toque llega antes que los días: informa cuando llegan', async () => {
        dias = { 303: lejanos };
        const c = useCalculadora({ pagina: pagina([303]), textos: {}, locale: 'es' });

        await c.cambiar('n', 2);
        await asentar();
        assert.deepEqual(enviados, eventos(303));
    });

    test('tocar una fila cuyos días no llegaron no informa', async () => {
        dias = { 302: 'falla' };
        const c = useCalculadora({ pagina: pagina([302]), textos: {}, locale: 'es' });

        await c.cambiar('n', 2);
        await asentar();
        assert.deepEqual(enviados, []);
    });
});

describe('la calculadora de la FIESTA', () => {
    test('arrancar sola NO informa; tocarla, el pack cuyos días enseña: sin edad el primero, con edad el suyo', async () => {
        dias = { 400: lejanos, 401: lejanos };
        const packs = [{ id: 400, guest_age_min: 3, guest_age_max: 7, min_quantity: 8 }, { id: 401, guest_age_min: 8, guest_age_max: null, min_quantity: 8 }];
        const c = useCalculadoraFiesta({ pagina: { packs, textos: {} }, textos: {}, locale: 'es' });

        await c.arrancar();
        await asentar();
        assert.deepEqual(enviados, []);

        await c.cambiar('n', 9);
        await asentar();
        assert.deepEqual(enviados, eventos(400));

        await c.cambiar('edad', 9);
        await asentar();
        assert.deepEqual(enviados, [...eventos(400), ...eventos(401)]);
    });
});

describe('la compra (la pantalla 0)', () => {
    function montar(productos) {
        const catalogStore = useCatalogStore();

        catalogStore.setProducts(productos);
        const flow = { catalogStore, timeStore: useTimeStore(), selectionStore: useSelectionStore(), cartStore: useCartStore(), locale: 'es', configuracion: { value: null } };
        const compra = reactive({ borrador: {}, precios: {}, llegaron: [], fichas: {}, grupos: [], cargandoHoras: false, aviso: '' });

        return { catalogStore, pantalla: usePantallaCero({ flow, compra, enCola: (t) => t(), textos: {} }) };
    }

    test('situarla informa la fila ELEGIDA, no las demás de su zona; cambiar de fila, la nueva', async () => {
        dias = { 200: lejanos, 201: lejanos };
        const { catalogStore, pantalla } = montar([{ id: 200, type: 'entry', zone: { slug: 'kids' } }, { id: 201, type: 'entry', zone: { slug: 'kids' } }]);

        await pantalla.situar(borradorDeIntencion({ type: 'product', id: 201 }, catalogStore.products));
        assert.deepEqual(enviados, eventos(201), 'la zona pide los días de todas sus filas; se mira una');

        await pantalla.cambiar('fila', 200);
        assert.deepEqual(enviados, [...eventos(201), ...eventos(200)]);
    });

    test('una FIESTA: el pack situado y, al cambiar la edad, el de su tramo', async () => {
        dias = { 220: lejanos, 221: lejanos };
        const edad = { key: 'edad', type: 'celebrant_age' };
        fichas = {
            220: { id: 220, addons: [], min_quantity: 8, guest_age_min: 3, guest_age_max: 7, event_fields: [edad] },
            221: { id: 221, addons: [], min_quantity: 8, guest_age_min: 8, guest_age_max: null, event_fields: [edad] },
        };
        const { catalogStore, pantalla } = montar([{ id: 220, type: 'pack', zone: { slug: 'cumpleanos' } }, { id: 221, type: 'pack', zone: { slug: 'cumpleanos' } }]);

        await pantalla.situar(borradorDeIntencion({ type: 'product', id: 220 }, catalogStore.products));
        assert.deepEqual(enviados, eventos(220));

        await pantalla.cambiar('edad', 9);
        assert.deepEqual(enviados, [...eventos(220), ...eventos(221)]);
    });

    test('un pack sin edad (una excursión): la oferta que trae la fiesta que no lo era también cuenta', async () => {
        dias = { 210: lejanos, 211: lejanos };
        const { catalogStore, pantalla } = montar([{ id: 210, type: 'pack', zone: { slug: 'colegios' } }, { id: 211, type: 'pack', zone: { slug: 'colegios' } }]);

        await pantalla.situar(borradorDeIntencion({ type: 'product', id: 211 }, catalogStore.products));
        assert.deepEqual(enviados, eventos(211));
    });
});
