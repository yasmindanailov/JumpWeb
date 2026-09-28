import { test } from 'node:test';
import assert from 'node:assert/strict';
import { DIAS_PARA_UN_MES, cierreDelDia, codificarCalculo, leerCalculo, mesesDelCalendario, precioDelTramo, tramoCerca, vistaCalculadora } from './vista.js';

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

test('lo que se le cuenta a la ISLA (T4e): nada sin tocar; lo que falta; y lo elegido en corto con el total del servidor', () => {
    assert.equal(vista().isla, null, 'Los valores de partida no son un cálculo: la isla no anuncia nada.');
    assert.deepEqual(vista({ tocada: true }).isla, { falta: 'dia', elegido: null, boton: 'Reservar' });
    assert.equal(vista({ tocada: true, borrador: { dia: '2026-09-26' } }).isla.falta, 'hora');
    const linea = { unit_price_cents: 1000, subtotal_cents: 1990, total_cents: 2490, addons: [] };
    const listo = vista({ tocada: true, borrador: { dia: '2026-09-26', hora: '17:00:00' }, horas: [{ time: '17:00:00', available: 20 }], linea });
    assert.deepEqual(listo.isla, { falta: '', elegido: `Sáb 26 · 17:00 · 2 niños · 24,90${NBSP}€`, boton: 'Reservar' }, 'El día con mayúscula, como `p3Corto` del diseño; el total, el del servidor.');
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

// `#830`: a fin de mes el calendario abría en un mes con tres días elegibles y el siguiente quedaba tras la flecha.
const vendidos = (desde, n) => Array.from({ length: n }, (_, i) => {
    const d = new Date(`${desde}T12:00:00Z`);

    d.setUTCDate(d.getUTCDate() + i);

    return { date: d.toISOString().slice(0, 10), price_cents: 800, rate_key: 'normal' };
});

test('el calendario abre con DOS meses cuando al de hoy le quedan menos de siete días a la venta', () => {
    assert.equal(DIAS_PARA_UN_MES, 7);
    // Hoy 27-09, se vende del 28: quedan 3 en septiembre.
    assert.deepEqual(mesesDelCalendario(vendidos('2026-09-28', 155), { hoy: '2026-09-27' }), { mes: '2026-09', meses: 2 });
    // Hoy 24-09, se vende desde hoy: quedan 7 → uno.
    assert.deepEqual(mesesDelCalendario(vendidos('2026-09-24', 155), { hoy: '2026-09-24' }), { mes: '2026-09', meses: 1 });
    // Hoy 25-09: quedan 6 → dos.
    assert.equal(mesesDelCalendario(vendidos('2026-09-25', 155), { hoy: '2026-09-25' }).meses, 2);
    // Sin nada a la venta el mes que viene, uno solo (no se pinta un mes vacío).
    assert.deepEqual(mesesDelCalendario(vendidos('2026-09-28', 3), { hoy: '2026-09-27' }), { mes: '2026-09', meses: 1 });
});

test('con dos meses, un día elegido en el de abajo deja la pareja quieta; uno más lejos pide su mes', () => {
    const dias = vendidos('2026-09-28', 155);

    assert.deepEqual(mesesDelCalendario(dias, { hoy: '2026-09-27', dia: '2026-10-15' }), { mes: '2026-09', meses: 2 });
    assert.deepEqual(mesesDelCalendario(dias, { hoy: '2026-09-27', dia: '2026-09-29' }), { mes: '2026-09', meses: 2 });
    assert.deepEqual(mesesDelCalendario(dias, { hoy: '2026-09-27', dia: '2026-12-05' }), { mes: '2026-12', meses: 2 });
    // En mitad de mes, el del día elegido y uno solo, como siempre.
    assert.deepEqual(mesesDelCalendario(vendidos('2026-09-10', 155), { hoy: '2026-09-10', dia: '2026-11-02' }), { mes: '2026-11', meses: 1 });
});

test('la hora de cierre de un día: la de su día especial, si no la de su día de la semana; sin abrir, ninguna', () => {
    const cierres = { semana: { 1: '21:30', 6: '21:30' }, dias: { '2026-10-12': '20:00' } };
    assert.deepEqual([cierreDelDia(cierres, '2026-09-26'), cierreDelDia(cierres, '2026-10-12'), cierreDelDia(cierres, '2026-09-27'), cierreDelDia(cierres, null)], ['21:30', '20:00', null, null]);
});

/**
 * **La calculadora de COLEGIOS: la de entradas con los dos packs de excursión por filas** (T6c·3b, §4.19). El precio por
 * alumno depende de CUÁNTOS (su tramo): se LEE de la escalera que la página trae de `/prices.tiers` —la de local, 15/17 ·
 * 13/15 · 12/14 € desde 30, 70 y 100, y el pack de 3 horas, 3 € más—; el del día de la API es el del tramo más barato y
 * aquí no vale. El total, la señal y el resto, de la línea del servidor.
 */
const tramos = (mas = 0) => [{ desde: 30, normal: 1500 + mas, especial: 1700 + mas }, { desde: 70, normal: 1300 + mas, especial: 1500 + mas }, { desde: 100, normal: 1200 + mas, especial: 1400 + mas }];
const colegios = {
    zona: 'excursiones', zonaNombre: 'Excursiones', nombre: 'Parque', url: 'https://parque.test/colegios', ancla: 'calcula',
    filas: [
        { id: 395, tipo: 'pack', label: '2 horas', horas: 2, desde_cents: 1200, min: 30, max: 100, tramos: tramos() },
        { id: 396, tipo: 'pack', label: '3 horas', horas: 3, desde_cents: 1500, min: 30, max: 100, tramos: tramos(300) },
    ],
    columnas: ['De lunes a jueves', 'Tarifa especial'],
    textos: {
        preguntas: ['¿Cuántos alumnos?', '¿Cuánto tiempo?', '¿Qué día?', '¿A qué hora?'], persona: ['alumno', 'alumnos'],
        cuantos: { label: 'Alumnos', sub: 'De 30 a 100', nota: 'n', editable: true }, tiempo: 't', boton: 'Reservar y pagar la señal', junto: 'j',
        nota: 'Un profesor gratis por cada 15 alumnos', notaIcono: 'user-round', ahora: 'Hoy pagas la señal', luego: 'El día de la visita', senal: '100 €',
        compartir: 'WhatsApp',
        calculadora: { mensaje: ':nombre: ', tramo_cerca: 'Desde :n alumnos, :precio por alumno.', tramo_calcular: 'Calcular con :n' },
    },
};
const conPack = { ...textos, calculadora: { ...textos.calculadora, linea_pack: ':pack · :n × :precio' } };
const diasPack = {
    395: [{ date: '2026-10-05', price_cents: 1200, rate_key: 'normal' }, { date: '2026-10-09', price_cents: 1400, rate_key: 'special' }],
    396: [{ date: '2026-10-05', price_cents: 1500, rate_key: 'normal' }, { date: '2026-10-09', price_cents: 1700, rate_key: 'special' }],
};
const deColegios = (cambios = {}) => vistaCalculadora({
    pagina: colegios, textos: conPack, locale: 'es', hoy: '2026-09-28', precios: diasPack, calcetin: null, horas: [], linea: null, cargoCalcetines: null, cierre: '21:30',
    ...cambios, borrador: { fila: 395, dia: null, hora: null, n: 60, cal: 0, ...(cambios.borrador ?? {}) },
});
const eur = (n) => `${n}${NBSP}€`;

test('la escalera: el precio del TRAMO de esa gente, en la columna de la tarifa; bajo el primero, ninguno', () => {
    const f = colegios.filas[0];

    assert.deepEqual([precioDelTramo(f, 30), precioDelTramo(f, 69), precioDelTramo(f, 70, true), precioDelTramo(f, 100), precioDelTramo(f, 29)], [1500, 1500, 1500, 1200, null]);
    assert.deepEqual([tramoCerca(f, 60), tramoCerca(f, 59), tramoCerca(f, 95, true), tramoCerca(f, 100)], [{ n: 70, cents: 1300 }, null, { n: 100, cents: 1400 }, null]);
});

test('sin día: «desde» el tramo de 60 en la tarifa normal, en el contador y en cada duración; y el tramo siguiente, a un toque', () => {
    const v = deColegios();

    assert.equal(v.cuantos.precio, `desde ${eur(15)} por alumno`, 'no los 12 € del día: esos son de 100 alumnos');
    assert.deepEqual(v.tiempo.items.map((i) => i.price), [`desde ${eur(15)}`, `desde ${eur(18)}`]);
    assert.deepEqual([v.cuantos.min, v.cuantos.max, v.cuantos.editable], [30, 100, true], 'el mínimo y el máximo, de la fila, antes de la ficha');
    assert.deepEqual(v.cuantos.cerca, { texto: `Desde 70 alumnos, ${eur(13)} por alumno.`, accion: 'Calcular con 70', n: 70 });
    assert.deepEqual(v.resumen.ahora, { label: 'Hoy pagas la señal', value: '100 €' }, 'la señal de la ficha, antes de la línea');
    assert.equal(v.resumen.notaIcono, 'user-round');
});

test('un día de tarifa especial: el tramo en SU columna, y el eco lo dice', () => {
    const v = deColegios({ borrador: { dia: '2026-10-09' } });

    assert.equal(v.cuantos.precio, `${eur(17)} por alumno`);
    assert.deepEqual(v.tiempo.items.map((i) => i.price), [eur(17), eur(20)]);
    assert.deepEqual([v.dia.eco.antes, v.dia.eco.cifra], ['Tarifa especial: ', eur(17)]);
});

test('con hora y la línea del servidor: su línea, su total, lo que se paga hoy y lo que queda para el día', () => {
    const linea = { unit_price_cents: 1500, subtotal_cents: 90000, total_cents: 90000, has_deposit: true, deposit_cents: 10000, gate_remainder_cents: 80000, addons: [] };
    const v = deColegios({ borrador: { dia: '2026-10-05', hora: '10:00:00' }, horas: [{ time: '10:00:00', available: 100, sellable: true }], linea });

    assert.deepEqual(v.resumen.lineas.map((l) => [l.label, l.value]), [[`2 horas · 60 × ${eur(15)}`, eur(900)]]);
    assert.deepEqual([v.resumen.total, v.resumen.listo], [eur(900), true]);
    assert.deepEqual([v.resumen.ahora, v.resumen.luego], [{ label: 'Hoy pagas la señal', value: eur(100) }, { label: 'El día de la visita', value: eur(800) }]);
    assert.equal(v.cuantos.cerca.n, 70);
});

/**
 * **El cálculo que se RETOMA** (T6c·4a, `#837`): viaja en el enlace (`?c=`) y se guarda en el dispositivo; al leerlo, lo que
 * no vale se suelta —una fila ajena, una cantidad fuera de sus topes, un día pasado (y con él la hora)—.
 */
test('el cálculo en una cadena, y de vuelta: lo que no vale se suelta', () => {
    const conPagina = { ...colegios, recordar: 'pj-colegios-calculo' };

    assert.equal(codificarCalculo({ n: 60, fila: 395, dia: '2026-10-20', hora: '10:00:00' }), '60_395_2026-10-20_10:00');
    assert.equal(codificarCalculo({ n: 60, fila: 395, dia: null, hora: null }), '60_395__');
    assert.deepEqual(leerCalculo('60_395_2026-10-20_10:00', conPagina, '2026-09-28'), { fila: 395, n: 60, dia: '2026-10-20', hora: '10:00:00' });
    assert.deepEqual(leerCalculo('75_396__', conPagina, '2026-09-28'), { fila: 396, n: 75, dia: null, hora: null });
    assert.deepEqual(leerCalculo('60_395_2026-09-01_10:00', conPagina, '2026-09-28'), { fila: 395, n: 60, dia: null, hora: null }, 'un día pasado se suelta, y su hora');
    for (const malo of ['60_101_2026-10-20_10:00', '20_395__', '101_395__', 'x_395__', '', null]) {
        assert.equal(leerCalculo(malo, conPagina, '2026-09-28'), null, String(malo));
    }
});

test('retomado: el enlace lo lleva dentro, la isla lo dice como suyo y, si su hora se ocupó, se avisa hasta elegir otra', () => {
    const pagina = { ...colegios, recordar: 'pj-colegios-calculo', textos: { ...colegios.textos, tuya: 'Tu excursión: ', hora_perdida: 'Esa hora ya no está libre. Mira las que quedan.' } };
    const linea = { unit_price_cents: 1500, subtotal_cents: 90000, total_cents: 90000, has_deposit: true, deposit_cents: 10000, gate_remainder_cents: 80000, addons: [] };
    const v = vistaCalculadora({
        pagina, textos: conPack, locale: 'es', hoy: '2026-09-28', precios: diasPack, calcetin: null, horas: [{ time: '10:00:00', available: 100, sellable: true }], linea,
        cargoCalcetines: null, cierre: '21:30', tocada: true, vuelta: 'enlace', borrador: { fila: 395, dia: '2026-10-05', hora: '10:00:00', n: 60, cal: 0 },
    });

    assert.equal(v.compartir.value, 'https://parque.test/colegios?c=60_395_2026-10-05_10%3A00#calcula');
    assert.ok(decodeURIComponent(v.compartir.items[0].href).endsWith('https://parque.test/colegios?c=60_395_2026-10-05_10%3A00#calcula'), 'y el mensaje de WhatsApp');
    assert.equal(v.isla.elegido, `Tu excursión: lun 5 · 10:00 · 60 alumnos · ${eur(900)}`);
    const perdida = vistaCalculadora({
        pagina, textos: conPack, locale: 'es', hoy: '2026-09-28', precios: diasPack, calcetin: null, horas: [], linea: null, cargoCalcetines: null, cierre: '21:30',
        tocada: true, vuelta: 'guardado', perdida: true, borrador: { fila: 395, dia: '2026-10-05', hora: null, n: 60, cal: 0 },
    });
    assert.equal(perdida.hora.perdida, 'Esa hora ya no está libre. Mira las que quedan.');
    assert.equal(deColegios().compartir.value, 'https://parque.test/colegios#calcula', 'sin tocar, el enlace va limpio');
});

test('con la HOJA de la página (T6c·4b), su descarga: con el cálculo solo si hay línea del servidor; sin hoja, nada', () => {
    const pagina = { ...colegios, recordar: 'pj-colegios-calculo', hoja: 'https://parque.test/colegios-propuesta', textos: { ...colegios.textos, descargar: 'Descargar la propuesta', descargar_calculo: 'Descargar la propuesta con este cálculo' } };
    const linea = { unit_price_cents: 1500, subtotal_cents: 90000, total_cents: 90000, addons: [] };
    const con = (cambios) => vistaCalculadora({ pagina, textos: conPack, locale: 'es', hoy: '2026-09-28', precios: diasPack, calcetin: null, horas: [{ time: '10:00:00', available: 100, sellable: true }], cargoCalcetines: null, cierre: '21:30', tocada: true, linea: null, ...cambios, borrador: { fila: 395, dia: '2026-10-05', hora: '10:00:00', n: 60, cal: 0 } });

    assert.deepEqual(con({ linea }).compartir.items[1], { kind: 'link', label: 'Descargar la propuesta con este cálculo', href: 'https://parque.test/colegios-propuesta?c=60_395_2026-10-05_10%3A00&imprimir=1' });
    assert.deepEqual(con({}).compartir.items[1], { kind: 'link', label: 'Descargar la propuesta', href: 'https://parque.test/colegios-propuesta?imprimir=1' });
    assert.equal(deColegios().compartir.items.length, 1, 'sin hoja declarada, solo WhatsApp');
});

test('las entradas no cambian: sin señal, sin tramos, sin contador escribible y con la nota del QR', () => {
    const v = vista();

    assert.deepEqual([v.resumen.ahora, v.resumen.luego, v.cuantos.editable, v.cuantos.cerca, v.resumen.notaIcono], [null, null, false, null, 'qr-code']);
});

/** **La escalera marcada** (T6c·6, `active` del diseño): la de la fila elegida, el tramo de esa gente y, con día, su tarifa. */
test('la escalera que marca: su fila, el tramo de esa gente y, solo con día, su tarifa; de unas entradas, ninguna', () => {
    assert.deepEqual(deColegios().escalera, { fila: 395, tramo: 30, tarifa: null }, 'sin día: el tramo, sin celda');
    assert.deepEqual(deColegios({ borrador: { n: 75 } }).escalera, { fila: 395, tramo: 70, tarifa: null });
    assert.deepEqual(deColegios({ borrador: { n: 100, dia: '2026-10-05' } }).escalera, { fila: 395, tramo: 100, tarifa: 'normal' });
    assert.deepEqual(deColegios({ borrador: { fila: 396, dia: '2026-10-09' } }).escalera, { fila: 396, tramo: 30, tarifa: 'special' });
    assert.equal(vista().escalera, null);
});
