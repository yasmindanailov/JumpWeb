import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { reactive } from 'vue';
import { useOtraEntrada } from './useOtraEntrada.js';

/**
 * **«Añadir otra entrada» desde «Pagar»** (K3 de `otra-zona.md`): abre la pantalla de la línea nueva aunque la compra llegue
 * a «Pagar» SIN los días de las demás filas —la vuelta de Google, o sin pasar por la pantalla 0—. Medido por el owner el
 * 03-10 en staging: sin esos días ninguna fila «se vendía» y el botón no hacía nada. La API se dobla en `fetch`.
 */
const kids = { id: 100, type: 'entry', name: 'Kids · 1 hora', duration_min: 60, zone: { slug: 'kids', name: 'KIDS' } };
const jump = { id: 103, type: 'entry', name: 'Jump · 1 hora', duration_min: 60, zone: { slug: 'jump', name: 'JUMP' } };
const DIA = '2026-10-10';

let dias = {};

beforeEach(() => {
    dias = {};
    globalThis.window = { JumpWeb: { track() {} } };
    globalThis.document = { cookie: 'XSRF-TOKEN=prueba', dispatchEvent() {} };
    globalThis.fetch = async (url, init = {}) => {
        const ruta = String(url).replace(/^\/api\/v1/, '');
        const d = ruta.match(/^\/availability\/(\d+)\/dates$/);
        let cuerpo = {};

        if (d) cuerpo = { data: dias[d[1]] ?? [] };
        else if ((init.method ?? 'GET') === 'POST' && /\/addons$/.test(ruta)) cuerpo = { groups: [], singles: [] };
        else if (/^\/catalog\/products\/\d+$/.test(ruta)) cuerpo = { id: Number(ruta.split('/').pop()), addons: [] };

        return new Response(JSON.stringify(cuerpo), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };
});

/** «Pagar» con un pedido de Kids y, como tras la vuelta de Google, SIN los días de ninguna fila. */
function enPagar() {
    const compra = reactive({
        pedido: { fila: 100, dia: DIA, hora: '17:00:00', otras: [] }, borrador: { zona: 'kids' }, precios: {},
        fichasOtras: {}, sueltosOtras: {}, gruposOtras: {}, nueva: null, nuevaOferta: null, modo: null, aviso: '', paso: 'pagar',
    });
    const otra = useOtraEntrada({
        flow: { catalogStore: { products: [kids, jump] }, locale: 'es' }, compra, enCola: (fn) => Promise.resolve().then(fn),
        textos: { compra: { pagar: { sin_otra: 'Ese día no queda otra entrada que añadir.' } } }, pago: {}, alLlenarse: () => {},
    });

    return { compra, otra };
}

describe('«Añadir otra entrada» desde «Pagar»', () => {
    test('sin los días de las filas, los trae ANTES de decidir y abre la otra zona', async () => {
        dias = { 100: [{ date: DIA, price_cents: 1000 }], 103: [{ date: DIA, price_cents: 1200 }] };
        const { compra, otra } = enPagar();

        await otra.abrir();

        assert.equal(compra.modo, 'otra', 'abre la pantalla de la línea nueva');
        assert.equal(compra.paso, 'cuando');
        assert.deepEqual([compra.nueva?.zona, compra.nueva?.fila], ['jump', 103], 'de partida, la otra zona con su tiempo parecido');
        assert.deepEqual(Object.keys(compra.precios).sort(), ['100', '103'], 'los días de las filas, ya en la compra');
    });

    test('si ese día no queda nada que añadir, lo DICE y se queda en «Pagar»', async () => {
        dias = { 100: [{ date: '2026-10-11', price_cents: 1000 }], 103: [] };
        const { compra, otra } = enPagar();

        await otra.abrir();

        assert.equal(compra.modo, null, 'no abre una pantalla sin nada que elegir');
        assert.equal(compra.paso, 'pagar');
        assert.equal(compra.aviso, 'Ese día no queda otra entrada que añadir.');
    });
});
