import { test } from 'node:test';
import assert from 'node:assert/strict';
import { precargarCompra, puedePrecargar } from './precarga.js';

test('se adelanta la compra salvo con «ahorro de datos» o en 2G', () => {
    assert.equal(puedePrecargar(undefined), true, 'sin la API de la conexión (Safari, Firefox): sí');
    assert.equal(puedePrecargar({ effectiveType: '4g' }), true);
    assert.equal(puedePrecargar({ effectiveType: '3g' }), true);
    assert.equal(puedePrecargar({ effectiveType: '2g' }), false);
    assert.equal(puedePrecargar({ effectiveType: 'slow-2g' }), false);
    assert.equal(puedePrecargar({ effectiveType: '4g', saveData: true }), false);
});

test('con la página cargada, en un rato ocioso, un `modulepreload` por trozo y sin repetir los que ya están', () => {
    const puestos = [];
    const doc = {
        readyState: 'complete',
        querySelectorAll: () => puestos,
        createElement: () => ({}),
        head: { append: (l) => puestos.push(l) },
    };
    const win = { navigator: {}, requestIdleCallback: (fn) => fn() };
    precargarCompra(['https://x/a.js', 'https://x/b.js'], { doc, win });
    precargarCompra(['https://x/a.js'], { doc, win });
    assert.deepEqual(puestos.map((l) => [l.rel, l.href]), [['modulepreload', 'https://x/a.js'], ['modulepreload', 'https://x/b.js']]);
    precargarCompra(['https://x/c.js'], { doc, win: { navigator: { connection: { saveData: true } }, requestIdleCallback: (fn) => fn() } });
    assert.equal(puestos.length, 2, 'con «ahorro de datos», nada');
});
