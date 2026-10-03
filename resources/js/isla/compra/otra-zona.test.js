import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
    cargarHorasDe, conOfertaElDia, edadesDe, filaParecida, finDe, horasQueNoCaben, otraDeLaPantalla, otraDelDia, otraNueva, otrasZonas,
} from './otra-zona.js';

/**
 * La otra zona en la pantalla 0 de la isla (K2 de `specs/otra-zona.md` §4.2, `#878`; su tarjeta COMPLETA, K2·b, §4.7,
 * `#882`): el enlace que la nombra (D3-B), su tarjeta —para quién, su tiempo (de partida, el parecido al del pedido), su
 * gente y sus complementos—, las horas en las que caben TODAS las líneas y, si los grupos salen a horas distintas, a cuál
 * sale cada uno. El catálogo, el de la BD local: Kids (de 4 a 7 años) con 1 hora, 2 horas e ilimitada; Jump (desde 8) con
 * 1 y 2 horas; la ilimitada, SIN `duration_min` (la API no publica la clave).
 */
const kids = { slug: 'kids', name: 'KIDS' };
const jump = { slug: 'jump', name: 'JUMP' };
const productos = [
    { id: 100, type: 'entry', name: 'Kids · 1 hora', zone: kids, duration_min: 60, guest_age_min: 4, guest_age_max: 7 },
    { id: 101, type: 'entry', name: 'Kids · 2 horas', zone: kids, duration_min: 120, guest_age_min: 4, guest_age_max: 7 },
    { id: 102, type: 'entry', name: 'Kids · Ilimitada', zone: kids, guest_age_min: 4, guest_age_max: 7 },
    { id: 103, type: 'entry', name: 'Jump · 1 hora', zone: jump, duration_min: 60, guest_age_min: 8, guest_age_max: null },
    { id: 104, type: 'entry', name: 'Jump · 2 horas', zone: jump, duration_min: 120, guest_age_min: 8, guest_age_max: null },
    { id: 105, type: 'pack', name: 'Pack Cumpleaños KIDS', zone: { slug: 'cumpleanos', name: 'Cumpleaños' } },
];
const sab = '2026-10-03';
const dom = '2026-10-04';
const lun = '2026-10-05';
// El sábado se vende todo; el domingo, Jump nada; el lunes, Jump solo 1 hora.
const precios = {
    100: [{ date: sab, price_cents: 1200 }], 101: [{ date: sab, price_cents: 1800 }], 102: [{ date: sab, price_cents: 2400 }],
    103: [{ date: sab, price_cents: 800 }, { date: lun, price_cents: 700 }], 104: [{ date: sab, price_cents: 1400 }],
};
const textos = {
    compra: {
        cuando: {
            entrada: 'entrada', entradas: 'entradas',
            otra_zona: '¿Alguien va a la otra zona? Añádelo a la misma reserva',
            otra_zona_de: '¿Alguien va a :zona? Añádelo a la misma reserva',
            otra_no_vende: ':zona no se vende este día',
            otra_no_cabe: 'En :zona no caben a esta hora',
            no_disponible: 'No se vende este día',
            ahorro: ':importe menos que dos de 1 hora',
            edades_de_a: 'De :min a :max años', edades_desde: 'Desde :min años', edades_hasta: 'Hasta :max años',
            extra_sin_hora: 'Elige la hora para saber si cabe.', extra_no: 'Ese día, a las :hora, no se puede añadir.',
        },
    },
};
const espacios = (s) => String(s).replace(/\s/g, ' ');
// Sin las uniones de palabra del tramo horario (`pantalla-cuando.js::rangoHorario`): se comprueban aparte.
const plano = (s) => (s == null ? s : String(s).replace(/⁠/g, ''));

describe('cuáles son «la otra zona»', () => {
    test('las zonas de ENTRADAS menos la del pedido, cada una con TODAS sus filas (sus tiempos); los packs no', () => {
        assert.deepEqual(otrasZonas(productos, 'kids').map((z) => [z.slug, z.filas.map((f) => f.id)]), [['jump', [103, 104]]]);
        assert.deepEqual(otrasZonas(productos, 'jump').map((z) => [z.slug, z.filas.map((f) => f.id)]), [['kids', [100, 101, 102]]]);
    });

    test('se vende ese día si ALGUNA de sus filas se vende; sin sus días todavía, ninguna (no se promete)', () => {
        const zonas = otrasZonas(productos, 'kids');

        assert.equal(conOfertaElDia(zonas, precios, sab).length, 1);
        assert.equal(conOfertaElDia(zonas, precios, lun).length, 1, 'el lunes, solo su 1 hora: basta');
        assert.equal(conOfertaElDia(zonas, precios, dom).length, 0, 'el domingo Jump no se vende');
        assert.equal(conOfertaElDia(zonas, {}, sab).length, 0);
    });
});

describe('el tiempo de partida: el PARECIDO al del pedido (`#882`)', () => {
    const deJump = productos.filter((p) => p.zone === jump);
    const deKids = productos.filter((p) => p.zone === kids);

    test('el MISMO tiempo, si su zona lo tiene', () => {
        assert.equal(filaParecida(deJump, 120).id, 104, 'Kids 2 horas → Jump 2 horas');
        assert.equal(filaParecida(deJump, 60).id, 103);
        assert.equal(filaParecida(deKids, 120).id, 101, 'y al revés');
        assert.equal(filaParecida(deKids, null).id, 102, 'una ilimitada con otra ilimitada');
    });

    test('si no lo tiene, el más largo SIN PASARSE —una ilimitada no tiene tope—; si todos se pasan, el primero', () => {
        assert.equal(filaParecida(deJump, null).id, 104, 'Kids ilimitada → Jump 2 horas');
        assert.equal(filaParecida(deJump, 90).id, 103, '90 minutos → 1 hora, no 2');
        assert.equal(filaParecida(deJump, 30).id, 103, 'todas se pasan: la primera');
        assert.equal(filaParecida([], 60), null);
    });

    test('entre dos del mismo tiempo, la primera del catálogo', () => {
        const dos = [{ id: 1, duration_min: 60 }, { id: 2, duration_min: 60 }];

        assert.equal(filaParecida(dos, 120).id, 1);
        assert.equal(filaParecida(dos, 60).id, 1);
    });

    test('la que se AÑADE: su fila parecida entre las que se venden ese día, para UNA persona y sin complementos', () => {
        const zonas = otrasZonas(productos, 'kids');

        assert.deepEqual(otraNueva(zonas, precios, sab, 120), { fila: 104, n: 1, extras: [], elecciones: {} });
        assert.equal(otraNueva(zonas, precios, sab, 60).fila, 103);
        assert.equal(otraNueva(zonas, precios, sab, null).fila, 104);
        assert.equal(otraNueva(zonas, precios, lun, 120).fila, 103, 'el lunes su 2 horas no se vende: la que sí');
        assert.equal(otraNueva(zonas, precios, dom, 120), null);
    });

    test('al CAMBIAR DE DÍA, si su tiempo no se vende y otro sí, el parecido: con su gente, sin lo elegido de la otra fila', () => {
        const zonas = otrasZonas(productos, 'kids');
        const otra = { fila: 104, n: 2, extras: [{ product_id: 140, quantity: 1 }], elecciones: { calcetin: '7' } };

        assert.deepEqual(otraDelDia(otra, zonas, precios, lun, 120), { fila: 103, n: 2, extras: [], elecciones: {} });
        assert.equal(otraDelDia(otra, zonas, precios, sab, 120), otra, 'se vende ese día: la misma');
        assert.equal(otraDelDia(otra, zonas, precios, dom, 120), otra, 'su zona no se vende: la misma (la tarjeta lo dice)');
        assert.equal(otraDelDia(otra, zonas, { 103: precios[103] }, lun, 120), otra, 'sin los días de su fila: no se mueve');
        assert.equal(otraDelDia(null, zonas, precios, lun, 120), null);
    });
});

describe('para quién y hasta qué hora', () => {
    test('las edades de su fila, del panel', () => {
        assert.equal(edadesDe({ guest_age_min: 4, guest_age_max: 7 }, textos), 'De 4 a 7 años');
        assert.equal(edadesDe({ guest_age_min: 8, guest_age_max: null }, textos), 'Desde 8 años');
        assert.equal(edadesDe({ guest_age_min: null, guest_age_max: 7 }, textos), 'Hasta 7 años');
        assert.equal(edadesDe({ guest_age_min: 0, guest_age_max: 7 }, textos), 'Hasta 7 años', 'desde 0 no dice nada');
        assert.equal(edadesDe({}, textos), '');
        assert.equal(edadesDe(null, textos), '');
    });

    test('la hora de salida: la de entrada más lo que se queda, sin pasar de medianoche (como el aforo)', () => {
        assert.equal(finDe('11:00', 120), '13:00');
        assert.equal(finDe('11:00:00', 180), '14:00');
        assert.equal(finDe('17:30', 45), '18:15');
        assert.equal(finDe('23:00', 120), '24:00');
        assert.equal(finDe(null, 60), null);
        assert.equal(finDe('11:00', null), null);
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
    // La ficha de «Jump · 2 horas»: los calcetines (que se preguntan una vez para todos) y su hora extra, que alarga 60.
    const ficha104 = {
        id: 104,
        addons: [
            { id: 110, name: 'Calcetines antideslizantes', max_quantity: null, stay_minutes: null, stay_per_unit: false },
            { id: 140, name: 'Una hora más en Jump', features: ['60 minutos más'], max_quantity: 1, stay_minutes: 60, stay_per_unit: false },
        ],
    };
    const conHora140 = [{ product_id: 140, note: '8,00 € por entrada que se queda', available: true }];
    const pantalla = (b, extra = {}) => otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, hora: null, ...b }, productos, precios, textos, locale: 'es', ...extra });

    test('sin la otra: el enlace la NOMBRA con una sola zona que vende ese día (D3-B); sin ninguna, nada', () => {
        const con = pantalla({ otra: null });

        assert.equal(con.enlace, '¿Alguien va a JUMP? Añádelo a la misma reserva');
        assert.equal(con.tarjeta, null);
        assert.equal(pantalla({ dia: dom, otra: null }).enlace, '', 'ese día no se vende: no se ofrece');
        assert.equal(pantalla({ dia: null, otra: null }).enlace, '', 'sin día, nada que prometer');
    });

    test('con dos zonas o más que venden ese día, el genérico', () => {
        const tres = [...productos, { id: 120, type: 'entry', name: 'Ninja · 1 hora', zone: { slug: 'ninja', name: 'NINJA' }, duration_min: 60 }];

        assert.equal(otraDeLaPantalla({ borrador: { zona: 'kids', dia: sab, otra: null }, productos: tres, precios: { ...precios, 120: [{ date: sab, price_cents: 900 }] }, textos }).enlace,
            '¿Alguien va a la otra zona? Añádelo a la misma reserva');
    });

    test('con la otra: su tarjeta —su zona, para quién, sus tiempos con su precio de ese día, el elegido y su gente—', () => {
        const v = pantalla({ otra: { fila: 103, n: 2, extras: [], elecciones: {} } });

        assert.equal(v.enlace, '');
        assert.deepEqual([v.tarjeta.titulo, v.tarjeta.pista, v.tarjeta.noSeVende, v.tarjeta.fila, v.tarjeta.n], ['JUMP', 'Desde 8 años', false, '103', 2]);
        assert.deepEqual(v.tarjeta.filas.map((f) => [f.value, f.title, espacios(f.price), f.disabled, espacios(f.highlight)]), [
            ['103', 'Jump · 1 hora', '8 €', false, ''],
            ['104', 'Jump · 2 horas', '14 €', false, '2 € menos que dos de 1 hora'],
        ]);
        assert.equal(v.resumen, 'Jump · 1 hora · 2 entradas');
        assert.deepEqual([v.bloquea, v.falta], [false, null]);
    });

    test('si su zona NO se vende ese día, la tarjeta lo dice en rojo y lo que falta es ella (sin quitarla a escondidas)', () => {
        const v = pantalla({ dia: dom, otra: { fila: 103, n: 1 } });

        assert.deepEqual([v.bloquea, v.falta, v.tarjeta.pista, v.tarjeta.noSeVende], [true, 'pjc-q-otra', 'JUMP no se vende este día', true]);
        assert.equal(pantalla({ dia: dom, otra: { fila: 103, n: 1 } }, { precios: { 100: [] } }).bloquea, false, 'sin sus días todavía, no se dice');
    });

    test('si solo SU TIEMPO no se vende ese día, lo que falta es su «¿Cuánto tiempo?», con esa opción apagada', () => {
        const v = pantalla({ dia: lun, otra: { fila: 104, n: 1 } });

        assert.deepEqual([v.bloquea, v.falta, v.tarjeta.noSeVende, v.tarjeta.pista], [true, 'pjc-q-otra-tiempo', false, 'Desde 8 años']);
        assert.deepEqual(v.tarjeta.filas.map((f) => [f.value, f.disabled, f.description]), [['103', false, ''], ['104', true, 'No se vende este día']]);
    });

    test('sus COMPLEMENTOS: los de SU ficha, menos los que se preguntan para todos; sin hora, esperan; nunca marcados', () => {
        const b = { otra: { fila: 104, n: 1, extras: [], elecciones: {} } };
        const sinHora = pantalla(b, { fichaOtra: ficha104, excluir: [110] }).tarjeta.complementos;

        assert.deepEqual(sinHora.map((c) => [c.id, c.forma, c.disponible, c.marcada, c.porQue, c.minutos]), [[140, 'si-no', false, false, 'Elige la hora para saber si cabe.', 60]]);
        assert.deepEqual(pantalla(b, { fichaOtra: { ...ficha104, id: 103 }, excluir: [110] }).tarjeta.complementos, [], 'la ficha de OTRA fila (llegando) no pinta nada');
    });

    test('con hora, lo que el servidor ofrece a esa hora: con su precio, y lo elegido, marcado', () => {
        const v = pantalla({ hora: '11:00:00', otra: { fila: 104, n: 1, extras: [{ product_id: 140, quantity: 1 }], elecciones: {} } },
            { fichaOtra: ficha104, excluir: [110], conHoraOtra: conHora140 });
        const [hora140] = v.tarjeta.complementos;

        assert.deepEqual([hora140.disponible, hora140.marcada, hora140.n, hora140.precio], [true, true, 1, '8,00 € por entrada que se queda']);
        assert.equal(pantalla({ hora: '11:00:00', otra: { fila: 104, n: 1, extras: [{ product_id: 140, quantity: 1 }] } }, { fichaOtra: ficha104, excluir: [110], conHoraOtra: [] })
            .tarjeta.complementos[0].porQue, 'Ese día, a las 11:00, no se puede añadir.', 'a esa hora no cabe: apagada y diciendo por qué');
    });

    test('si los grupos salen a horas DISTINTAS, cada uno dice la suya; si coinciden, nada que decir', () => {
        const kids2h = { duracion: 120, complementos: [] };
        const conHora = (otra, principal = kids2h, extra = {}) => pantalla({ hora: '11:00:00', otra }, { principal, fichaOtra: ficha104, excluir: [110], conHoraOtra: conHora140, ...extra });

        const una = conHora({ fila: 103, n: 1 });
        assert.deepEqual([plano(una.resumen), una.finPrincipal], ['Jump · 1 hora · 11:00–12:00 · 1 entrada', '13:00'], 'Kids 2 h y Jump 1 h');
        assert.match(una.resumen, /11:00⁠–⁠12:00/, 'el tramo no se parte al final de una línea (uniones de palabra)');

        const iguales = conHora({ fila: 104, n: 2, extras: [] });
        assert.deepEqual([iguales.resumen, iguales.finPrincipal], ['Jump · 2 horas · 2 entradas', null], 'las dos de 2 horas: salen juntos');

        const suExtra = conHora({ fila: 104, n: 1, extras: [{ product_id: 140, quantity: 1 }] });
        assert.deepEqual([plano(suExtra.resumen), suExtra.finPrincipal], ['Jump · 2 horas · 11:00–14:00 · 1 entrada', '13:00'], 'su hora extra la alarga');

        const laDelPedido = conHora({ fila: 104, n: 1, extras: [] }, { duracion: 120, complementos: [{ n: 1, minutos: 60, porUnidad: false }] });
        assert.deepEqual([plano(laDelPedido.resumen), laDelPedido.finPrincipal], ['Jump · 2 horas · 11:00–13:00 · 1 entrada', '14:00'], 'y la del pedido, la suya');

        const ilimitada = conHora({ fila: 104, n: 1, extras: [] }, { duracion: null, complementos: [] });
        assert.deepEqual([plano(ilimitada.resumen), ilimitada.finPrincipal], ['Jump · 2 horas · 11:00–13:00 · 1 entrada', null], 'una ilimitada no tiene hora de salida');

        const sinHora = pantalla({ otra: { fila: 103, n: 1 } }, { principal: kids2h });
        assert.deepEqual([sinHora.resumen, sinHora.finPrincipal], ['Jump · 1 hora · 1 entrada', null], 'sin hora, nada que decir');
    });

    test('la hora extra que NO cabe a esa hora no alarga nada (solo cuenta lo elegido Y disponible)', () => {
        const v = pantalla({ hora: '11:00:00', otra: { fila: 104, n: 1, extras: [{ product_id: 140, quantity: 1 }] } },
            { principal: { duracion: 120, complementos: [] }, fichaOtra: ficha104, excluir: [110], conHoraOtra: [] });

        assert.deepEqual([v.resumen, v.finPrincipal], ['Jump · 2 horas · 1 entrada', null]);
    });

    test('las horas en las que la otra no cabe, apagadas con su porqué', () => {
        const v = pantalla({ otra: { fila: 103, n: 3 } }, { horasOtra: [{ time: '17:00:00', available: 2, sellable: true }] });

        assert.equal(v.noCabe('17:00'), true, 'quedan 2 y van 3');
        assert.equal(v.notaHora, 'En JUMP no caben a esta hora');
    });
});
