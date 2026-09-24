import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    campoDeEdad, edadesDe, eleccionesDe, menuElegido, menusDe, packPorEdad, packsDeFiesta, pantallaCuandoFiesta, rotuloDelPack,
} from './fiesta.js';

/**
 * La pantalla 0 de una fiesta (T3e·5 de `specs/isla-y-landing-nueva.md` §4.10). Las fichas y los menús tienen la FORMA
 * real de la API, medida en local el 2026-09-24 (`GET /catalog/products/{105,106}` y su `POST …/addons`).
 */
const textos = {
    compra: {
        cuando: {
            banda: 'Cuándo y cuántos', continuar: 'Continuar', titulo_fiesta: 'Un cumpleaños', hoy: 'hoy', tarifa_especial: 'tarifa especial',
            pregunta_edad: '¿Cuántos años cumple?', pregunta_ninos: '¿Cuántos niños vienen?', pregunta_dia_fiesta: '¿Qué día?',
            pregunta_hora: '¿A qué hora?', pregunta_menu: '¿Qué menú?', nino: 'niño', ninos: 'niños', minimo: 'Mínimo :n.',
            ajusta: '¿Aún no sabes cuántos seréis? Reserva con :n y ajusta hasta :horas h antes. Si vienen menos, pagas menos.',
            pack_de_a: ':pack, de :min a :max años', pack_desde: ':pack, desde :min años',
            menu_incluido: ':menu, incluido', menu_mas: ':menu, :precio más por niño',
        },
        pagar: { hoy_pagas: 'Hoy pagas :importe' },
    },
    pieza: { quedan: 'Quedan :n' },
};
const nb = (s) => s.replace(/\s/g, ' ');
const zone = { id: 3, slug: 'cumpleanos', name: 'Cumpleaños' };
const kids = { id: 105, name: 'Pack Kids', guest_age_min: 4, guest_age_max: 7, min_quantity: 8, max_quantity: 20, event_fields: [{ key: 'age', type: 'celebrant_age', required: true, min: 4, max: 7 }] };
const jump = { id: 106, name: 'Pack Jump', guest_age_min: 8, min_quantity: 8, max_quantity: 20, event_fields: [{ key: 'age', type: 'celebrant_age', required: true, min: 8, max: null }] };
const excursion = { id: 395, name: 'Excursión', min_quantity: 20, event_fields: [] };
const productos = [
    { id: 100, type: 'entry', zone: { slug: 'kids' } },
    { id: 105, type: 'pack', zone }, { id: 106, type: 'pack', zone },
    { id: 395, type: 'pack', zone: { slug: 'excursiones' } },
];
const fichas = { 105: kids, 106: jump, 395: excursion };
const grupos = [{
    key: 'menu',
    options: [
        { product_id: 107, product_name: 'Menú 1', price_cents: 0, is_included: true, selected: true, features: ['Refresco o zumo', 'Sándwich mixto'], gifts: ['Cono de chuches'] },
        { product_id: 108, product_name: 'Menú 2', price_cents: 200, is_included: false, selected: false, features: ['Pizza o perrito'], gifts: [] },
    ],
}];

describe('la edad elige el pack', () => {
    test('los packs de fiesta son los de la zona que preguntan la edad', () => {
        assert.deepEqual(packsDeFiesta(productos, fichas, 'cumpleanos').map((p) => p.id), [105, 106]);
        assert.deepEqual(packsDeFiesta(productos, fichas, 'excursiones'), [], 'una excursión no pregunta la edad de nadie');
        assert.equal(campoDeEdad(kids).key, 'age');
    });

    test('cada edad cae en su tramo; el abierto no tiene final', () => {
        const packs = [kids, jump];

        assert.equal(packPorEdad(packs, 4).id, 105);
        assert.equal(packPorEdad(packs, 7).id, 105);
        assert.equal(packPorEdad(packs, 8).id, 106);
        assert.equal(packPorEdad(packs, 15).id, 106);
        assert.equal(packPorEdad(packs, 3), null);
    });

    test('las edades van del menor tramo al mayor; con uno abierto, cinco más que su mínimo (la rejilla del diseño)', () => {
        assert.deepEqual(edadesDe([kids, jump]), [4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);
        assert.deepEqual(edadesDe([kids]), [4, 5, 6, 7]);
        assert.deepEqual(edadesDe([]), []);
    });

    test('el pack elegido se dice con su tramo', () => {
        assert.equal(rotuloDelPack(kids, textos), 'Pack Kids, de 4 a 7 años');
        assert.equal(rotuloDelPack(jump, textos), 'Pack Jump, desde 8 años');
        assert.equal(rotuloDelPack(null, textos), '');
    });
});

describe('los menús', () => {
    test('como el diseño, con el precio PUBLICADO de la opción, y sus platos en una frase', () => {
        const [uno, dos] = menusDe(grupos, { textos });

        assert.deepEqual(uno, { value: '107', title: 'Menú 1, incluido', description: 'Refresco o zumo, sándwich mixto y cono de chuches.' });
        assert.equal(nb(dos.title), 'Menú 2, 2 € más por niño');
        assert.equal(dos.description, 'Pizza o perrito.');
    });

    test('el elegido es el que el servidor dejó elegido, y viaja en SU grupo', () => {
        assert.equal(menuElegido(grupos), '107');
        assert.deepEqual(eleccionesDe(grupos, '108'), [{ group: 'menu', product_id: 108 }]);
        assert.deepEqual(eleccionesDe([], '108'), [], 'sin grupo no se inventa una elección');
        assert.equal(menuElegido([]), null);
    });
});

describe('la pantalla 0 de una fiesta', () => {
    const base = {
        packs: [kids, jump], textos, locale: 'es', hoy: '2026-09-24', corte: 24, grupos,
        precios: { 105: [{ date: '2026-09-25', price_cents: 1695, rate_key: 'special' }, { date: '2026-09-28', price_cents: 1495, rate_key: 'normal' }] },
        horas: [{ time: '17:00:00', available: 60, max_quantity: 20 }, { time: '18:00:00', available: 5, max_quantity: 20 }],
    };

    test('sin edad: las cinco preguntas, ningún pack y el mínimo con su plazo de ajuste', () => {
        const { props, listo, ck } = pantallaCuandoFiesta({ ...base, borrador: { edad: null, n: null, dia: null, hora: null, menu: '107', fila: 105 } });

        assert.equal(props.titulo, 'Un cumpleaños');
        assert.equal(props.preguntas.length, 5);
        assert.equal(props.edades.length, 10);
        assert.equal(props.pack, '');
        assert.equal(props.ninos.n, 8, 'nace en el mínimo del pack');
        assert.equal(nb(props.ninos.pista), 'Mínimo 8. ¿Aún no sabes cuántos seréis? Reserva con 8 y ajusta hasta 24 h antes. Si vienen menos, pagas menos.');
        assert.equal(props.horas, null, 'sin día no hay horas');
        assert.equal(listo, false);
        assert.equal(ck.action.disabled, true);
        assert.equal(ck.summary, null);
    });

    test('sin el corte publicado, solo el mínimo (no se promete un plazo que no se sabe)', () => {
        const { props } = pantallaCuandoFiesta({ ...base, corte: undefined, borrador: { edad: null, n: 8, dia: null, hora: null, menu: null, fila: 105 } });

        assert.equal(props.ninos.pista, 'Mínimo 8.');
    });

    test('con edad, día y hora: el pack, las horas que caben, la línea, su total y la SEÑAL de hoy', () => {
        const linea = { total_cents: 18950, has_deposit: true, deposit_cents: 5000 };
        const { props, listo, ck } = pantallaCuandoFiesta({
            ...base, linea, maximo: 20, borrador: { edad: 5, n: 10, dia: '2026-09-25', hora: '17:00:00', menu: '108', fila: 105 },
        });

        assert.equal(props.pack, 'Pack Kids, de 4 a 7 años');
        assert.equal(props.edad, '5');
        assert.equal(props.hora, '17:00');
        assert.deepEqual(props.horas.map((h) => [h.time, Boolean(h.disabled)]), [['17:00', false], ['18:00', true]], 'a las 18:00 quedan 5 y vienen 10');
        assert.equal(props.ninos.max, 20);
        assert.equal(listo, true);
        assert.equal(ck.summary, 'Pack Kids · vie 25, 17:00 · 10 niños');
        assert.equal(nb(ck.total), '189,50 €');
        assert.equal(nb(ck.today), 'Hoy pagas 50 €');
        assert.equal(ck.action.disabled, false);
    });

    test('una edad sin tramo no elige pack ni deja seguir', () => {
        const { props, listo } = pantallaCuandoFiesta({ ...base, packs: [kids], borrador: { edad: 12, n: 8, dia: '2026-09-25', hora: '17:00:00', menu: null, fila: 105 } });

        assert.equal(props.pack, '');
        assert.equal(listo, false);
    });
});
