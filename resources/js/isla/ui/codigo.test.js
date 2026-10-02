import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { estiloCasilla } from './codigo.js';

/** Las casillas del código de la isla (Z6g·1): lo que decide cómo se pinta cada una. */
describe('una casilla del código', () => {
    test('en reposo y vacía: el borde suave, sin anillo', () => {
        const s = estiloCasilla({ activa: false, llena: false, error: false });

        assert.equal(s.border, '1px solid var(--control-border)');
        assert.equal(s.boxShadow, 'none');
    });

    test('la del cursor lleva el anillo y el borde fuerte; las escritas, solo el borde', () => {
        assert.equal(estiloCasilla({ activa: true, llena: false, error: false }).boxShadow, 'var(--ring)');
        assert.equal(estiloCasilla({ activa: true, llena: false, error: false }).border, '1px solid var(--control-border-strong)');

        const escrita = estiloCasilla({ activa: false, llena: true, error: false });
        assert.equal(escrita.border, '1px solid var(--control-border-strong)');
        assert.equal(escrita.boxShadow, 'none');
    });

    test('un «no» pinta el borde de peligro también en la del cursor y en las escritas', () => {
        for (const e of [{ activa: true, llena: false }, { activa: false, llena: true }, { activa: false, llena: false }]) {
            assert.equal(estiloCasilla({ ...e, error: true }).border, '1px solid var(--border-danger)', JSON.stringify(e));
        }
    });

    test('las cifras, en la monoespaciada y con números tabulares: el código no baila al escribirlo', () => {
        const s = estiloCasilla({ activa: false, llena: true, error: false });

        assert.equal(s.fontFamily, 'var(--font-mono)');
        assert.equal(s.fontVariantNumeric, 'tabular-nums');
    });
});
