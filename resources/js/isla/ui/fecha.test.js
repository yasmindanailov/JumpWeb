import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fechaDeIso, fechaTecleada, isoDeFecha } from './fecha.js';
import * as hijos from '../cuenta/hijos.js';

/**
 * La fecha como se teclea en la isla (`ui/fecha.js`): la de un hijo (T5d) y la del titular (`#792`), un solo control.
 */
test('las barras solas, sin letras, y ocho cifras como mucho', () => {
    assert.equal(fechaTecleada('07'), '07');
    assert.equal(fechaTecleada('070'), '07/0');
    assert.equal(fechaTecleada('07032019'), '07/03/2019');
    assert.equal(fechaTecleada('07/03/2019x9'), '07/03/2019');
});

test('a Y-m-d solo un día que existe, y de vuelta como se teclea', () => {
    assert.equal(isoDeFecha('07/03/2019'), '2019-03-07');
    assert.equal(isoDeFecha('29/02/2019'), null, 'el 29 de febrero de un año no bisiesto no existe');
    assert.equal(isoDeFecha('07/03'), null);
    assert.equal(fechaDeIso('2019-03-07'), '07/03/2019');
    assert.equal(fechaDeIso(null), '');
    assert.equal(fechaDeIso('07/03/2019'), '', 'otra forma no se adivina');
});

test('los hijos siguen leyendo las mismas funciones (reexportadas, no copiadas)', () => {
    assert.equal(hijos.fechaTecleada, fechaTecleada);
    assert.equal(hijos.isoDeFecha, isoDeFecha);
});
