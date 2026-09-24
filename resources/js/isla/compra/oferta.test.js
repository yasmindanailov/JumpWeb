import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    borradorDeIntencion, calcetinDe, cargarDiasDeFilas, cargarFichas, cargarGrupos, horaDelMotor, horaQueCabe, primerDia,
} from './oferta.js';

/**
 * Lo que la pantalla 0 de la isla pide al motor (T3e de `specs/isla-y-landing-nueva.md` §4.10). La API se dobla:
 * se comprueba a quién se pregunta y qué se hace con lo que responde.
 */
describe('los días de las filas', () => {
    test('se piden los de todas las filas, en paralelo y una vez cada una', async () => {
        const pedidas = [];
        const api = {
            get: async (ruta) => {
                pedidas.push(ruta);
                return { ok: true, data: { data: [{ date: '2026-09-25', price_cents: 800, rate_key: 'special' }] } };
            },
        };

        const dias = await cargarDiasDeFilas({ api, ids: [100, 101, 100] });

        assert.deepEqual(pedidas, ['/availability/100/dates', '/availability/101/dates']);
        assert.equal(dias[101][0].price_cents, 800);
    });

    test('una fila que falla se queda sin días, y las demás siguen', async () => {
        const api = { get: async (ruta) => (ruta.includes('101') ? { ok: false } : { ok: true, data: { data: [{ date: '2026-09-25' }] } }) };

        const dias = await cargarDiasDeFilas({ api, ids: [100, 101] });

        assert.equal(dias[100].length, 1);
        assert.deepEqual(dias[101], []);
        assert.equal(primerDia(dias[100]), '2026-09-25');
        assert.equal(primerDia(dias[101]), null);
    });
});

describe('el complemento por cantidad de una entrada', () => {
    const calcetines = { id: 110, price_cents: 200, allow_extra: true, per_guest: false, choice_group: null, mandatory: false, included: false, max_quantity: null };

    test('se reconoce por su FORMA, no por su nombre', () => {
        assert.deepEqual(calcetinDe({ addons: [calcetines] }), { id: 110, price_cents: 200, max_quantity: null });
    });

    test('un menú (grupo de elección), un complemento por invitado o uno obligatorio no son esa pregunta', () => {
        assert.equal(calcetinDe({ addons: [{ ...calcetines, choice_group: 'menu' }] }), null);
        assert.equal(calcetinDe({ addons: [{ ...calcetines, per_guest: true }] }), null);
        assert.equal(calcetinDe({ addons: [{ ...calcetines, mandatory: true }] }), null);
        assert.equal(calcetinDe({ addons: [{ ...calcetines, allow_extra: false }] }), null);
        assert.equal(calcetinDe(null), null);
    });
});

describe('la intención de la landing', () => {
    const productos = [
        { id: 100, type: 'entry', zone: { slug: 'kids' } },
        { id: 101, type: 'entry', zone: { slug: 'kids' } },
        { id: 103, type: 'entry', zone: { slug: 'jump' } },
        { id: 105, type: 'pack', zone: { slug: 'cumpleanos' } },
    ];

    test('abrir en una entrada: su zona, con esa fila', () => {
        assert.deepEqual([borradorDeIntencion({ type: 'product', id: 101 }, productos)].map((b) => [b.zona, b.fila, b.elegirZona]), [['kids', 101, false]]);
    });

    test('abrir en una zona: su primera fila', () => {
        const b = borradorDeIntencion({ type: 'zone', slug: 'jump' }, productos);
        assert.deepEqual([b.zona, b.fila], ['jump', 103]);
    });

    test('sin intención o con algo que el catálogo no tiene: «Para hoy», eligiendo zona', () => {
        for (const intencion of [null, { type: 'zone', slug: 'bar' }, { type: 'product', id: 999 }]) {
            const b = borradorDeIntencion(intencion, productos);
            assert.deepEqual([b.zona, b.fila, b.elegirZona], [null, null, true], JSON.stringify(intencion));
        }
    });

    test('un pack, «los packs» o una zona que solo vende packs abren su FIESTA (T3e·5): sin edad, sin día', () => {
        for (const intencion of [{ type: 'packs' }, { type: 'product', id: 105 }, { type: 'zone', slug: 'cumpleanos' }]) {
            const b = borradorDeIntencion(intencion, productos);

            assert.deepEqual([b.fiesta, b.zona, b.fila, b.edad, b.dia, b.elegirZona], [true, 'cumpleanos', 105, null, null, false], JSON.stringify(intencion));
        }
    });
});

describe('lo que la fiesta pide al motor (T3e·5)', () => {
    const api = (respuestas) => {
        const llamadas = [];

        return {
            llamadas,
            get: async (ruta) => { llamadas.push(['get', ruta]); return respuestas[ruta] ?? { ok: false }; },
            post: async (ruta, cuerpo) => { llamadas.push(['post', ruta, cuerpo]); return respuestas[ruta] ?? { ok: false }; },
        };
    };

    test('las fichas de los packs, en paralelo; la que falla se queda fuera', async () => {
        const a = api({ '/catalog/products/105': { ok: true, data: { id: 105 } } });

        assert.deepEqual(await cargarFichas({ api: a, ids: [105, 106, 105] }), { 105: { id: 105 } });
        assert.equal(a.llamadas.length, 2, 'sin repetir');
    });

    test('los menús SIN día ni hora: el cuerpo no los lleva (el servidor rechaza `null` en ellos)', async () => {
        const a = api({ '/catalog/products/105/addons': { ok: true, data: { groups: [{ key: 'menu', options: [] }] } } });

        assert.deepEqual(await cargarGrupos({ api: a, productId: 105, quantity: 8 }), [{ key: 'menu', options: [] }]);
        assert.deepEqual(a.llamadas[0][2], { quantity: 8, addons: [], choices: [] });
        assert.equal('date' in a.llamadas[0][2] || 'time' in a.llamadas[0][2], false);
        assert.deepEqual(await cargarGrupos({ api: api({}), productId: 105, quantity: 8 }), [], 'un fallo, sin menús: no revienta');
    });
});

describe('la hora que se elige', () => {
    const ofrecidas = [{ time: '17:00:00', available: 3, sellable: true }, { time: '18:00:00', available: 0, sellable: true }];

    test('la del selector se traduce a la del motor, con sus segundos', () => {
        assert.equal(horaDelMotor(ofrecidas, '17:00'), '17:00:00');
        assert.equal(horaDelMotor(ofrecidas, '09:00'), null);
    });

    test('una hora deja de caber si no hay sitio para todos', () => {
        assert.equal(horaQueCabe(ofrecidas, '17:00:00', 3), true);
        assert.equal(horaQueCabe(ofrecidas, '17:00:00', 4), false);
        assert.equal(horaQueCabe(ofrecidas, '18:00:00', 1), false);
    });
});
