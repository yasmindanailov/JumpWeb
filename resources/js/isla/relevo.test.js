import { test } from 'node:test';
import assert from 'node:assert/strict';
import { VIDA, cajaDe, desde, registrar, soltar, vigente } from './relevo.js';
import { desplazamientoDesde, filasDePanel } from './movimiento.js';

// Una isla de mentira: lo que el relevo lee de una de verdad (su caja, su radio, si lleva velo y si sigue montada).
const isla = (left, top, width, height, { radio = 999, velo = false } = {}) => ({
    isConnected: true, dataset: velo ? { islaVelo: '1' } : {}, radio,
    getBoundingClientRect: () => ({ left, top, width, height }),
});
globalThis.getComputedStyle = (el) => ({ borderTopLeftRadius: `${el.radio}px` });

test('la caja de una isla: una píldora de 999px de radio mide la mitad de su alto', () => {
    assert.deepEqual(cajaDe({ left: 16, top: 740, width: 358, height: 90 }, 999), { x: 16, y: 740, w: 358, h: 90, radio: 45 });
    assert.equal(cajaDe({ left: 0, top: 0, width: 600, height: 380 }, 20).radio, 20);
});

test('la que entra crece desde OTRA que siga en pantalla (la píldora que espera), y esa ya no deja su caja al irse', () => {
    const pildora = isla(16, 740, 358, 90);
    const compra = isla(8, 8, 374, 828);
    registrar(pildora);
    const d = desde(compra);
    assert.deepEqual([d.x, d.y, d.w, d.h, d.velo], [16, 740, 358, 90, false]);
    registrar(compra);
    soltar(pildora);
    // La píldora relevada se fue sin dejar nada: la siguiente que entre no crece desde ella.
    soltar(compra);
    const vuelta = desde(isla(16, 740, 358, 90));
    assert.deepEqual([vuelta.y, vuelta.h], [8, 828], 'la píldora que vuelve crece desde la compra que se acaba de ir');
});

test('la caja que dejó una isla que se fue vale un momento, y se toma UNA vez', () => {
    const capa = isla(8, 8, 374, 828, { velo: true });
    registrar(capa);
    soltar(capa);
    const d = desde(isla(16, 740, 358, 90), performance.now());
    assert.equal(d.velo, true, 'llevaba velo: la que entra lo funde');
    assert.equal(desde(isla(16, 740, 358, 90)), null, 'tomada una vez');
    assert.equal(vigente({ t: 0 }, VIDA - 1), true);
    assert.equal(vigente({ t: 0 }, VIDA), false);
    assert.equal(vigente(null, 0), false);
});

test('el desplazamiento del relevo casa el CENTRO y el borde anclado, no la esquina', () => {
    // Móvil: la píldora (abajo, 358×90 en y = 740) y la compra a pantalla completa, las dos pegadas abajo.
    const pildora = { x: 16, y: 740, w: 358, h: 90 };
    const compra = { x: 8, y: 8, w: 374, h: 828 };
    assert.deepEqual(desplazamientoDesde(pildora, compra, false), { dx: 0, dy: -6 }, 'los bordes de abajo: 830 y 836');
    // Escritorio: la píldora arriba centrada (379 de ancho, y = 14) y la compra de 600 en y = 34, las dos arriba.
    const arriba = desplazamientoDesde({ x: 451, y: 14, w: 379, h: 62 }, { x: 340, y: 34, w: 600, h: 379 }, true);
    assert.deepEqual(arriba, { dx: 0.5, dy: -20 });
});

test('las filas de un panel, en el orden en que se leen: el título, los hijos del cuerpo y las listas abiertas', () => {
    const el = (tagName, children = []) => ({ tagName, children });
    const pn = el('DIV', [el('DIV'), el('DIV', [el('BUTTON'), el('UL', [el('LI'), el('LI')]), el('P')])]);
    assert.deepEqual(filasDePanel(pn).map((x) => x.tagName), ['DIV', 'BUTTON', 'LI', 'LI', 'P']);
    const solo = el('DIV', [el('DIV', [])]);
    assert.deepEqual(filasDePanel(solo), [solo], 'una sola fila: el panel entero');
});
