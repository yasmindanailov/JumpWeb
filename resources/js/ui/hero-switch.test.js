import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { INICIAL, siguiente, rearranca, installHeroSwitch } from './hero-switch.js';

/**
 * La red del rearranque del interruptor del titular (`DECISIONES #280`).
 *
 * ⚠️ **Lo que se prueba aquí no se ve en ningún HTML ni en ninguna captura**: depende de una
 * SECUENCIA de entradas del `IntersectionObserver`. El defecto que motiva el módulo es de esa
 * clase — una animación que se acaba de acotar para que no fuera un tic, volviendo a serlo porque
 * el observador dispara en el filo.
 */

/** Recorre una secuencia de visibilidades y devuelve en qué pasos habría rearrancado. */
const recorre = (visibilidades) => {
    let estado = INICIAL;

    return visibilidades.map((visible) => {
        estado = siguiente(estado, visible);

        return estado.rearranca;
    });
};

describe('cuándo toca volver a saltar', () => {
    test('al cargar con el hero a la vista NO se rearranca: el CSS ya está animando', () => {
        assert.deepEqual(recorre([true]), [false]);
    });

    test('hay que haber salido del todo antes de volver', () => {
        assert.deepEqual(recorre([true, false, true]), [false, false, true]);
    });

    test('una vez gastado no se repite hasta volver a salir', () => {
        assert.deepEqual(
            recorre([true, false, true, true, true]), [false, false, true, false, false],
            'entrar dos veces seguidas sin haber salido no es volver: es el filo del observador',
        );
    });

    test('varias salidas seguidas siguen valiendo por una', () => {
        assert.deepEqual(recorre([false, false, false, true]), [false, false, false, true]);
    });

    test('la página que carga YA desplazada por debajo del hero rearranca al subir', () => {
        assert.deepEqual(
            recorre([false, true]), [false, true],
            'si el hero nunca se vio, los ciclos que el CSS gastó al cargar no los vio nadie',
        );
    });

    test('ir y volver dos veces rearranca dos veces', () => {
        assert.deepEqual(recorre([true, false, true, false, true]), [false, false, true, false, true]);
    });
});

describe('el rearranque descarta y recrea', () => {
    /** Una pieza de mentira que apunta cada escritura de `animation-name` y cada lectura forzada. */
    const pieza = () => {
        const traza = [];

        return {
            traza,
            style: { set animationName(v) { traza.push(`name=${v === '' ? '(vacío)' : v}`); } },
            get offsetWidth() { traza.push('reflow'); return 44; },
        };
    };

    test('apaga las tres, fuerza el recálculo y las devuelve a la cascada', () => {
        const piezas = [pieza(), pieza(), pieza()];

        rearranca(piezas);

        assert.deepEqual(piezas[0].traza, ['name=none', 'reflow', 'name=(vacío)'],
            '⚠️ Sin la lectura EN MEDIO el navegador agrupa las dos escrituras, ve el valor final '
            + 'y concluye que `animation-name` no ha cambiado: no recrea nada.');
        assert.deepEqual(piezas[1].traza, ['name=none', 'name=(vacío)']);
        assert.deepEqual(piezas[2].traza, ['name=none', 'name=(vacío)']);
    });

    test('las tres se apagan ANTES de que ninguna vuelva', () => {
        const orden = [];
        const marca = (i) => ({
            style: { set animationName(v) { orden.push(`${i}:${v === '' ? 'vuelve' : v}`); } },
            get offsetWidth() { orden.push('reflow'); return 44; },
        });

        rearranca([marca(0), marca(1), marca(2)]);

        assert.deepEqual(orden, ['0:none', '1:none', '2:none', 'reflow', '0:vuelve', '1:vuelve', '2:vuelve'],
            'apagar y encender pieza a pieza dejaría a cada una arrancando en un instante distinto, '
            + 'y el desfase se vería: las tres cuentan la misma historia.');
    });

    test('sin piezas no toca nada y no revienta', () => {
        assert.doesNotThrow(() => rearranca([]));
    });
});

describe('la instalación no se impone donde no hay pieza', () => {
    /** Una raíz con envoltorio cuyo interior lo decide cada caso. */
    const raizCon = (dentro) => ({
        querySelector: (q) => (q === '.hero__switch' ? { querySelectorAll: () => dentro } : null),
    });

    /** Presta un `IntersectionObserver` de mentira para que la puerta del navegador no decida. */
    const conObservador = (fn) => {
        const previo = globalThis.IntersectionObserver;
        globalThis.IntersectionObserver = class { observe() {} disconnect() {} };

        try {
            return fn();
        } finally {
            if (previo === undefined) {
                delete globalThis.IntersectionObserver;
            } else {
                globalThis.IntersectionObserver = previo;
            }
        }
    };

    test('sin interruptor en la vista devuelve null', () => {
        assert.equal(
            conObservador(() => installHeroSwitch({ raiz: { querySelector: () => null } })), null,
            'son diez de las doce vistas: el módulo no puede exigir un hero que no existe',
        );
    });

    test('con envoltorio pero sin piezas dentro, tampoco', () => {
        assert.equal(
            conObservador(() => installHeroSwitch({ raiz: raizCon([]) })), null,
            '⚠️ Con el observador prestado, la ÚNICA razón posible del `null` es que no hay piezas: '
            + 'sin prestarlo este caso saldría verde por la puerta del navegador antiguo.',
        );
    });

    test('con piezas y observador, se engancha y se puede soltar', () => {
        const instalado = conObservador(() => installHeroSwitch({ raiz: raizCon([{}, {}, {}]) }));

        assert.notEqual(instalado, null, 'el caso de CONTROL: si esto también diera null, los dos de '
            + 'arriba no demostrarían nada');
        assert.equal(typeof instalado.desconecta, 'function');
    });

    test('sin `IntersectionObserver` en el navegador se retira sin tocar nada', () => {
        assert.equal(installHeroSwitch({ raiz: raizCon([{}, {}, {}]) }), null,
            'la pieza ya está completa sin esto: anima al cargar y descansa encendida');
    });
});
