import assert from 'node:assert/strict';
import { test } from 'node:test';

import { addonGifts, hasAddonInfo } from './addon-info.js';

/** Los regalos solo se ven dentro del «Más info» (`#589`): sin ventajas, el botón tiene que salir igual. */
test('un complemento con solo regalos también abre su «Más info»', () => {
    assert.equal(hasAddonInfo({ features: [], gifts: ['Cono de chuches'] }), true);
    assert.equal(hasAddonInfo({ features: ['Bocadillo'], gifts: [] }), true);
    assert.equal(hasAddonInfo({ features: [], gifts: [] }), false);
});

test('sin el campo de regalos —un cliente anterior a #589— no revienta', () => {
    assert.deepEqual(addonGifts({ features: [] }), []);
    assert.equal(hasAddonInfo({ features: [] }), false);
    assert.deepEqual(addonGifts(null), []);
    assert.deepEqual(addonGifts({ gifts: ['Cono'] }), ['Cono']);
});
