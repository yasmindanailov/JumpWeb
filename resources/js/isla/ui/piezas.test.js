import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createTextVNode, h } from 'vue';
import { acotar, barrasEsqueleto, columnasOpciones, enNavegadorDeApp, estadoHora, idDeCampo, idDeCasilla, textoDeRanura } from './piezas.js';
import { celdasDelMes, estadoDia, mesDesplazado, vistaCalendario } from './calendario.js';
import { atributosCompartir } from './compartir.js';

const tp = (clave, p = {}) => `${clave}${p.n != null ? `:${p.n}` : ''}`;

test('una hora: la nota manda, luego completa, luego pocas; con counts «low» la cifra grande calla', () => {
    assert.equal(estadoHora({ time: '17:00', left: 0 }, {}, tp).texto, 'pieza.completo');
    assert.equal(estadoHora({ time: '17:00', left: 9, disabled: true }, {}, tp).agotada, true);
    assert.equal(estadoHora({ time: '17:00', left: 5 }, { lowThreshold: 6 }, tp).texto, 'pieza.quedan:5');
    assert.equal(estadoHora({ time: '17:00', left: 31 }, { counts: 'low' }, tp).texto, '');
    assert.equal(estadoHora({ time: '17:00', left: 31 }, {}, tp).texto, 'pieza.libres:31');
    assert.equal(estadoHora({ time: '17:00', left: 5, note: 'Quedan 5' }, { lowThreshold: 1 }, tp).texto, 'Quedan 5');
    assert.equal(estadoHora({ time: '17:00', left: 3 }, { value: '17:00' }, tp).activa, true);
});

test('una hora sin cifra de plazas no es «pocas» ni «completa»', () => {
    const e = estadoHora({ time: '5' }, { counts: 'low' }, tp);
    assert.deepEqual([e.agotada, e.pocas, e.texto], [false, false, '']);
});

test('la cantidad no pasa de sus topes', () => {
    assert.equal(acotar(-1, 0, 20), 0);
    assert.equal(acotar(21, 0, 20), 20);
    assert.equal(acotar(7, 8, 40), 8);
});

test('los identificadores que el diseño deriva de la etiqueta', () => {
    assert.equal(idDeCampo(undefined, 'Nombre y apellidos', 'text'), 'f-nombre-y-apellidos');
    assert.equal(idDeCampo(undefined, '', 'password'), 'f-password');
    assert.equal(idDeCampo('pjc-tel', 'Teléfono', 'tel'), 'pjc-tel');
    assert.equal(idDeCasilla(undefined, 'He leído y acepto el descargo de responsabilidad.'), 'cb-he-le-do-y-acepto-el-des');
    assert.equal(idDeCasilla(undefined, ''), 'cb-x');
});

test('las columnas de las opciones: automáticas, o N que caen a una', () => {
    assert.equal(columnasOpciones('auto'), 'repeat(auto-fit, minmax(210px, 1fr))');
    assert.equal(columnasOpciones('1'), 'repeat(auto-fit, minmax(clamp(calc((100% - 0 * 12px) / 1), calc((17rem - 100%) * 999), 100%), 1fr))');
    assert.equal(columnasOpciones('x'), columnasOpciones('2'));
});

test('el navegador de Instagram, Facebook y TikTok se reconoce; Safari y Chrome, no', () => {
    assert.equal(enNavegadorDeApp('Mozilla/5.0 (iPhone) Instagram 300.0'), true);
    assert.equal(enNavegadorDeApp('Mozilla/5.0 [FBAN/FBIOS;FBAV/400.0]'), true);
    assert.equal(enNavegadorDeApp('Mozilla/5.0 musical_ly_29.0'), true);
    assert.equal(enNavegadorDeApp('Mozilla/5.0 (iPhone) Version/17.0 Mobile Safari/604.1'), false);
    assert.equal(enNavegadorDeApp(undefined), false);
});

test('las barras del hueco de carga, por forma', () => {
    assert.deepEqual(barrasEsqueleto({ kind: 'text', lines: 3 }).barras.map((b) => [b.w, b.delay]), [['100%', '0ms'], ['100%', '90ms'], ['62%', '180ms']]);
    assert.equal(barrasEsqueleto({ kind: 'text', lines: 1 }).barras[0].w, '100%');
    assert.deepEqual(barrasEsqueleto({ kind: 'circle' }).extra, { width: '48px' });
    assert.equal(barrasEsqueleto({ kind: 'price' }).fila, true);
    assert.equal(barrasEsqueleto({ kind: 'card' }).barras[0].ar, 'var(--ar-card)');
    assert.equal(barrasEsqueleto({ kind: 'card', height: '80px' }).barras[0].ar, undefined);
    assert.deepEqual(barrasEsqueleto({ kind: 'block', height: '52px', radius: 'var(--r-md)' }).bloque, { h: '52px', ar: undefined, r: 'var(--r-md)' });
    assert.equal(barrasEsqueleto({ kind: 'block', aspect: '16 / 9' }).bloque.h, undefined);
});

test('el texto de un botón para su bola de carga: solo si dentro hay SOLO texto', () => {
    assert.equal(textoDeRanura([createTextVNode('Pagar 24 € con tarjeta')]), 'Pagar 24 € con tarjeta');
    assert.equal(textoDeRanura([h('span', 'Pagar')]), '');
    assert.equal(textoDeRanura([createTextVNode('a'), createTextVNode('b')]), '');
    assert.equal(textoDeRanura(undefined), '');
});

test('el mes del calendario se mueve cruzando el año, hacia delante y hacia atrás', () => {
    assert.equal(mesDesplazado('2026-09', 1), '2026-10');
    assert.equal(mesDesplazado('2026-12', 1), '2027-01');
    assert.equal(mesDesplazado('2026-01', -1), '2025-12');
    assert.equal(mesDesplazado('2026-09', -21), '2024-12');
});

test('las casillas del mes empiezan en lunes y cuentan sus días', () => {
    const septiembre = celdasDelMes('2026-09'); // el 1 es martes
    assert.equal(septiembre[0], null);
    assert.deepEqual(septiembre[1], { n: 1, date: '2026-09-01' });
    assert.equal(septiembre.length, 1 + 30);
    assert.equal(celdasDelMes('2026-06')[0].date, '2026-06-01'); // el 1 es lunes: sin hueco
    assert.equal(celdasDelMes('2028-02').filter(Boolean).length, 29);
});

test('un día: sin fila, cerrado; completo, tachado y solo pulsable con aviso; la marca especial, nunca si está lleno', () => {
    const d = '2026-09-26';
    assert.deepEqual(estadoDia(undefined, { date: d }), { libre: false, lleno: false, activo: false, hoy: false, especial: false, pulsable: false });
    assert.equal(estadoDia({ date: d }, { date: d }).libre, true);
    assert.equal(estadoDia({ date: d, special: true }, { date: d }).especial, true);
    assert.deepEqual([estadoDia({ date: d, state: 'full', special: true }, { date: d }).especial, estadoDia({ date: d, state: 'full' }, { date: d }).pulsable], [false, false]);
    assert.equal(estadoDia({ date: d, state: 'full' }, { date: d, avisa: true }).pulsable, true);
    assert.deepEqual([estadoDia({ date: d }, { date: d, value: d, today: d }).activo, estadoDia({ date: d }, { date: d, today: d }).hoy], [true, true]);
});

test('la vista del mes: su nombre y sus iniciales en el idioma, los topes y el nombre accesible de cada día', () => {
    const textos = { 'pieza.calendario.dia': ':n de :mes', 'pieza.calendario.aria_hoy': ', hoy', 'pieza.calendario.aria_especial': ', libre, :tarifa', 'pieza.calendario.aria_cerrado': ', cerrado', 'pieza.calendario.aria_completo': ', completo' };
    const t = (k) => textos[k];
    const tp = (k, p) => Object.entries(p).reduce((s, [n, x]) => s.replace(`:${n}`, x), textos[k]);
    const v = vistaCalendario({ mes: '2026-09', days: [{ date: '2026-09-26', special: true }, { date: '2026-09-27', state: 'full' }], today: '2026-09-23', minMonth: '2026-09', especial: 'Tarifa especial', sobre: '2026-09-26' }, { t, tp });

    assert.deepEqual([v.nombre, v.anio, v.iniciales.join(''), v.puedeAtras, v.puedeAlante], ['septiembre', 2026, 'LMXJVSD', false, true]);
    const dia = (n) => v.celdas.find((c) => c?.n === n);
    assert.deepEqual([dia(23).aria, dia(26).aria, dia(27).aria], ['23 de septiembre, hoy, cerrado', '26 de septiembre, libre, tarifa especial', '27 de septiembre, completo']);
    assert.deepEqual([dia(26).sobre, dia(24).sobre], [true, false]);
    assert.equal(vistaCalendario({ mes: '2026-09', locale: 'en' }, { t, tp }).iniciales.join(''), 'MTWTFSS');
});

test('compartir: un enlace de fuera se abre aparte y sin `opener`; uno de la casa, en su sitio; sin enlace, un botón', () => {
    assert.deepEqual(atributosCompartir({ href: 'https://wa.me/?text=x' }), { href: 'https://wa.me/?text=x', target: '_blank', rel: 'noopener noreferrer' });
    assert.deepEqual(atributosCompartir({ href: '#precio' }), { href: '#precio', target: undefined, rel: undefined });
    assert.deepEqual(atributosCompartir({ kind: 'copy' }), { type: 'button' });
});
