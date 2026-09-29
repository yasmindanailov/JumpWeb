import { test } from 'node:test';
import assert from 'node:assert/strict';
import { estiloBoton, margenTextoBoton } from './estilos.js';

// `#844` (`[DECIDIDO owner]` 2026-09-29): el botón de ANCHO COMPLETO parte su texto si no cabe —alto MÍNIMO con aire, texto
// equilibrado—; el que no lo es sigue como el `Button` del diseño, de alto fijo y en una línea.
test('el botón de ancho completo parte su texto y crece: su alto de talla pasa a mínimo, con aire arriba y abajo', () => {
    const e = estiloBoton({ variant: 'primary', size: 'xl', full: true });

    assert.equal(e.whiteSpace, 'normal');
    assert.equal(e.height, undefined, 'con alto fijo, dos líneas se saldrían de la píldora');
    assert.equal(e.minHeight, 'var(--control-xl)', 'si cabe, el mismo alto de su talla que en el diseño');
    assert.equal(e.paddingBlock, '10px');
    assert.equal(e.textWrap, 'balance');
    assert.equal(e.width, '100%');
});

test('su texto se come el aire lateral de su talla antes de partir: el margen es ese aire, en negativo', () => {
    // Medido: con el aire como MÍNIMO, «Reservar y pagar la señal» pedía 294 px (214 de texto + 80) y ensanchaba la columna.
    assert.equal(margenTextoBoton({ full: true, iconos: false, size: 'xl' }), '-40px');
    assert.equal(margenTextoBoton({ full: true, iconos: false, size: 'md' }), '-26px');
    assert.equal(margenTextoBoton({ full: true, iconos: false, size: 'rara' }), '-26px', 'una talla desconocida, la de por defecto');
    // Y solo el de ancho completo SIN iconos (con ellos, el texto se les montaría encima).
    assert.equal(margenTextoBoton({ full: true, iconos: true, size: 'xl' }), undefined);
    assert.equal(margenTextoBoton({ full: false, iconos: false, size: 'xl' }), undefined);
});

test('el que no es de ancho completo sigue como el diseño: alto fijo de su talla y una sola línea', () => {
    const e = estiloBoton({ variant: 'primary', size: 'xl', full: false });

    assert.equal(e.whiteSpace, 'nowrap');
    assert.equal(e.height, 'var(--control-xl)');
    assert.equal(e.minHeight, undefined);
    assert.equal(e.paddingBlock, undefined);
    // La secundaria tranquila ya partía antes, con o sin ancho completo.
    assert.equal(estiloBoton({ variant: 'quiet', size: 'md' }).whiteSpace, 'normal');
});
