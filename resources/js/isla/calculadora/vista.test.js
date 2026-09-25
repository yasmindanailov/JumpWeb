import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cierreDelDia, vistaCalculadora } from './vista.js';

const NBSP = String.fromCharCode(0xa0);
const textos = {
    calculadora: {
        falta_dia: 'Elige el día', falta_hora: 'Elige la hora', espera_hora: 'Antes el día', por: ':precio por :persona', desde: 'desde :precio',
        eco_dia_antes: ':tarifa: ', eco_dia_despues: ' por :persona', eco_hora: 'De :desde a :hasta', eco_hora_cierre: 'De :desde al cierre, a las :cierre',
        a_las: ':dia a las :hora', linea_entradas: 'Entradas · :n × :precio', linea_complemento: ':nombre · :n × :precio',
    },
    pieza: { quedan: 'Quedan :n' },
};
const pagina = {
    zona: 'kids', zonaNombre: 'Kids', nombre: 'Parque', url: 'https://parque.test/kids',
    filas: [{ id: 101, label: '1 hora', horas: 1, desde_cents: 800 }, { id: 102, label: 'Ilimitada', horas: null, descripcion: 'Toda la tarde.', aviso: 'Solo entre semana.', desde_cents: 1800 }],
    columnas: ['De lunes a jueves', 'Tarifa especial'],
    textos: {
        preguntas: ['¿Cuántos?', '¿Cuánto?', '¿Qué día?', '¿Qué hora?', '¿Calcetines?'], persona: ['niño', 'niños'], cuantos: { label: 'Niños', sub: '', nota: 'n' },
        calcetines: { sub: 's', nota: 'n' }, tiempo: 't', boton: 'Reservar', junto: 'j', nota: 'q', compartir: 'WhatsApp',
        calculadora: { calcetines: 'Pares', sin_calcetines: 'Los vuestros', cada_uno: 'Uno por :persona · :n', calcetines_uno: 'un par', calcetines_varios: 'pares para :n', numeros: ['', 'uno', 'dos'], mensaje: ':nombre, zona :zona: ' },
    },
};
const precios = {
    101: [{ date: '2026-09-24', price_cents: 800, rate_key: 'normal' }, { date: '2026-09-26', price_cents: 1000, rate_key: 'special' }],
    102: [{ date: '2026-09-24', price_cents: 1800, rate_key: 'normal' }],
};
const calcetin = { id: 110, price_cents: 200, max_quantity: 40 };
const vista = (cambios = {}) => vistaCalculadora({
    pagina, textos, locale: 'es', hoy: '2026-09-23', precios, calcetin, horas: [], linea: null, cargoCalcetines: null, cierre: '21:30',
    ...cambios, borrador: { fila: 101, dia: null, hora: null, n: 2, cal: 0, ...(cambios.borrador ?? {}) },
});

test('sin día: el «desde» de cada fila, lo que falta, ni total ni botón', () => {
    const v = vista();
    assert.deepEqual(v.tiempo.items.map((i) => i.price), [`desde 8${NBSP}€`, `desde 18${NBSP}€`]);
    assert.deepEqual([v.resumen.falta, v.resumen.total, v.resumen.listo, v.hora.horas], ['Elige el día', '', false, null]);
});

test('el dinero es el del SERVIDOR: el precio del día, la línea y el cargo de los calcetines, nunca una cuenta', () => {
    // Una línea que NO es «n × precio» y un cargo que no es «pares × precio»: si la vista multiplicara, se vería.
    const linea = { unit_price_cents: 1000, subtotal_cents: 1990, total_cents: 2490, addons: [{ product_id: 110, product_name: 'Calcetines', quantity: 2, subtotal_cents: 500 }] };
    const v = vista({ borrador: { dia: '2026-09-26', hora: '17:00:00', cal: 2 }, horas: [{ time: '17:00:00', available: 20 }], linea, cargoCalcetines: 450 });

    assert.equal(v.cuantos.precio, `10${NBSP}€ por niño`);
    assert.deepEqual(v.resumen.lineas.map((l) => [l.label, l.value]), [[`Entradas · 2 × 10${NBSP}€`, `19,90${NBSP}€`], [`Calcetines · 2 × 2${NBSP}€`, `5${NBSP}€`]]);
    assert.deepEqual([v.resumen.total, v.resumen.listo, v.calcetines.precio], [`24,90${NBSP}€`, true, `4,50${NBSP}€`]);
    // Con otra petición en camino, el total que se ve sigue ahí pero el botón espera a la última respuesta.
    const enCamino = vista({ borrador: { dia: '2026-09-26', hora: '17:00:00', cal: 2 }, horas: [{ time: '17:00:00', available: 20 }], linea, pendiente: true });
    assert.deepEqual([enCamino.resumen.total, enCamino.resumen.listo], [`24,90${NBSP}€`, false]);
});

test('con hora pero sin la línea del servidor todavía: sin total y con el botón apagado', () => {
    const v = vista({ borrador: { dia: '2026-09-26', hora: '17:00:00' }, horas: [{ time: '17:00:00', available: 20 }] });
    assert.deepEqual([v.resumen.falta, v.resumen.lineas, v.resumen.total, v.resumen.listo], ['', [], '', false]);
});

test('una hora que ya no cabe para el grupo sale apagada con sus plazas, y no cuenta como elegida', () => {
    const v = vista({ borrador: { dia: '2026-09-26', hora: '17:00:00', n: 5 }, horas: [{ time: '17:00:00', available: 3 }, { time: '17:30:00', available: 0 }], linea: { unit_price_cents: 1, subtotal_cents: 1, total_cents: 1, addons: [] } });
    assert.deepEqual(v.hora.horas, [{ time: '17:00', left: 3, disabled: true, note: 'Quedan 3' }, { time: '17:30', left: 0, disabled: true }]);
    assert.deepEqual([v.hora.valor, v.resumen.falta, v.resumen.listo], [null, 'Elige la hora', false]);
});

test('el día especial dice su tarifa; la fila sin hora fija acaba al cierre de ese día', () => {
    const v = vista({ borrador: { fila: 102, dia: '2026-09-24', hora: '18:30:00' }, horas: [{ time: '18:30:00', available: 9 }], cierre: '21:00' });
    assert.deepEqual([v.dia.nota, v.hora.eco, v.dia.eco.antes], ['Solo entre semana.', 'De 18:30 al cierre, a las 21:00', 'De lunes a jueves: ']);
    assert.equal(vista({ borrador: { dia: '2026-09-26' } }).dia.eco.antes, 'Tarifa especial: ');
    assert.deepEqual(vista({ borrador: { dia: '2026-09-26' } }).dia.dias, [{ date: '2026-09-24', special: false }, { date: '2026-09-26', special: true }]);
});

test('sin complemento por cantidad, la pregunta de los calcetines no sale', () => {
    assert.equal(vista({ calcetin: null }).calcetines, null);
});

test('el resumen y el mensaje de WhatsApp con las palabras de la página', () => {
    const v = vista({ borrador: { dia: '2026-09-26', cal: 2 } });
    assert.equal(v.resumen.seleccion, '2 niños · 1 hora · sábado 26 · pares para dos');
    assert.equal(decodeURIComponent(v.compartir.items[0].href.split('text=')[1]), 'Parque, zona Kids: 2 niños · 1 hora · sábado 26 · pares para dos · https://parque.test/kids');
    assert.equal(v.calcetines.cadaUno.texto, 'Uno por niño · 2');
});

test('la hora de cierre de un día: la de su día especial, si no la de su día de la semana; sin abrir, ninguna', () => {
    const cierres = { semana: { 1: '21:30', 6: '21:30' }, dias: { '2026-10-12': '20:00' } };
    assert.deepEqual([cierreDelDia(cierres, '2026-09-26'), cierreDelDia(cierres, '2026-10-12'), cierreDelDia(cierres, '2026-09-27'), cierreDelDia(cierres, null)], ['21:30', '20:00', null, null]);
});
