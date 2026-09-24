import { test } from 'node:test';
import assert from 'node:assert/strict';
import { estiloIsla, estiloMedida, tamano } from './forma.js';

const caja = (w, h, cap = 0) => ({ w, h, cap });

test('la isla no anima hasta la primera medida: el primer tamaño no es una transición desde cero', () => {
    const antes = estiloIsla({ row: true, box: caja(0, 0), alert: false, grown: false, animate: false, calm: false });
    assert.equal(antes.transition, 'none');
    assert.equal(antes.width, 'max-content');
    assert.equal(antes.height, 'auto');

    const medida = estiloIsla({ row: true, box: caja(312.5, 64), alert: false, grown: false, animate: true, calm: false });
    assert.equal(medida.width, '312.5px');
    assert.equal(medida.height, '64px');
    assert.match(medida.transition, /^var\(--t-island\)/);
});

test('abrir o cerrar un panel va sin rebote, y una isla en columna ocupa el ancho entero', () => {
    const abierta = estiloIsla({ row: false, box: caja(300, 400), alert: false, grown: true, animate: true, calm: true });
    assert.equal(abierta.width, '100%');
    assert.equal(abierta.borderRadius, 'var(--r-lg)');
    assert.doesNotMatch(abierta.transition, /--t-island/);
});

test('el pago fallido cambia el borde a su rol de alerta', () => {
    const fallo = estiloIsla({ row: true, box: caja(300, 64), alert: true, grown: false, animate: true, calm: false });
    assert.equal(fallo.border, '1px solid var(--isla-borde-alerta)');
});

test('abierta arriba, el mínimo va en PÍXELES (un % perseguía a la isla que se anima) y nunca pasa de 390', () => {
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 1200, maxWidth: 760 }).minWidth, '390px');
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 320, maxWidth: 760 }).minWidth, '320px');
    assert.equal(estiloMedida({ row: false, top: true, isOpen: true, cap: 0, maxWidth: 360 }).minWidth, '360px');
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 1200, maxWidth: 760 }).minWidth, undefined);
    assert.equal(estiloMedida({ row: true, top: false, isOpen: true, cap: 390, maxWidth: 760 }).minWidth, undefined);
});

test('el techo del medidor: el contenedor o `maxWidth` arriba; abajo, la pantalla menos sus márgenes', () => {
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 1200, maxWidth: 760 }).maxWidth, '760px');
    assert.equal(estiloMedida({ row: true, top: true, isOpen: false, cap: 0, maxWidth: 760 }).maxWidth, '760px');
    assert.equal(estiloMedida({ row: true, top: false, isOpen: false, cap: 390, maxWidth: 760 }).maxWidth, 'calc(100vw - 32px)');
    assert.equal(estiloMedida({ row: false, top: false, isOpen: false, cap: 390, maxWidth: 760 }).maxWidth, '100%');
});

test('el tamaño que declara la raíz, de más a menos: compra, panel, aviso, compacta, reposo', () => {
    assert.equal(tamano({ inCheckout: true, isOpen: true, notice: 'x', isCompact: true }), 'compra');
    assert.equal(tamano({ inCheckout: false, isOpen: true, notice: 'x', isCompact: true }), 'abierta');
    assert.equal(tamano({ inCheckout: false, isOpen: false, notice: 'x', isCompact: true }), 'aviso');
    assert.equal(tamano({ inCheckout: false, isOpen: false, notice: null, isCompact: true }), 'compacta');
    assert.equal(tamano({ inCheckout: false, isOpen: false, notice: null, isCompact: false }), 'reposo');
});
