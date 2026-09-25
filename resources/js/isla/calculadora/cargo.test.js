import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cargarCargo } from './cargo.js';

test('lo que carga un complemento sin día ni hora es su `charged_cents` del servidor; sin respuesta, nada', async () => {
    const pedidas = [];
    const api = { post: async (ruta, cuerpo) => { pedidas.push([ruta, cuerpo]); return { ok: true, data: { singles: [{ product_id: 110, charged_cents: 450 }] } }; } };

    assert.equal(await cargarCargo({ api, productId: 101, quantity: 3, addons: [{ product_id: 110, quantity: 2 }], id: 110 }), 450);
    // Sin día ni hora en la petición: el endpoint los rechaza en `null`.
    assert.deepEqual(pedidas, [['/catalog/products/101/addons', { quantity: 3, addons: [{ product_id: 110, quantity: 2 }], choices: [] }]]);
    assert.equal(await cargarCargo({ api: { post: async () => ({ ok: false }) }, productId: 101, quantity: 1, addons: [], id: 110 }), null);
    assert.equal(await cargarCargo({ api: { post: async () => ({ ok: true, data: { singles: [] } }) }, productId: 101, quantity: 1, addons: [], id: 110 }), null);
});
