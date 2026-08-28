import test from 'node:test';
import assert from 'node:assert/strict';

import { scrollState, scrollStep } from './strip.js';

/**
 * El desplazamiento con ratón de las tiras (`DECISIONES #241`).
 *
 * Se prueba con objetos planos —`{scrollLeft, clientWidth, scrollWidth}`— porque eso es exactamente
 * lo que el módulo lee de un elemento: sin navegador, sin DOM y sin montar nada.
 */

test('sin recorrido no hay flechas: una tira que cabe entera no ofrece ninguna', () => {
    assert.deepEqual(scrollState({ scrollLeft: 0, clientWidth: 350, scrollWidth: 350 }), { prev: false, next: false });
    assert.deepEqual(scrollState({ scrollLeft: 0, clientWidth: 350, scrollWidth: 300 }), { prev: false, next: false });
});

test('al principio solo se puede ir hacia delante', () => {
    assert.deepEqual(scrollState({ scrollLeft: 0, clientWidth: 350, scrollWidth: 1200 }), { prev: false, next: true });
});

test('en medio se puede ir a los dos lados', () => {
    assert.deepEqual(scrollState({ scrollLeft: 400, clientWidth: 350, scrollWidth: 1200 }), { prev: true, next: true });
});

test('al final solo se puede volver', () => {
    assert.deepEqual(scrollState({ scrollLeft: 850, clientWidth: 350, scrollWidth: 1200 }), { prev: true, next: false });
});

/**
 * ⚠️ **La tolerancia no es defensiva: sin ella la flecha «siguiente» NO SE APAGA NUNCA.**
 * `scrollLeft` es fraccionario con zoom o en pantallas de densidad alta, así que el final del
 * recorrido se queda a medio píxel y la comparación estricta lo lee como «aún queda».
 */
test('media décima de píxel al final ya cuenta como final', () => {
    assert.equal(scrollState({ scrollLeft: 849.6, clientWidth: 350, scrollWidth: 1200 }).next, false);
    assert.equal(scrollState({ scrollLeft: 0.4, clientWidth: 350, scrollWidth: 1200 }).prev, false);
});

test('sin carril no lanza y no ofrece nada', () => {
    assert.deepEqual(scrollState(null), { prev: false, next: false });
    assert.deepEqual(scrollState(undefined), { prev: false, next: false });
});

/** Una pantalla MENOS un chip: el chip que se queda es lo que dice hacia dónde se ha movido. */
test('el salto es el 80 % del ancho visible', () => {
    assert.equal(scrollStep({ clientWidth: 350 }), 280);
    assert.equal(scrollStep({ clientWidth: 1000 }), 800);
});

/** Nunca 0: un salto de cero sería una flecha que se pulsa y no hace nada. */
test('el salto nunca es cero, ni sin carril', () => {
    assert.equal(scrollStep(null), 1);
    assert.equal(scrollStep({ clientWidth: 0 }), 1);
});
