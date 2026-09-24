import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { CAJON, ISLA, carcasaDe, superficieDe } from './carcasa.js';

/**
 * La carcasa de la compra (`DECISIONES #682`, T3e·2 de `specs/isla-y-landing-nueva.md` §4.10): qué dice el
 * arranque y en qué superficie se abre cada cosa.
 */
describe('la carcasa que dice el arranque', () => {
    test('la isla, solo si el arranque la nombra', () => {
        assert.equal(carcasaDe({ shell: 'isla' }), ISLA);
        assert.equal(carcasaDe({ shell: 'cajon' }), CAJON);
    });

    test('un arranque sin carcasa, con una desconocida o sin arranque es el cajón: la conducta de siempre', () => {
        assert.equal(carcasaDe({}), CAJON);
        assert.equal(carcasaDe({ shell: 'lateral' }), CAJON);
        assert.equal(carcasaDe({ shell: 'ISLA' }), CAJON);
        assert.equal(carcasaDe(null), CAJON);
        assert.equal(carcasaDe(undefined), CAJON);
    });
});

describe('dónde se abre cada cosa', () => {
    test('con la isla, la compra se abre en la isla y la cuenta en el lateral (hasta la T5)', () => {
        assert.equal(superficieDe(ISLA), ISLA);
        assert.equal(superficieDe(ISLA, { cuenta: true }), CAJON);
    });

    test('con el cajón, todo en el lateral', () => {
        assert.equal(superficieDe(CAJON), CAJON);
        assert.equal(superficieDe(CAJON, { cuenta: true }), CAJON);
    });

    test('una carcasa desconocida no abre una superficie que no existe', () => {
        assert.equal(superficieDe('lateral'), CAJON);
        assert.equal(superficieDe(undefined), CAJON);
    });
});
