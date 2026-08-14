import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createScrollLock } from './scroll-lock.js';

/**
 * La red del bloqueo de scroll (`sidebar-spa.md` §6).
 *
 * ⚠️ **Lo que se prueba aquí no se ve en ningún árbol**: `body.no-scroll` se pinta FUERA del cajón —en
 * el `<body>`— y su valor depende de una secuencia de aperturas y cierres, no de un render. El fallo
 * que motiva el módulo es exactamente de esa clase: la página moviéndose por detrás de un panel que
 * sigue abierto, con todo el marcado correcto.
 */

/** Un cerrojo con su espía: `applied` es lo que habría hecho el `<body>`. */
function lockWithSpy() {
    const applied = [];
    const lock = createScrollLock((locked) => applied.push(locked));

    return { lock, applied, get last() { return applied[applied.length - 1]; } };
}

describe('un solo superpuesto', () => {
    test('pedir bloquea y soltar libera', () => {
        const spy = lockWithSpy();

        spy.lock.lock('sidecart');
        assert.deepEqual(spy.lock.owners(), ['sidecart']);
        assert.equal(spy.last, true);

        spy.lock.unlock('sidecart');
        assert.deepEqual(spy.lock.owners(), []);
        assert.equal(spy.last, false);
    });

    test('el estado se APLICA en cada cambio, no solo se recuerda', () => {
        const spy = lockWithSpy();

        spy.lock.lock('sidecart');
        assert.deepEqual(spy.applied, [true]);

        spy.lock.unlock('sidecart');
        assert.deepEqual(spy.applied, [true, false]);
    });
});

describe('dos superpuestos a la vez', () => {
    /**
     * ⚠️ **EL FALLO QUE MOTIVA EL MÓDULO.** Con el cajón de compra abierto, el bloque de cuenta ofrece
     * «Iniciar sesión» y abre el modal de auth. Antes, cerrar el modal hacía `remove('no-scroll')` y la
     * página se movía por detrás del panel, que seguía abierto. Es alcanzable con dos clics.
     */
    test('cerrar el de encima NO libera el scroll si el de abajo sigue abierto', () => {
        const spy = lockWithSpy();

        spy.lock.lock('sidecart');
        spy.lock.lock('auth');
        spy.lock.unlock('auth');

        assert.equal(spy.last, true, 'el cajón sigue abierto: el scroll NO se libera');
        assert.deepEqual(spy.lock.owners(), ['sidecart']);

        spy.lock.unlock('sidecart');
        assert.equal(spy.last, false);
    });

    test('y el orden de cierre da igual', () => {
        const spy = lockWithSpy();

        spy.lock.lock('nav');
        spy.lock.lock('offers');
        spy.lock.unlock('nav');

        assert.equal(spy.last, true);

        spy.lock.unlock('offers');
        assert.equal(spy.last, false);
    });
});

describe('llaves repetidas', () => {
    test('pedir dos veces la misma llave no obliga a soltarla dos', () => {
        // Es un `Set` y no un contador a propósito: un `x-init` que coincida con un `open()` pediría
        // la misma llave dos veces, y con un contador el scroll se quedaría bloqueado para siempre.
        const spy = lockWithSpy();

        spy.lock.lock('sidecart');
        spy.lock.lock('sidecart');
        spy.lock.unlock('sidecart');

        assert.equal(spy.last, false);
        assert.deepEqual(spy.lock.owners(), []);
    });

    test('soltar una llave que nadie pidió no rompe nada', () => {
        const spy = lockWithSpy();

        spy.lock.lock('auth');
        spy.lock.unlock('nunca-pedida');

        assert.equal(spy.last, true, 'el modal de auth sigue teniendo la suya');
    });

    /**
     * ⚠️ **La llave es por INSTANCIA, no por tipo.** «Mis pedidos» pinta un modal por pedido; con una
     * llave compartida, abrir A, abrir B y cerrar A soltaría el scroll con B todavía delante.
     */
    test('dos instancias del mismo tipo llevan llaves distintas', () => {
        const spy = lockWithSpy();

        spy.lock.lock('order-manage:1');
        spy.lock.lock('order-manage:2');
        spy.lock.unlock('order-manage:1');

        assert.equal(spy.last, true);
        assert.deepEqual(spy.lock.owners(), ['order-manage:2']);
    });
});

describe('el atajo para estados observados', () => {
    test('`set` traduce un booleano a pedir o soltar', () => {
        const spy = lockWithSpy();

        spy.lock.set('nav', true);
        assert.equal(spy.last, true);

        spy.lock.set('nav', false);
        assert.equal(spy.last, false);
    });

    test('y no pisa la llave de otro', () => {
        const spy = lockWithSpy();

        spy.lock.lock('sidecart');
        spy.lock.set('nav', true);
        spy.lock.set('nav', false);

        assert.equal(spy.last, true, 'el cajón conserva la suya');
    });
});
