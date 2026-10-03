import { test } from 'node:test';
import assert from 'node:assert/strict';
import { medirEleccion } from './embudo-isla.js';

function medidor() {
    const hechos = [];

    return { hechos, medir: (nombre, datos) => hechos.push([nombre, datos]) };
}

test('elegir DÍA, HORA o PRODUCTO en la pantalla 0 cuenta, con el producto que se mira', () => {
    const { hechos, medir } = medidor();
    const borrador = { fila: 100 };

    assert.equal(medirEleccion('dia', '2026-10-10', borrador, medir), 'date_chosen');
    assert.equal(medirEleccion('hora', '17:00', borrador, medir), 'time_chosen');
    assert.equal(medirEleccion('fila', '103', borrador, medir), 'product_chosen');

    assert.deepEqual(hechos, [
        ['date_chosen', { product: 100, date: '2026-10-10' }],
        ['time_chosen', { product: 100 }],
        ['product_chosen', { product: 103 }],
    ]);
});

test('lo demás (gente, complementos, datos de la reserva) y un valor vacío no cuentan', () => {
    const { hechos, medir } = medidor();
    const borrador = { fila: 100 };

    for (const [campo, valor] of [['n', 3], ['extra', { id: 1, n: 2 }], ['evento', { key: 'nombre', valor: 'Lía' }], ['dia', null], ['hora', ''], ['fila', null]]) {
        assert.equal(medirEleccion(campo, valor, borrador, medir), null);
    }

    assert.deepEqual(hechos, []);
});

test('sin producto todavía (la zona sin fila), el día cuenta igual, con el producto vacío', () => {
    const { hechos, medir } = medidor();

    medirEleccion('dia', '2026-10-11', {}, medir);

    assert.deepEqual(hechos, [['date_chosen', { product: null, date: '2026-10-11' }]]);
});
