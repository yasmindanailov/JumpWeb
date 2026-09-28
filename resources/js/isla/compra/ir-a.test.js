import { test } from 'node:test';
import assert from 'node:assert/strict';
import { arribaDe } from './ir-a.js';

/**
 * Llevar la capa a lo que falta (el owner, 28-09): el bloque queda ARRIBA de la caja con su aire (12 px), contando lo que
 * la caja ya estaba desplazada; nunca por encima del principio.
 */
test('el bloque queda arriba de la caja con su aire, desde donde estuviera', () => {
    const caja = { top: 100 };

    assert.equal(arribaDe(caja, { top: 700 }, 0), 588, 'abajo del todo: se baja hasta él');
    assert.equal(arribaDe(caja, { top: 300 }, 400), 588, 'lo mismo con la caja ya bajada');
    assert.equal(arribaDe(caja, { top: 105 }, 0), 0, 'ya arriba: no se sube por encima del principio');
});
