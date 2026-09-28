import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { DIAS_TIRA, calendarioDeTira, diaCorto, diaLargo, euros, horasCercanas, horasDelSelector, tiraDias } from './vista.js';
import { datosCompletos, datosDeReserva, filasDeZona, pantallaCuando, respuestasDe, zonasConEntradas } from './pantalla-cuando.js';

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
            persona: 'persona', personas: 'personas', desde_precio: 'desde :precio', pregunta_datos: 'Datos de la reserva',
        },
        pagar: { hoy_pagas: 'Hoy pagas :importe' },
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

    // `#830`: la tira se quedaba en SIETE días y el motor vende cinco meses.
    const ofrecidos = (n, desde = '2026-09-28') => Array.from({ length: n }, (_, i) => {
        const d = new Date(`${desde}T12:00:00Z`);

        d.setUTCDate(d.getUTCDate() + i);

        return { date: d.toISOString().slice(0, 10), price_cents: 800, rate_key: i % 7 === 4 ? 'special' : 'normal' };
    });

    test('la tira enseña dos semanas; el día elegido más allá entra al final, y no se repite si ya está', () => {
        const dias = ofrecidos(155);

        assert.equal(DIAS_TIRA, 14);
        assert.equal(tiraDias(dias, { hoy: '2026-09-28', locale: 'es', textos }).length, 14);
        const conLejano = tiraDias(dias, { hoy: '2026-09-28', locale: 'es', textos, elegido: '2026-11-20' });

        assert.equal(conLejano.length, 15);
        assert.equal(conLejano.at(-1).id, '2026-11-20');
        // Con su mes encima: tras «dom 11» de octubre, «vie 20» se leía como del mismo mes.
        assert.equal(conLejano.at(-1).label, 'nov');
        assert.equal(conLejano.at(-2).label, 'dom');
        assert.equal(tiraDias(dias, { hoy: '2026-09-28', locale: 'es', textos, elegido: '2026-10-01' }).length, 14);
        // Un día que el motor no vende no se inventa en la tira.
        assert.equal(tiraDias(dias, { hoy: '2026-09-28', locale: 'es', textos, elegido: '2027-06-01' }).length, 14);
    });

    test('«Más fechas»: solo si hay más días que la tira; abre en el mes del primero que ella deja fuera, o del elegido', () => {
        assert.equal(calendarioDeTira(ofrecidos(14), {}), null);
        const cal = calendarioDeTira(ofrecidos(155), { hoy: '2026-09-28', locale: 'es' });

        // La tira va del 28-09 al 11-10: el primero que deja fuera es el 12-10.
        assert.equal(cal.month, '2026-10');
        assert.equal(cal.minMonth, '2026-09');
        assert.equal(cal.maxMonth, '2027-03');
        assert.equal(cal.days.length, 155);
        assert.deepEqual(cal.days[4], { date: '2026-10-02', special: true });
        assert.equal(cal.today, '2026-09-28');
        assert.equal(calendarioDeTira(ofrecidos(155), { dia: '2026-12-24' }).month, '2026-12');
        // Un elegido DENTRO de la tira no lo mueve: abre donde la tira acaba.
        assert.equal(calendarioDeTira(ofrecidos(155), { dia: '2026-09-30' }).month, '2026-10');
    });

    test('las dos pantallas de «Cuándo» llevan el calendario de «Más fechas» cuando hay más días', () => {
        const largo = { ...precios, 100: ofrecidos(40, '2026-09-24') };
        const { props } = pantallaCuando(estado({ precios: largo }));

        assert.equal(props.dias.length, 14);
        assert.equal(props.calendario.month, '2026-10');
        assert.equal(pantallaCuando(estado()).props.calendario, null);
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

/**
 * **Un PACK SIN EDAD por la pantalla de las entradas** (T6c·3 de §4.19: las excursiones). Con la forma real medida en
 * local el 28-09: los packs 395 y 396, sus días (el precio del tramo MÁS BARATO: 12 € entre semana) y su ficha con lo que
 * pide al reservar (`#839`: el centro, el curso opcional, el responsable y su teléfono).
 */
describe('la pantalla 0 de un pack sin edad (una excursión)', () => {
    const excursiones = { id: 241, slug: 'excursiones', name: 'Excursiones' };
    const catalogo = [
        ...productos,
        { id: 395, type: 'pack', name: 'Excursión escolar · 2 horas', zone: excursiones, duration_min: 120 },
        { id: 396, type: 'pack', name: 'Excursión escolar · 3 horas', zone: excursiones, duration_min: 180 },
    ];
    const campo = (key, label, required, type = 'text') => ({ key, label, type, required, min: null, max: null });
    const ficha = {
        id: 395, type: 'pack', min_quantity: 30, max_quantity: 100, guardian_authorization: 'required',
        event_fields: [campo('school', 'Nombre del centro', true), campo('course', 'Curso o edades', false), campo('lead', 'Persona responsable el día de la visita', true), campo('lead_phone', 'Teléfono de contacto ese día', true)],
    };
    const dias = { 395: [{ date: '2026-10-01', price_cents: 1200, rate_key: 'normal' }], 396: [{ date: '2026-10-01', price_cents: 1500, rate_key: 'normal' }] };
    const deExcursion = (cambios = {}) => estado({
        productos: catalogo, precios: dias, ficha, calcetin: null, minimo: 30,
        borrador: borrador({ zona: 'excursiones', fila: 395, dia: '2026-10-01', n: 60, evento: {} }), ...cambios,
    });
    const contestado = { school: 'CEIP San José', lead: 'Marta Ruiz', lead_phone: '600 000 000' };

    test('las filas de una zona sin entradas son sus packs; con entradas, solo ellas', () => {
        assert.deepEqual(filasDeZona(catalogo, 'excursiones').map((p) => p.id), [395, 396]);
        assert.deepEqual(filasDeZona(catalogo, 'kids').map((p) => p.id), [100, 101, 102]);
    });

    test('el título es la zona, se cuentan personas con el tope de la ficha y cada pack dice «desde» su tramo más barato', () => {
        const { props } = pantallaCuando(deExcursion());

        assert.equal(props.titulo, 'Excursiones', 'no «Entrada Excursiones»: la zona no vende entradas');
        assert.deepEqual(props.cuantos, { n: 60, uno: 'persona', varios: 'personas', min: 30, max: 100 });
        assert.deepEqual(props.filas.map((f) => f.price), ['desde 12 €', 'desde 15 €']);
        assert.equal(props.preguntas.datos.titulo, 'Datos de la reserva');
        assert.deepEqual(props.preguntas.datos.campos.map((d) => [d.key, d.label, d.required, d.valor]), [
            ['school', 'Nombre del centro', true, ''], ['course', 'Curso o edades', false, ''],
            ['lead', 'Persona responsable el día de la visita', true, ''], ['lead_phone', 'Teléfono de contacto ese día', true, ''],
        ]);
    });

    test('con hora y su línea: el precio de ESA cantidad (el del servidor), el total y lo que se paga hoy', () => {
        const linea = { unit_price_cents: 1300, total_cents: 78000, has_deposit: true, deposit_cents: 10000 };
        const { props, ck } = pantallaCuando(deExcursion({ borrador: borrador({ zona: 'excursiones', fila: 395, dia: '2026-10-01', hora: '10:00:00', n: 60, evento: contestado }), linea }));

        assert.deepEqual(props.filas.map((f) => f.price), ['13 €', 'desde 15 €']);
        assert.equal(ck.summary, 'Excursión escolar · 2 horas · jue 1, 10:00 · 60 personas');
        assert.deepEqual([ck.total, ck.today], ['780 €', 'Hoy pagas 100 €']);
    });

    test('no se puede continuar sin lo que el pack exige al reservar; el curso es opcional', () => {
        const conHora = (evento) => pantallaCuando(deExcursion({ borrador: borrador({ zona: 'excursiones', fila: 395, dia: '2026-10-01', hora: '10:00:00', n: 60, evento }), linea: { total_cents: 78000 } }));

        assert.equal(conHora({}).listo, false);
        assert.equal(conHora({ ...contestado, lead: '   ' }).listo, false, 'solo espacios no es contestar: el servidor los quita');
        assert.equal(conHora(contestado).listo, true);
        assert.equal(conHora(contestado).ck.action.disabled, false);
    });

    /** El owner, 28-09: «siempre mover el scroll hasta donde es necesario X campo o X acción para continuar». */
    test('lo PRIMERO que falta, por su id: el día, la hora y, con todo elegido, el primer campo obligatorio sin contestar', () => {
        const con = (cambios) => pantallaCuando(deExcursion({ borrador: borrador({ zona: 'excursiones', fila: 395, dia: '2026-10-01', hora: '10:00:00', n: 60, evento: {}, ...cambios }), linea: { total_cents: 78000 } }));

        assert.equal(con({ dia: null, hora: null }).falta, 'pjc-q-dia');
        assert.equal(con({ hora: null }).falta, 'pjc-q-hora');
        assert.equal(con({}).falta, 'pjc-dato-school');
        assert.equal(con({ evento: { school: 'CEIP', course: '' } }).falta, 'pjc-dato-lead', 'el curso es opcional: se salta');
        assert.equal(con({ evento: contestado }).falta, null, 'lista: nada que buscar');
        assert.equal(pantallaCuando(estado({ borrador: borrador({ zona: null, elegirZona: true, fila: null }) })).falta, 'pjc-q-zona');
    });

    test('lo que viaja en la línea: los campos del pack contestados, sin espacios y nada más', () => {
        const datos = datosDeReserva(ficha, { ...contestado, course: '  ', otra: 'x', school: ' CEIP San José ' });

        assert.equal(datosCompletos(datos), true);
        assert.deepEqual(respuestasDe(datos), { school: 'CEIP San José', lead: 'Marta Ruiz', lead_phone: '600 000 000' });
    });

    test('una entrada no pide nada; de una fiesta, la edad de quien cumple no (la pregunta su pantalla); un número se teclea como tal', () => {
        assert.deepEqual(datosDeReserva({ type: 'entry', event_fields: ficha.event_fields }), []);
        const fiesta = { type: 'pack', event_fields: [campo('age', '¿Cuántos años cumple?', true, 'celebrant_age'), campo('adults', '¿Cuántos adultos?', false, 'adults')] };

        assert.deepEqual(datosDeReserva(fiesta).map((d) => [d.key, d.numero]), [['adults', true]]);
    });
});
