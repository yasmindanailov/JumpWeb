import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { nuevaEnZona, nuevaEntrada, pantallaOtraEntrada } from './otra-entrada.js';

/**
 * «Añadir otra entrada» (K3 de `specs/otra-zona.md` §4.3, `#878`; «¿Qué zona?» con dos o más, lo dicho al owner en `#882`):
 * la línea de partida, la pantalla con el día y la hora fijos, y lo que pasa con un tiempo que ya está en la reserva. El
 * catálogo, el de la BD local: Kids (de 4 a 7 años) con 1 hora, 2 horas e ilimitada; Jump (desde 8) con 1 y 2 horas.
 */
const kids = { slug: 'kids', name: 'KIDS' };
const jump = { slug: 'jump', name: 'JUMP' };
const productos = [
    { id: 100, type: 'entry', name: 'Kids · 1 hora', zone: kids, duration_min: 60, guest_age_min: 4, guest_age_max: 7 },
    { id: 101, type: 'entry', name: 'Kids · 2 horas', zone: kids, duration_min: 120, guest_age_min: 4, guest_age_max: 7 },
    { id: 102, type: 'entry', name: 'Kids · Ilimitada', zone: kids, guest_age_min: 4, guest_age_max: 7 },
    { id: 103, type: 'entry', name: 'Jump · 1 hora', zone: jump, duration_min: 60, guest_age_min: 8, guest_age_max: null },
    { id: 104, type: 'entry', name: 'Jump · 2 horas', zone: jump, duration_min: 120, guest_age_min: 8, guest_age_max: null },
];
const sab = '2026-10-03';
const dom = '2026-10-04';
// El sábado se vende todo; el domingo, solo Kids.
const precios = {
    100: [{ date: sab, price_cents: 1200 }, { date: dom, price_cents: 1200 }], 101: [{ date: sab, price_cents: 1800 }], 102: [{ date: sab, price_cents: 2400 }],
    103: [{ date: sab, price_cents: 800 }], 104: [{ date: sab, price_cents: 1400 }],
};
const textos = {
    compra: {
        cuando: {
            entrada: 'entrada', entradas: 'entradas', otra_titulo: 'Añadir otra entrada', pregunta_tiempo: '¿Cuánto tiempo?',
            pregunta_cuantos: '¿Cuántos venís?', no_disponible: 'No se vende este día', se_suma: 'Ya está en la reserva: se suma a ella',
            edades_de_a: 'De :min a :max años', edades_desde: 'Desde :min años', extra_sin_hora: 'Elige la hora para saber si cabe.',
            extra_no: 'Ese día, a las :hora, no se puede añadir.',
        },
    },
};
// El pedido: Kids 2 horas el sábado a las 11:00, con Jump 1 hora añadida.
const pedido = { fila: 101, dia: sab, hora: '11:00:00', n: 2, calcetin: { id: 110 }, otras: [{ fila: 103, n: 1 }] };
const base = { productos, precios, dia: sab, zona: 'kids', duracion: 120 };

describe('la línea de partida', () => {
    test('de la OTRA zona (el mockup), su tiempo parecido al del pedido, para una persona y sin complementos', () => {
        assert.deepEqual(nuevaEntrada(base), { zona: 'jump', fila: 104, n: 1, extras: [], elecciones: {} });
        assert.equal(nuevaEntrada({ ...base, duracion: 60 }).fila, 103);
    });

    test('de partida, lo que AÚN NO está en la reserva: quien pulsa «Añadir otra entrada» quiere algo nuevo', () => {
        assert.equal(nuevaEntrada({ ...base, enLaReserva: [101, 104] }).fila, 103, 'Jump 2 h ya está: Jump 1 h');
        assert.deepEqual(nuevaEntrada({ ...base, enLaReserva: [101, 103, 104] }), { zona: 'kids', fila: 100, n: 1, extras: [], elecciones: {} },
            'Jump entera ya está: la zona del pedido, con lo que le queda');
        assert.equal(nuevaEntrada({ ...base, enLaReserva: [100, 101, 102, 103, 104] }).fila, 104, 'todo está: el parecido, que suma gente');
        assert.equal(nuevaEnZona({ zona: 'jump', fila: 103, n: 1 }, { ...base, zona: 'kids', enLaReserva: [101] }).fila, 100, 'en Kids, sin la del pedido');
    });

    test('si la otra no se vende ese día, de la del pedido; si nada se vende, ninguna', () => {
        assert.deepEqual(nuevaEntrada({ ...base, dia: dom }), { zona: 'kids', fila: 100, n: 1, extras: [], elecciones: {} });
        assert.equal(nuevaEntrada({ ...base, dia: '2026-10-05' }), null);
    });

    test('otra zona: su tiempo parecido allí; lo elegido de la fila anterior, fuera', () => {
        const nueva = { zona: 'jump', fila: 104, n: 3, extras: [{ product_id: 140, quantity: 1 }], elecciones: { g: '1' } };

        assert.deepEqual(nuevaEnZona(nueva, { ...base, zona: 'kids' }), { zona: 'kids', fila: 101, n: 3, extras: [], elecciones: {} });
        assert.equal(nuevaEnZona(nueva, { ...base, zona: 'ninja' }), nueva, 'una zona que no existe no cambia nada');
    });
});

describe('la pantalla «Añadir otra entrada»', () => {
    const pantalla = (nueva, extra = {}) => pantallaOtraEntrada({ nueva, pedido, productos, precios, textos, locale: 'es', ...extra });

    test('el día y la hora del pedido, fijos; «¿Qué zona?» con dos o más, con sus edades; su tiempo, su gente', () => {
        const v = pantalla({ zona: 'jump', fila: 104, n: 1, extras: [], elecciones: {} });

        assert.deepEqual([v.props.titulo, v.props.otraEntrada, v.props.fijo], ['Añadir otra entrada', true, 'Sáb 3 · 11:00']);
        assert.deepEqual(v.props.zonas.map((z) => [z.value, z.title, z.description, z.disabled]), [['kids', 'KIDS', 'De 4 a 7 años', false], ['jump', 'JUMP', 'Desde 8 años', false]]);
        assert.deepEqual([v.props.zona, v.props.fila, v.props.cuantos.n, v.props.cuantos.min], ['jump', '104', 1, 1]);
        assert.deepEqual([v.listo, v.falta], [true, null]);
        assert.deepEqual([v.props.otras, v.props.otraZona, v.props.calcetines], [[], '', null], 'ni tarjetas, ni enlace, ni calcetines: son del pedido');
    });

    test('un tiempo que YA está en la reserva se puede elegir: suma gente a su línea, y lo dice', () => {
        const v = pantalla({ zona: 'jump', fila: 104, n: 1, extras: [], elecciones: {} });

        assert.deepEqual(v.props.filas.map((f) => [f.value, f.disabled, f.description]), [['103', false, 'Ya está en la reserva: se suma a ella'], ['104', false, '']]);
        assert.deepEqual(pantalla({ zona: 'kids', fila: 100, n: 1 }).props.filas.map((f) => f.description), ['', 'Ya está en la reserva: se suma a ella', ''], 'la del pedido, también');
    });

    test('ese día, una zona que no vende sale apagada; sin un tiempo que se venda, no está lista y lo que falta es la zona', () => {
        const domingo = { ...pedido, dia: dom };
        const v = pantalla({ zona: 'jump', fila: 104, n: 1 }, { pedido: domingo });

        assert.deepEqual(v.props.zonas.map((z) => [z.value, z.disabled]), [['kids', false], ['jump', true]]);
        assert.deepEqual([v.listo, v.falta], [false, 'pjc-q-zona']);
        assert.deepEqual(pantalla({ zona: 'kids', fila: 101, n: 1 }, { pedido: domingo }).falta, 'pjc-q-tiempo', 'su zona vende otro tiempo: lo que falta es el tiempo');
    });

    test('sus complementos, los de SU ficha (menos los del pedido entero) y, a esa hora, lo que dice el servidor', () => {
        const ficha104 = { id: 104, addons: [{ id: 110, name: 'Calcetines', max_quantity: null }, { id: 140, name: 'Una hora más en Jump', max_quantity: 1, stay_minutes: 60 }] };
        const nueva = { zona: 'jump', fila: 104, n: 1, extras: [{ product_id: 140, quantity: 1 }], elecciones: {} };
        const sinHora = pantalla(nueva, { fichas: { 104: ficha104 }, excluir: [110] }).props.complementos;
        const conHora = pantalla(nueva, { fichas: { 104: ficha104 }, excluir: [110], oferta: { singles: [{ product_id: 140, note: '8,00 €', available: true }], groups: [] } }).props.complementos;

        assert.deepEqual(sinHora.map((c) => [c.id, c.disponible, c.marcada]), [[140, false, false]], 'mientras llega su oferta a esa hora, espera');
        assert.deepEqual(conHora.map((c) => [c.id, c.disponible, c.marcada, c.precio]), [[140, true, true, '8,00 €']]);
    });

    test('con una sola zona de entradas, sin «¿Qué zona?»', () => {
        const soloKids = productos.filter((p) => p.zone === kids);

        assert.equal(pantallaOtraEntrada({ nueva: { zona: 'kids', fila: 100, n: 1 }, pedido, productos: soloKids, precios, textos }).props.zonas, null);
    });
});
