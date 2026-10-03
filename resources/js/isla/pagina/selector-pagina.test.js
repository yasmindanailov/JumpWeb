import { test } from 'node:test';
import assert from 'node:assert/strict';
import { haySelectorEnLaPagina } from './selector-pagina.js';

/** La configuración de la isla de la página, como la sirve el producto (`#jw-isla-pagina`). */
const pagina = (config) => ({ getElementById: (id) => (id === 'jw-isla-pagina' ? { textContent: JSON.stringify({ config }) } : null) });

test('la página con selector de planes (con opciones) lo tiene; sin él, sin opciones o sin isla, no', () => {
    assert.equal(haySelectorEnLaPagina(pagina({ plans: { title: 'Elige', options: [{ title: 'Kids' }] } })), true);
    assert.equal(haySelectorEnLaPagina(pagina({ plans: { title: 'Elige', options: [{ title: 'Kids' }], backOnly: true } })), true, 'el que solo es destino de la flecha también cuenta');
    assert.equal(haySelectorEnLaPagina(pagina({ plans: { title: 'Elige', options: [] } })), false, 'sin opciones no hay nada que elegir');
    assert.equal(haySelectorEnLaPagina(pagina({})), false);
    assert.equal(haySelectorEnLaPagina({ getElementById: () => null }), false, 'sin isla en la página');
    assert.equal(haySelectorEnLaPagina({ getElementById: () => ({ textContent: '{roto' }) }), false, 'un JSON roto no rompe la compra');
});
