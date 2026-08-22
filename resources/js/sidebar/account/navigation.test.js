import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { DEFAULT_ZONE, ZONES, ZONE_TITLE_KEYS, createNavigation, isZone, titleKeyOf } from './navigation.js';
import { FUNNEL_STEPS, FUNNEL_TRANSITIONS } from '../machine.js';

/**
 * La red del modelo de navegación del área de cliente (`docs/specs/area-cliente.md` §3.1).
 *
 * ⚠️ Los casos frontera se eligen **por el MECANISMO del fallo, no por el síntoma** (`DECISIONES
 * #68`): lo que puede romperse aquí no es «ir a una zona» —eso es una asignación— sino **que la pila
 * crezca sin límite** y deje «volver» inservible, y **que la salida del área se confunda con un
 * error**. Los dos tienen caso propio.
 */

describe('las zonas', () => {
    test('quien entra sin pedir nada aterriza en el índice', () => {
        assert.equal(DEFAULT_ZONE, ZONES.HOME);
        assert.equal(createNavigation().zone, ZONES.HOME);
    });

    test('reconoce las que existen y rechaza las que no', () => {
        assert.equal(isZone(ZONES.HOME), true);
        assert.equal(isZone(ZONES.ORDERS), true);
        assert.equal(isZone('perfil'), false, 'las zonas de la tanda 2 NO están declaradas todavía');
        assert.equal(isZone(undefined), false);
    });

    test('⚠️ NO es un embudo: no comparte modelo con el grafo de la compra', () => {
        // La guarda de `machine.test.js` mira que el embudo no crezca; ésta mira el otro lado, que es
        // el que este módulo existe para impedir: que las zonas de cuenta acaben en aquel mapa.
        for (const zona of Object.values(ZONES)) {
            assert.equal(FUNNEL_STEPS.includes(zona), false, `la zona «${zona}» se ha colado en el embudo`);
            assert.equal(Object.keys(FUNNEL_TRANSITIONS).includes(zona), false);
        }
    });
});

describe('el rótulo de cada zona', () => {
    test('⚠️ TODAS las zonas tienen rótulo declarado, y por eso ninguna sale con el título vacío', () => {
        // La trampa que esto cierra: `i18n.js` devuelve `''` cuando falta una clave —en producción un
        // texto ausente no puede tumbar el cajón—, así que una zona sin entrada aquí se pintaría con
        // el título EN BLANCO y nada avisaría. Es la misma familia que los 20 iconos vacíos (`#113`).
        for (const zona of Object.values(ZONES)) {
            assert.ok(ZONE_TITLE_KEYS[zona], `la zona «${zona}» no tiene rótulo: se pintaría vacía`);
            assert.notEqual(titleKeyOf(zona), '', `la zona «${zona}» resuelve a camino vacío`);
        }
    });

    test('una zona desconocida cae en el rótulo del índice, nunca en cadena vacía', () => {
        assert.equal(titleKeyOf('ajustes'), ZONE_TITLE_KEYS[DEFAULT_ZONE]);
        assert.equal(titleKeyOf(undefined), ZONE_TITLE_KEYS[DEFAULT_ZONE]);
    });
});

describe('la entrada al área', () => {
    test('se puede entrar DIRECTO a una zona, y entonces «volver» ya significa salir', () => {
        // El caso real: pulsar «Ver mis reservas» no debe aterrizar en un índice que nadie visitó.
        const nav = createNavigation({ zone: ZONES.ORDERS });

        assert.equal(nav.zone, ZONES.ORDERS);
        assert.equal(nav.canBack, false, 'no hay historia que inventar');
    });

    test('una zona de entrada inventada degrada al índice, no deja el área en blanco', () => {
        assert.equal(createNavigation({ zone: 'ajustes' }).zone, ZONES.HOME);
    });
});

describe('la pila de retorno', () => {
    test('ir y volver', () => {
        const nav = createNavigation();

        assert.equal(nav.go(ZONES.ORDERS), true);
        assert.deepEqual(nav.trail, [ZONES.HOME, ZONES.ORDERS]);
        assert.equal(nav.canBack, true);

        assert.equal(nav.back(), true);
        assert.equal(nav.zone, ZONES.HOME);
        assert.equal(nav.canBack, false);
    });

    test('⚠️⚠️ alternar entre dos zonas NO hace crecer la pila', () => {
        const nav = createNavigation();

        // Quince idas y venidas. Con una pila ingenua quedarían quince entradas y el cliente tendría
        // que pulsar «volver» quince veces para salir de un área que solo tiene dos pantallas.
        for (let i = 0; i < 15; i++) {
            nav.go(ZONES.ORDERS);
            nav.go(ZONES.HOME);
        }

        assert.deepEqual(nav.trail, [ZONES.HOME], 'la pila se recorta al volver a una zona ya visitada');
        assert.equal(nav.canBack, false, 'y «volver» sigue significando salir, que es lo correcto');
    });

    test('volver a una zona ya visitada recorta hasta ella (miga de pan)', () => {
        const nav = createNavigation({ zone: ZONES.ORDERS });

        nav.go(ZONES.HOME);
        assert.deepEqual(nav.trail, [ZONES.ORDERS, ZONES.HOME]);

        nav.go(ZONES.ORDERS);
        assert.deepEqual(nav.trail, [ZONES.ORDERS], 'no se apila una tercera vez');
    });

    test('la pila está ACOTADA por el número de zonas, pase lo que pase', () => {
        const nav = createNavigation();
        const zonas = Object.values(ZONES);

        for (let i = 0; i < 200; i++) nav.go(zonas[i % zonas.length]);

        assert.ok(nav.trail.length <= zonas.length, `la pila creció a ${nav.trail.length}`);
    });

    test('pedir la zona en la que ya se está no cuenta como movimiento', () => {
        const nav = createNavigation();

        assert.equal(nav.go(ZONES.HOME), false);
        assert.deepEqual(nav.trail, [ZONES.HOME]);
    });

    test('una zona inventada se rechaza y no toca la historia', () => {
        const nav = createNavigation();

        assert.equal(nav.go('ajustes'), false);
        assert.deepEqual(nav.trail, [ZONES.HOME]);
    });

    test('⚠️ `back()` en la raíz devuelve false, y eso NO es un error: es «sal del área»', () => {
        const nav = createNavigation();

        assert.equal(nav.back(), false);
        assert.equal(nav.zone, ZONES.HOME, 'y la zona no se mueve: el área nunca queda sin pantalla');
    });

    test('la historia que se lee es una COPIA: quien la mire no puede romperla', () => {
        const nav = createNavigation();
        nav.go(ZONES.ORDERS);

        const leida = nav.trail;
        leida.push('inventada');
        leida.length = 0;

        assert.deepEqual(nav.trail, [ZONES.HOME, ZONES.ORDERS]);
    });
});

describe('salir y volver a entrar', () => {
    test('la historia se vacía: no se arrastra el recorrido de la visita anterior', () => {
        const nav = createNavigation();

        nav.go(ZONES.ORDERS);
        nav.reset();

        assert.deepEqual(nav.trail, [ZONES.HOME]);
        assert.equal(nav.canBack, false);
    });

    test('se puede reentrar directo a una zona', () => {
        const nav = createNavigation();

        nav.reset(ZONES.ORDERS);

        assert.deepEqual(nav.trail, [ZONES.ORDERS]);
    });

    test('reentrar a una zona inventada degrada al índice', () => {
        const nav = createNavigation();

        nav.reset('ajustes');

        assert.equal(nav.zone, ZONES.HOME);
    });
});
