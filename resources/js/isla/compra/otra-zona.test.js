import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { cargarHorasDe, conOfertaElDia, horasQueNoCaben, otraDeLaPantalla, otraNueva, otrasZonas } from './otra-zona.js';

/**
 * La otra zona en la pantalla 0 de la isla (K2 de `specs/otra-zona.md` §4.2, `#878`): el enlace que la nombra (D3-B), su
 * tarjeta y las horas en las que caben TODAS las líneas. El catálogo, el de la BD local (Kids y Jump, con sus filas).
 */
const kids = { slug: 'kids', name: 'KIDS' };
const jump = { slug: 'jump', name: 'JUMP' };
const productos = [
    { id: 100, type: 'entry', name: 'Kids · 1 hora', zone: kids },
    { id: 101, type: 'entry', name: 'Kids · 2 horas', zone: kids },
    { id: 103, type: 'entry', name: 'Jump · 1 hora', zone: jump },
    { id: 104, type: 'entry', name: 'Jump · 2 horas', zone: jump },
    { id: 105, type: 'pack', name: 'Pack Cumpleaños KIDS', zone: { slug: 'cumpleanos', name: 'Cumpleaños' } },
];
const sab = '2026-10-03';
const precios = { 100: [{ date: sab, price_cents: 1200 }], 103: [{ date: sab, price_cents: 800 }, { date: '2026-10-05', price_cents: 700 }] };
const textos = {
    compra: {
        cuando: {
            entrada: 'entrada', entradas: 'entradas',
            otra_zona: '¿Alguien va a la otra zona? Añádelo a la misma reserva',
            otra_zona_de: '¿Alguien va a :zona? Añádelo a la misma reserva',
            otra_no_vende: ':zona no se vende este día',
            otra_no_cabe: 'En :zona no caben a esta hora',
        },
        pagar: { precio_por: ':precio por :unidad' },
    },
};

describe('cuáles son «la otra zona»', () => {
    test('las zonas de ENTRADAS menos la del pedido, cada una con su primera fila; los packs no', () => {
        assert.deepEqual(otrasZonas(productos, 'kids').map((z) => [z.slug, z.fila.id]), [['jump', 103]]);
        assert.deepEqual(otrasZonas(productos, 'jump').map((z) => [z.slug, z.fila.id]), [['kids', 100]]);
    });

    test('solo las que se VENDEN ese día; sin sus días todavía, ninguna (no se promete)', () => {
        const zonas = otrasZonas(productos, 'kids');

        assert.equal(conOfertaElDia(zonas, precios, sab).length, 1);
        assert.equal(conOfertaElDia(zonas, precios, '2026-10-04').length, 0, 'ese día Jump no se vende');
        assert.equal(conOfertaElDia(zonas, {}, sab).length, 0);
    });

    test('la que se añade: su primera fila, para UNA persona (el mockup)', () => {
        assert.deepEqual(otraNueva(otrasZonas(productos, 'kids'), precios, sab), { fila: 103, n: 1 });
        assert.equal(otraNueva(otrasZonas(productos, 'kids'), precios, '2026-10-04'), null);
    });
});

describe('las horas, para TODAS las líneas', () => {
    const ofrecidas = [{ time: '17:00:00', available: 5, sellable: true }, { time: '18:00:00', available: 1, sellable: true }, { time: '19:00:00', available: 9, sellable: false }];

    test('no cabe donde su zona no tiene sitio para SU gente, no vende o no ofrece la hora', () => {
        const noCabe = horasQueNoCaben(ofrecidas, 2);

        assert.deepEqual(['17:00', '18:00', '19:00', '20:00'].map(noCabe), [false, true, true, true]);
    });

    test('sin su oferta todavía, no se apaga nada: mientras llega, no se esconde lo que quizá quepa', () => {
        assert.equal(horasQueNoCaben(null, 2)('17:00'), false);
    });

    test('se piden con la cesta de contexto (`AFORO-02`); sin respuesta, `null`', async () => {
        const llamadas = [];
        const api = { post: async (ruta, cuerpo) => { llamadas.push([ruta, cuerpo]); return { ok: true, data: { data: ofrecidas } }; } };

        assert.deepEqual(await cargarHorasDe({ api, fila: 103, dia: sab, lineas: [{ product_id: 100, date: sab, time: '17:00:00', quantity: 2 }] }), ofrecidas);
        assert.deepEqual(llamadas, [['/availability/103/times', { date: sab, items: [{ product_id: 100, date: sab, time: '17:00:00', quantity: 2 }] }]]);
        assert.equal(await cargarHorasDe({ api: { post: async () => ({ ok: false }) }, fila: 103, dia: sab }), null);
    });
});

describe('lo que pinta la pantalla 0', () => {
    test('sin la otra: el enlace la NOMBRA con una sola zona que vende ese día (D3-B); sin ninguna, nada', () => {
        const con = otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, otra: null }, productos, precios, textos, locale: 'es' });

        assert.equal(con.enlace, '¿Alguien va a JUMP? Añádelo a la misma reserva');
        assert.equal(con.tarjeta, null);
        assert.equal(otraDeLaPantalla({ borrador: { zona: 'kids', dia: '2026-10-04', otra: null }, productos, precios, textos }).enlace, '', 'ese día no se vende: no se ofrece');
        assert.equal(otraDeLaPantalla({ borrador: { zona: 'kids', dia: null, otra: null }, productos, precios, textos }).enlace, '', 'sin día, nada que prometer');
    });

    test('con dos zonas o más que venden ese día, el genérico', () => {
        const tres = [...productos, { id: 120, type: 'entry', name: 'Ninja · 1 hora', zone: { slug: 'ninja', name: 'NINJA' } }];

        assert.equal(otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, otra: null }, productos: tres, precios: { ...precios, 120: [{ date: sab, price_cents: 900 }] }, textos }).enlace,
            '¿Alguien va a la otra zona? Añádelo a la misma reserva');
    });

    test('con la otra: su tarjeta —su fila, su precio de ese día por persona, su gente— y su parte del resumen', () => {
        const v = otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, otra: { fila: 103, n: 2 } }, productos, precios, textos, locale: 'es' });

        assert.equal(v.enlace, '');
        assert.deepEqual([v.tarjeta.titulo, v.tarjeta.precio.replace(/\s/g, ' '), v.tarjeta.n], ['Jump · 1 hora', '8 € por entrada', 2]);
        assert.equal(v.resumen, 'Jump · 1 hora · 2 entradas');
        assert.equal(v.bloquea, false);
    });

    test('si su zona NO se vende ese día, la tarjeta lo dice y el pedido no sigue (sin quitarla a escondidas)', () => {
        const v = otraDeLaPantalla({ borrador: { zona: 'kids', dia: '2026-10-04', otra: { fila: 103, n: 1 } }, productos, precios: { ...precios, 103: [{ date: sab, price_cents: 800 }] }, textos });

        assert.equal(v.bloquea, true);
        assert.equal(v.tarjeta.precio, 'JUMP no se vende este día');
        assert.equal(otraDeLaPantalla({ borrador: { zona: 'kids', dia: '2026-10-04', otra: { fila: 103, n: 1 } }, productos, precios: { 100: [] }, textos }).bloquea, false, 'sin sus días todavía, no se dice');
    });

    test('las horas en las que la otra no cabe, apagadas con su porqué', () => {
        const v = otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, otra: { fila: 103, n: 3 } }, productos, precios, horasOtra: [{ time: '17:00:00', available: 2, sellable: true }], textos });

        assert.equal(v.noCabe('17:00'), true, 'quedan 2 y van 3');
        assert.equal(v.notaHora, 'En JUMP no caben a esta hora');
    });
});
