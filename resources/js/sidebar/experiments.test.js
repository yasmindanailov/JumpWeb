import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { CONTROL, createExperiments, variantOf } from './experiments.js';

/**
 * T5a de la analítica — los experimentos en el motor (`docs/specs/analitica.md` §4.4): la variante la trae el
 * arranque; aquí solo se lee y se cuenta la exposición, una vez y solo cuando hay variante asignada.
 */
const boot = { experiments: { shell: 'isla', precio: 'b' } };

describe('la variante', () => {
    test('es la que asignó el servidor', () => {
        assert.equal(variantOf(boot, 'shell'), 'isla');
        assert.equal(variantOf(boot, 'precio'), 'b');
    });

    test('sin experimento vivo cae al valor por defecto, y el por defecto se puede elegir', () => {
        assert.equal(variantOf(boot, 'otro'), CONTROL);
        assert.equal(variantOf(boot, 'otro', 'cajon'), 'cajon');
        assert.equal(variantOf({}, 'shell'), CONTROL);
        assert.equal(variantOf(null, 'shell'), CONTROL);
        assert.equal(variantOf({ experiments: { shell: '' } }, 'shell'), CONTROL, 'una variante vacía no es una variante');
        assert.equal(variantOf({ experiments: { shell: 3 } }, 'shell'), CONTROL, 'ni un número');
    });
});

describe('la exposición', () => {
    test('se cuenta UNA vez por carga, con la clave y la variante', () => {
        const tracked = [];
        const experiments = createExperiments({ boot, track: (name, props) => tracked.push([name, props]) });

        assert.equal(experiments.expose('shell'), true);
        assert.equal(experiments.expose('shell'), false, 'la segunda vez no cuenta');
        assert.deepEqual(tracked, [['experiment_exposed', { key: 'shell', variant: 'isla' }]]);
        assert.deepEqual(experiments.exposed(), ['shell']);
    });

    test('sin variante asignada no hay exposición: el valor por defecto no es enseñar algo distinto', () => {
        const tracked = [];
        const experiments = createExperiments({ boot: {}, track: (name, props) => tracked.push([name, props]) });

        assert.equal(experiments.expose('shell'), false);
        assert.deepEqual(tracked, []);
        assert.deepEqual(experiments.exposed(), []);
    });

    test('sin tracker no rompe: la analítica que no llega no es un fallo del cajón', () => {
        const experiments = createExperiments({ boot });

        assert.equal(experiments.expose('shell'), true);
        assert.equal(experiments.variant('shell'), 'isla');
    });
});
