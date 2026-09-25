import test from 'node:test';
import assert from 'node:assert/strict';

import { capitalizar, choice, clave, cuentas, estadoFicha, euros, limpiar } from './logica.js';

test('la clave de un nombre ignora tildes, mayúsculas y espacios de más', () => {
    assert.equal(clave('  Álex   Romero '), 'alex romero');
    assert.equal(clave('MARÍA josé'), 'maria jose');
});

test('capitalizar solo toca un nombre escrito todo en minúsculas', () => {
    assert.equal(capitalizar('mateo gil'), 'Mateo Gil');
    assert.equal(capitalizar('Mateo  de la Fuente'), 'Mateo de la Fuente');
});

test('pegar una lista quita viñetas, numeraciones y teléfonos, y no repite', () => {
    const texto = '1. Hugo Martín 655 120 387\n- carla gómez\n• Leo\nHugo Martín\n\n3) Nora +34 600 11 22 33\nx';
    const r = limpiar(texto, ['Leo']);
    assert.deepEqual(r.nombres, ['Hugo Martín', 'Carla Gómez', 'Nora']);
    assert.equal(r.repetidos, 2, 'Leo ya estaba y Hugo se repite');
});

test('pegar una lista en una sola línea con comas parte por comas', () => {
    assert.deepEqual(limpiar('Lucía, Mateo; Hugo').nombres, ['Lucía', 'Mateo', 'Hugo']);
});

test('el estado de una ficha: completa, con datos y qué le falta', () => {
    const campos = (nombre, edad) => [
        { required: true, filled: nombre, label: 'Nombre' },
        { required: true, filled: edad, label: 'Edad' },
        { required: false, filled: false, label: 'Alergias' },
    ];
    assert.deepEqual(estadoFicha(campos(true, true)), { completa: true, conDatos: true, falta: null });
    assert.deepEqual(estadoFicha(campos(true, false)), { completa: false, conDatos: true, falta: 'Edad' });
    assert.deepEqual(estadoFicha(campos(false, false)), { completa: false, conDatos: false, falta: null });
    assert.equal(estadoFicha(campos(true, true), true).completa, false, 'una edad sin producto no está completa');
});

test('las cuentas de la zona 1 no cuentan las fichas vacías', () => {
    const c = cuentas([
        { vacia: false, respuesta: 'si' }, { vacia: false, respuesta: 'si' }, { vacia: false, respuesta: 'no' },
        { vacia: false, respuesta: null }, { vacia: true, respuesta: null },
    ]);
    assert.deepEqual(c, { confirmados: 2, noPueden: 1, sinContestar: 1, enLista: 4 });
});

test('choice resuelve el plural de Laravel con intervalos y marcadores', () => {
    assert.equal(choice('{1} Queda 1 plaza libre.|[2,*] Quedan :count plazas libres.', 1), 'Queda 1 plaza libre.');
    assert.equal(choice('{1} Queda 1 plaza libre.|[2,*] Quedan :count plazas libres.', 7), 'Quedan 7 plazas libres.');
    assert.equal(choice('{0} Añadir|{1} Añadir 1 niño|[2,*] Añadir :count niños', 0), 'Añadir');
    assert.equal(choice(':name, en la lista.', 1, { name: 'Mateo' }), 'Mateo, en la lista.');
});

test('euros escribe como el servidor', () => {
    const NBSP = String.fromCharCode(160);
    assert.equal(euros(1600), `16${NBSP}€`);
    assert.equal(euros(1695), `16,95${NBSP}€`);
});
