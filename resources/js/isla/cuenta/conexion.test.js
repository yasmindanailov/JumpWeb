import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { intentar, sinRed } from './conexion.js';

/** Sin conexión en Mi cuenta (T5f, spec §4.13): se avisa y lo que guarda deja reintentar. */

describe('sin conexión', () => {
    test('solo `onLine === false` dice que no hay red; sin navegador, o sin el dato, se intenta', () => {
        assert.equal(sinRed({ onLine: false }), true);
        assert.equal(sinRed({ onLine: true }), false);
        assert.equal(sinRed({}), false);
        assert.equal(sinRed(null), false);
    });

    test('sin red, no se intenta y queda el reintento; con red, el reintento lo hace y retira el fallo', () => {
        const nav = { onLine: false };
        const hechos = [];
        let fallo = null;
        const guardar = intentar((x) => { hechos.push(x); return 'ok'; }, { nav, fallar: (r) => { fallo = r; }, limpiar: () => { fallo = null; } });

        assert.equal(guardar('a'), undefined);
        assert.deepEqual(hechos, []);
        assert.equal(typeof fallo, 'function');

        // Reintentar sin red vuelve a decirlo, con su reintento.
        const primero = fallo;
        primero();
        assert.deepEqual(hechos, []);
        assert.notEqual(fallo, null);

        nav.onLine = true;
        assert.equal(fallo(), 'ok');
        assert.deepEqual(hechos, ['a']);
        assert.equal(fallo, null);
    });

    test('con red, lo hace a la primera y retira el fallo que hubiera', () => {
        let fallo = () => {};
        const guardar = intentar(() => 'hecho', { nav: { onLine: true }, fallar: (r) => { fallo = r; }, limpiar: () => { fallo = null; } });

        assert.equal(guardar(), 'hecho');
        assert.equal(fallo, null);
    });
});
