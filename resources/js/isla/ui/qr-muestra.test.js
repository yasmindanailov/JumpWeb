import { test } from 'node:test';
import assert from 'node:assert/strict';
import { celdas } from './qr-muestra.js';

const clave = ([r, c]) => `${r},${c}`;

test('el dibujo de muestra sale siempre igual para el mismo código, y cambia con el código', () => {
    const a = celdas('R-7K2P4').map(clave);
    assert.deepEqual(celdas('R-7K2P4').map(clave), a);
    assert.notDeepEqual(celdas('R-7K2P5').map(clave), a);
});

test('lleva las tres esquinas de un QR y la alineación, con su hueco en medio', () => {
    const hay = new Set(celdas('R-7K2P4').map(clave));
    for (const [r, c] of [[0, 0], [0, 24], [24, 0], [3, 3], [3, 21], [21, 3], [16, 16], [18, 18], [20, 20]]) {
        assert.ok(hay.has(`${r},${c}`), `falta la celda ${r},${c}`);
    }
    for (const [r, c] of [[1, 1], [5, 5], [17, 17]]) {
        assert.ok(! hay.has(`${r},${c}`), `sobra la celda ${r},${c}`);
    }
});

test('ninguna celda cae fuera de la rejilla de 25', () => {
    assert.ok(celdas('ABC').every(([r, c]) => r >= 0 && r < 25 && c >= 0 && c < 25));
});
