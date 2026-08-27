import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { shouldHideNav, MOVEMENT_THRESHOLD, TOP_ZONE } from './nav-choreography.js';

/**
 * La red de la coreografía del armazón (`armazon-y-menu.md` §4.4, tanda 2c·2).
 *
 * ⚠️ **Lo que se prueba aquí no se ve en ningún árbol ni en ningún HTML**: depende de una
 * SECUENCIA de posiciones de scroll y del estado de un overlay. El fallo que motiva el módulo es
 * de esa clase — el armazón desapareciendo con el marcado perfecto, o quedándose puesto cuando
 * ya no toca.
 */

describe('la intención, no el evento', () => {
    test('por debajo del umbral no se decide nada', () => {
        assert.equal(
            shouldHideNav({ y: 500 + MOVEMENT_THRESHOLD - 1, previous: 500, locked: false }), null,
            'un movimiento menor que el umbral no puede mover el armazón: sería el temblor de un trackpad',
        );
        assert.equal(
            shouldHideNav({ y: 500 - (MOVEMENT_THRESHOLD - 1), previous: 500, locked: false }), null,
            'y tampoco hacia arriba',
        );
    });

    test('justo en el umbral ya cuenta', () => {
        assert.equal(shouldHideNav({ y: 500 + MOVEMENT_THRESHOLD, previous: 500 }), true);
    });
});

describe('la dirección', () => {
    test('bajando por debajo de la primera pantalla, se retira', () => {
        assert.equal(shouldHideNav({ y: TOP_ZONE + 100, previous: TOP_ZONE + 50 }), true);
    });

    test('subiendo, vuelve', () => {
        assert.equal(shouldHideNav({ y: TOP_ZONE + 50, previous: TOP_ZONE + 100 }), false);
    });
});

describe('la primera pantalla', () => {
    test('bajando pero todavía arriba, NO se retira', () => {
        assert.equal(
            shouldHideNav({ y: TOP_ZONE - 1, previous: 0 }), false,
            'en la primera pantalla el armazón se queda: es la única navegación visible',
        );
    });

    test('justo pasado el corte, ya se retira', () => {
        assert.equal(shouldHideNav({ y: TOP_ZONE + 1, previous: 0 }), true);
    });
});

describe('con un overlay abierto', () => {
    test('NUNCA se retira, aunque el gesto sea de bajar', () => {
        assert.equal(
            shouldHideNav({ y: 2000, previous: 100, locked: true }), false,
            'la hamburguesa es la forma de cerrar el menú: si se va con el scroll, el visitante '
            + 'se queda dentro sin salida visible',
        );
    });

    test('pero el umbral sigue mandando: sin intención no se toca nada', () => {
        assert.equal(shouldHideNav({ y: 102, previous: 100, locked: true }), null);
    });
});
