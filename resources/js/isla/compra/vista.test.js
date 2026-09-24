import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { diaCorto, diaLargo, euros, horasCercanas, horasDelSelector, pantallaCuando, tiraDias, zonasConEntradas } from './vista.js';

/**
 * La vista pura de la compra de la isla (T3e de `specs/isla-y-landing-nueva.md` §4.10): del estado del motor a las
 * props de la pantalla 0. Los datos tienen la FORMA real de la API, medida en local el 2026-09-24 (el catálogo, los
 * días de `availability/{id}/dates`, las horas de `…/times` y el complemento de los calcetines).
 *
 * Los textos son los del grupo `isla` de `lang/es` (copiados aquí los que se prueban).
 */
const textos = {
    pieza: { quedan: 'Quedan :n' },
    compra: {
        cuando: {
            banda: 'Cuándo y cuántos', titulo_hoy: 'Para hoy', ahorro: ':importe menos que dos de 1 hora', continuar: 'Continuar',
            titulo_zona: 'Entrada :zona', hoy: 'hoy', tarifa_especial: 'tarifa especial', pregunta_dia: '¿Qué día venís?',
            pregunta_hora: '¿A qué hora?', pregunta_tiempo: '¿Cuánto tiempo?', pregunta_cuantos: '¿Cuántos venís?',
            pregunta_calcetines: '¿Calcetines antideslizantes?', pista_calcetines: ':precio el par. Si ya los tenéis, traedlos.',
            entrada: 'entrada', entradas: 'entradas', par: 'par', pares: 'pares', no_disponible: 'No se vende este día',
        },
    },
};

const kids = { id: 2, slug: 'kids', name: 'KIDS' };
const jump = { id: 1, slug: 'jump', name: 'JUMP' };
const productos = [
    { id: 100, type: 'entry', name: 'Kids · 1 hora', zone: kids, duration_min: 60 },
    { id: 101, type: 'entry', name: 'Kids · 2 horas', zone: kids, duration_min: 120 },
    { id: 102, type: 'entry', name: 'Kids · Ilimitada', zone: kids },
    { id: 103, type: 'entry', name: 'Jump · 1 hora', zone: jump, duration_min: 60 },
    { id: 105, type: 'pack', name: 'Pack Cumpleaños KIDS', zone: { id: 3, slug: 'cumpleanos', name: 'Cumpleaños' } },
];
const precios = {
    100: [{ date: '2026-09-24', price_cents: 640, rate_key: 'normal' }, { date: '2026-09-25', price_cents: 800, rate_key: 'special' }],
    101: [{ date: '2026-09-24', price_cents: 960, rate_key: 'normal' }, { date: '2026-09-25', price_cents: 1200, rate_key: 'special' }],
    102: [{ date: '2026-09-24', price_cents: 1440, rate_key: 'normal' }],
};
const horas = [
    { time: '17:00:00', available: 20, max_quantity: 20, sellable: true },
    { time: '18:00:00', available: 1, max_quantity: 1, sellable: true },
    { time: '19:00:00', available: 0, max_quantity: 0, sellable: true },
];
const borrador = (cambios = {}) => ({ zona: 'kids', elegirZona: false, dia: '2026-09-25', hora: null, fila: 100, n: 2, cal: 0, otra: null, ...cambios });
const estado = (cambios = {}) => ({
    borrador: borrador(), productos, precios, horas, cargandoHoras: false, maximo: null, minimo: 1, umbral: 8,
    calcetin: { id: 110, price_cents: 200, max_quantity: null }, linea: null, textos, locale: 'es', hoy: '2026-09-24', ...cambios,
});

describe('los formatos del diseño', () => {
    test('un importe redondo va sin decimales, y el resto con dos', () => {
        assert.equal(euros(800, 'es'), '8 €');
        assert.equal(euros(640, 'es'), '6,40 €');
    });

    test('el día corto y el largo, sin el punto ni la coma que pone el navegador', () => {
        assert.equal(diaCorto('2026-09-26', 'es'), 'sáb 26');
        assert.equal(diaLargo('2026-09-26', 'es'), 'Sábado 26 de septiembre');
    });
});

describe('la tira de días y el selector de horas', () => {
    test('hoy se llama «hoy» y la tarifa especial se marca y se dice', () => {
        const [primero, segundo] = tiraDias(precios[100], { hoy: '2026-09-24', locale: 'es', textos });

        assert.deepEqual(primero, { id: '2026-09-24', n: 24, label: 'hoy', special: false, aria: 'Jueves 24 de septiembre' });
        assert.deepEqual(segundo, { id: '2026-09-25', n: 25, label: 'vie', special: true, aria: 'Viernes 25 de septiembre, tarifa especial' });
    });

    test('una hora sin sitio para todos sale apagada, y con «Quedan N» si le queda alguno', () => {
        assert.deepEqual(horasDelSelector(horas, { gente: 2, textos }), [
            { time: '17:00', left: 20 },
            { time: '18:00', left: 1, disabled: true, note: 'Quedan 1' },
            { time: '19:00', left: 0, disabled: true },
        ]);
    });

    test('las horas cercanas: las cuatro con sitio más próximas a la perdida, en orden de reloj (T3e·6)', () => {
        const libres = ['16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'].map((time) => ({ time, left: 5 }));
        const dia = libres.map((s) => (s.time === '17:00' ? { ...s, left: 0, disabled: true } : s));

        // 16:00 y 22:00 quedan a la misma distancia de las 19:00, y solo cabe una: gana la de antes.
        assert.deepEqual(horasCercanas(dia, '19:00').map((s) => s.time), ['16:00', '18:00', '20:00', '21:00']);
        // Una hora apagada no se ofrece aunque le quede alguna plaza (no cabe la gente que se pide).
        const justa = dia.map((s) => (s.time === '18:00' ? { ...s, left: 1, disabled: true, note: 'Quedan 1' } : s));

        assert.deepEqual(horasCercanas(justa, '19:00').map((s) => s.time), ['16:00', '20:00', '21:00', '22:00']);
        // Si la perdida ya no sale en la lista, las primeras del día; sin horas, ninguna.
        assert.deepEqual(horasCercanas(libres.slice(0, 5), '15:00').map((s) => s.time), ['16:00', '17:00', '18:00', '19:00']);
        assert.deepEqual(horasCercanas([], '17:00'), []);
    });
});

describe('la pantalla 0 de las entradas', () => {
    test('las zonas que venden entradas, sin los packs', () => {
        assert.deepEqual(zonasConEntradas(productos).map((z) => z.slug), ['kids', 'jump']);
    });

    test('las filas son las entradas de la zona con el precio DEL DÍA, y la de dos horas dice lo que se ahorra', () => {
        const { props } = pantallaCuando(estado());

        assert.equal(props.titulo, 'Entrada KIDS');
        assert.deepEqual(props.filas.map((f) => [f.value, f.price, f.highlight, f.disabled]), [
            ['100', '8 €', '', false],
            ['101', '12 €', '4 € menos que dos de 1 hora', false],
            ['102', '', '', true],
        ]);
        assert.equal(props.filas[2].description, 'No se vende este día', 'la ilimitada no se vende el viernes: se apaga y lo dice');
    });

    /** Visto en el navegador (T3e·2b): mientras llegaban los días, las tres filas decían «No se vende este día». */
    test('mientras llegan los días de una fila no se dice que no se vende: sin precio, pero sin apagar', () => {
        const { props } = pantallaCuando(estado({ precios: {} }));

        assert.deepEqual(props.filas.map((f) => [f.price, f.disabled, f.description]), [['', false, ''], ['', false, ''], ['', false, '']]);
    });

    test('las cantidades y el umbral salen de los DATOS, no del diseño', () => {
        const { props } = pantallaCuando(estado({ maximo: 12, minimo: 1, umbral: 8 }));

        assert.deepEqual(props.cuantos, { n: 2, uno: 'entrada', varios: 'entradas', min: 1, max: 12 });
        assert.equal(props.umbral, 8);
        assert.deepEqual(props.calcetines, { n: 0, uno: 'par', varios: 'pares', pista: '2 € el par. Si ya los tenéis, traedlos.', max: 40 });
    });

    test('sin complemento por cantidad en la entrada, no hay pregunta de calcetines', () => {
        assert.equal(pantallaCuando(estado({ calcetin: null })).props.calcetines, null);
    });

    test('sin hora no se puede continuar; con hora, la línea y el total que resolvió el servidor', () => {
        const sin = pantallaCuando(estado());
        assert.equal(sin.listo, false);
        assert.equal(sin.ck.action.disabled, true);
        assert.equal(sin.ck.summary, 'Kids · 1 hora · vie 25 · 2 entradas');
        assert.equal(sin.ck.total, null);

        const con = pantallaCuando(estado({ borrador: borrador({ hora: '17:00:00' }), linea: { total_cents: 2000 } }));
        assert.equal(con.listo, true);
        assert.equal(con.ck.action.disabled, false);
        assert.equal(con.ck.summary, 'Kids · 1 hora · vie 25, 17:00 · 2 entradas');
        assert.equal(con.ck.total, '20 €', 'el total lo dio el servidor, con los calcetines dentro');
        assert.equal(con.props.hora, '17:00');
    });

    test('sin zona («Para hoy») se elige primero la zona, y no hay preguntas ni resumen hasta entonces', () => {
        const { props, ck } = pantallaCuando(estado({ borrador: borrador({ zona: null, elegirZona: true, fila: null }) }));

        assert.equal(props.titulo, 'Para hoy');
        assert.deepEqual(props.zonas, [{ value: 'kids', title: 'KIDS' }, { value: 'jump', title: 'JUMP' }]);
        assert.equal(props.preguntas, null);
        assert.equal(ck.summary, null);
    });
});
