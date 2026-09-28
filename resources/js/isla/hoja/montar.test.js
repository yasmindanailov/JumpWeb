import { test } from 'node:test';
import assert from 'node:assert/strict';
import { diaLargo, euros, leerCalculo, rellenarHoja } from './montar.js';
import { codificarCalculo, leerCalculo as leerDeLaCalculadora } from '../calculadora/vista.js';
import { diaLargo as diaLargoDeLaCompra, euros as eurosDeLaCompra } from '../compra/vista.js';

/**
 * **Las COPIAS no divergen** (la hoja no importa nada compartido: reagrupaba los trozos del motor, medido): leen lo que la
 * calculadora escribe igual que ella, y escriben euros y fechas igual que la compra.
 */
test('las copias de la hoja dicen lo mismo que las originales', () => {
    const pagina = { filas: [{ id: 395, min: 30, max: 100 }, { id: 396, min: 30, max: 100 }] };

    for (const b of [{ n: 60, fila: 395, dia: '2026-10-20', hora: '10:00:00' }, { n: 75, fila: 396, dia: null, hora: null }, { n: 20, fila: 395, dia: null, hora: null }]) {
        const texto = codificarCalculo(b);

        assert.deepEqual(leerCalculo(texto, pagina, '2026-09-28'), leerDeLaCalculadora(texto, pagina, '2026-09-28'), texto);
    }
    for (const x of ['60_999__', '60_395_2026-01-01_10:00', 'basura', '']) {
        assert.deepEqual(leerCalculo(x, pagina, '2026-09-28'), leerDeLaCalculadora(x, pagina, '2026-09-28'), x);
    }
    for (const c of [800, 640, 97500, 1, 123456]) {
        for (const l of ['es', 'en', 'fr']) assert.equal(euros(c, l), eurosDeLaCompra(c, l), `${c} ${l}`);
    }
    for (const d of ['2026-10-01', '2026-12-25']) {
        for (const l of ['es', 'en', 'fr']) assert.equal(diaLargo(d, l), diaLargoDeLaCompra(d, l), `${d} ${l}`);
    }
});

/**
 * La hoja para dirección con las cifras del GRUPO (T6c·4b, `#837`): del enlace (`?c=`) y del SERVIDOR (la línea de ese
 * grupo, la misma pregunta que la calculadora); sin respuesta, la hoja se queda con sus cifras generales.
 */
const NBSP = String.fromCharCode(0xa0);

function documento() {
    const el = (datos = {}) => ({ textContent: '', hidden: datos.hidden ?? false, dataset: datos.dataset ?? {} });
    const raiz = el({ dataset: { jwHoja: JSON.stringify({
        hoy: '2026-09-28', cuando: 'Vuestro grupo: :dia a las :hora · :duracion',
        filas: [{ id: 395, label: '2 horas', min: 30, max: 100 }, { id: 396, label: '3 horas', min: 30, max: 100 }],
    }) } });
    const marcas = {
        '[data-jw-hoja-cifra="alumnos"]': [el()], '[data-jw-hoja-cifra="porAlumno"]': [el()], '[data-jw-hoja-cifra="total"]': [el()],
        '[data-jw-hoja-cuando]': [el()], '[data-jw-hoja-grupo]': [el({ hidden: true })], '[data-jw-hoja-general]': [el()],
    };

    return {
        marcas,
        documentElement: { lang: 'es' },
        querySelector: (sel) => (sel === '[data-jw-hoja]' ? raiz : null),
        querySelectorAll: (sel) => marcas[sel] ?? [],
    };
}
const ventana = (search) => ({ location: { search } });

test('con un cálculo en el enlace, las cifras del grupo son las de la línea del SERVIDOR', async () => {
    const doc = documento();
    const pedidas = [];
    const peticion = { post: async (url, datos) => { pedidas.push([url, datos]); return { ok: true, data: { line: { unit_price_cents: 1500, total_cents: 90000 } } }; } };

    assert.equal(await rellenarHoja({ doc, win: ventana('?c=60_395_2026-10-20_10%3A00'), peticion }), true);
    assert.deepEqual(pedidas, [['/catalog/products/395/addons', { quantity: 60, date: '2026-10-20', time: '10:00:00', addons: [], choices: [] }]]);
    const texto = (sel) => doc.marcas[sel][0].textContent;

    assert.deepEqual([texto('[data-jw-hoja-cifra="alumnos"]'), texto('[data-jw-hoja-cifra="porAlumno"]'), texto('[data-jw-hoja-cifra="total"]')], ['60', `15${NBSP}€`, `900${NBSP}€`]);
    assert.equal(texto('[data-jw-hoja-cuando]'), 'Vuestro grupo: Martes 20 de octubre a las 10:00 · 2 horas');
    assert.deepEqual([doc.marcas['[data-jw-hoja-grupo]'][0].hidden, doc.marcas['[data-jw-hoja-general]'][0].hidden], [false, true]);
});

test('sin cálculo, con uno sin hora o sin respuesta del servidor, la hoja no cambia (nunca unas cifras inventadas)', async () => {
    const falla = { post: async () => ({ ok: false }) };
    const nunca = { post: async () => { throw new Error('no se pregunta'); } };

    for (const [search, peticion] of [['', nunca], ['?c=60_395__', nunca], ['?c=60_101_2026-10-20_10%3A00', nunca], ['?c=60_395_2026-10-20_10%3A00', falla]]) {
        const doc = documento();

        assert.equal(await rellenarHoja({ doc, win: ventana(search), peticion }), false, search);
        assert.equal(doc.marcas['[data-jw-hoja-grupo]'][0].hidden, true, search);
        assert.equal(doc.marcas['[data-jw-hoja-cifra="total"]'][0].textContent, '', search);
    }
});
