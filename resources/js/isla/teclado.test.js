import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alPulsarIntro, desplazarHastaVer, esCampoDeIntro, leerTeclado, pistasDeIntro } from './teclado.js';

test('el teclado: lo que tapa de más de 120px es un teclado; menos, una barra o el zoom', () => {
    // iPhone 12 (844 de alto) con el teclado abierto: se ven 480.
    assert.deepEqual(leerTeclado({ alto: 844, visible: 480, desplazado: 0 }), { h: 480, top: 0 });
    assert.equal(leerTeclado({ alto: 844, visible: 760, desplazado: 0 }), null, '84px: la barra del navegador, no un teclado');
    assert.equal(leerTeclado({ alto: 844, visible: 724, desplazado: 0 }), null, 'justo 120: todavía no');
    assert.deepEqual(leerTeclado({ alto: 844, visible: 723.6, desplazado: 12.4 }), { h: 724, top: 12 });
    assert.equal(leerTeclado({ alto: 844, visible: 0 }), null, 'sin ventana visible (sin `visualViewport`): nada');
});

test('el teclado: si no ha cambiado, el MISMO objeto (quien lo guarda no repinta)', () => {
    const antes = { h: 480, top: 0 };
    assert.equal(leerTeclado({ alto: 844, visible: 480, desplazado: 0 }, antes), antes);
    assert.notEqual(leerTeclado({ alto: 844, visible: 470, desplazado: 0 }, antes), antes);
});

test('Intro: al siguiente campo y, en el último, la acción del paso', () => {
    const [nombre, correo, clave] = [{}, {}, {}];
    assert.deepEqual(alPulsarIntro([nombre, correo, clave], nombre), { que: 'siguiente', indice: 1 });
    assert.deepEqual(alPulsarIntro([nombre, correo, clave], correo), { que: 'siguiente', indice: 2 });
    assert.deepEqual(alPulsarIntro([nombre, correo, clave], clave), { que: 'enviar' });
    assert.deepEqual(alPulsarIntro([correo], correo), { que: 'enviar' }, 'un solo campo: Intro es la acción');
    assert.equal(alPulsarIntro([nombre, correo], {}), null, 'fuera de la lista (oculto, o no es un campo): nada');
});

test('Intro recorre los campos de texto, no las casillas ni los botones', () => {
    assert.equal(esCampoDeIntro({ tagName: 'INPUT', type: 'email' }), true);
    assert.equal(esCampoDeIntro({ tagName: 'INPUT', type: 'password' }), true);
    assert.equal(esCampoDeIntro({ tagName: 'INPUT', type: 'checkbox' }), false);
    assert.equal(esCampoDeIntro({ tagName: 'INPUT', type: 'submit' }), false);
    assert.equal(esCampoDeIntro({ tagName: 'BUTTON', type: 'button' }), false);
    assert.equal(esCampoDeIntro(null), false);
});

test('la pista del teclado: «Siguiente» entre campos e «Ir» en el último', () => {
    assert.deepEqual(pistasDeIntro(3), ['next', 'next', 'go']);
    assert.deepEqual(pistasDeIntro(1), ['go']);
    assert.deepEqual(pistasDeIntro(0), []);
});

test('el campo con el foco no queda tapado: se baja o se sube lo justo, con aire', () => {
    const caja = { top: 100, bottom: 500 };
    assert.equal(desplazarHastaVer(caja, { top: 200, bottom: 250 }), 0, 'se ve: nada');
    assert.equal(desplazarHastaVer(caja, { top: 470, bottom: 520 }), 44, 'tapado por abajo: 520 − 500 + 24');
    assert.equal(desplazarHastaVer(caja, { top: 90, bottom: 140 }), -34, 'tapado por arriba: −(100 − 90 + 24)');
});
