import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { DEFAULT_ZONE, GUEST_ZONES, HOME_ENTRIES, ZONES, ZONE_PARENTS, ZONE_TITLE_KEYS, bringsOwnHeading, createNavigation, isGuestZone, isZone, titleKeyOf, zoneFor } from './navigation.js';
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

describe('el índice', () => {
    /**
     * ⚠️⚠️ **Toda zona tiene que ser ALCANZABLE, y hasta el paso 8 nadie lo miraba.**
     *
     * Declarar una zona en `ZONES` y olvidarla en su puerta la deja **inalcanzable** —código muerto
     * que se pinta perfecto en un test de navegación y al que ningún cliente llega—. Es la misma
     * familia que los 20 iconos vacíos (`#113`) y que el enlace profundo de `#117`: algo que «no
     * falla y no hace nada».
     *
     * ⚠️ **La regla CAMBIÓ DE FORMA el 2026-08-23, y no se relajó** (`specs/auth-en-cajon.md` §4.1).
     * Antes decía «toda zona está en `HOME_ENTRIES`», porque el índice era la única puerta. Con las
     * tres zonas de auth hay **dos** puertas: el índice para quien tiene sesión, y las rutas de
     * `Http\Sidebar\AccountDoor` para quien no. Lo que se exige es que **toda zona esté en una de las
     * dos listas** — no que haya una excepción escrita a mano, que es donde se acaba metiendo
     * cualquier cosa.
     */
    test('TODAS las zonas se pueden alcanzar por alguna de las TRES puertas', () => {
        for (const zona of Object.values(ZONES)) {
            if (zona === DEFAULT_ZONE) continue;

            assert.ok(
                HOME_ENTRIES.includes(zona) || GUEST_ZONES.includes(zona) || zona in ZONE_PARENTS,
                `a la zona «${zona}» no se llega desde ningún sitio: ni el índice, ni una puerta de invitado, ni otra zona`
            );
        }
    });

    /**
     * ⚠️⚠️ **La tercera puerta no puede ser una excepción encubierta** (2026-08-23,
     * `specs/mis-reservas-por-reserva.md` §4.3). Declarar «esta zona cuelga de aquella» solo vale si
     * el padre EXISTE y es alcanzable a su vez; si no, la lista se convierte en el sitio donde
     * esconder una zona muerta, que es exactamente lo que la regla lleva dos versiones evitando.
     */
    test('y una zona que cuelga de otra tiene un padre REAL y alcanzable', () => {
        for (const [zona, padre] of Object.entries(ZONE_PARENTS)) {
            assert.ok(isZone(zona), `«${zona}» está declarada con padre pero no es una zona`);
            assert.ok(isZone(padre), `el padre de «${zona}» («${padre}») no es una zona`);
            assert.notEqual(padre, zona, `«${zona}» se declara padre de sí misma`);

            assert.ok(
                HOME_ENTRIES.includes(padre) || GUEST_ZONES.includes(padre) || padre === DEFAULT_ZONE,
                `«${zona}» cuelga de «${padre}», que a su vez no se alcanza desde ninguna puerta: la cadena no llega a ningún sitio`
            );
        }
    });

    /** Y no está a la vez colgada de otra zona y en una puerta: dos caminos, dos sitios que mantener. */
    test('una zona con padre no está ADEMÁS en el índice ni entre las de invitado', () => {
        for (const zona of Object.keys(ZONE_PARENTS)) {
            assert.equal(HOME_ENTRIES.includes(zona), false, `«${zona}» está en el índice Y colgada de otra zona`);
            assert.equal(GUEST_ZONES.includes(zona), false, `«${zona}» es de invitado Y cuelga de otra zona`);
        }
    });

    /**
     * ⚠️ **Y las dos listas no se solapan.** El índice solo lo ve quien tiene sesión: ofrecerle ahí
     * «identifícate» sería enseñarle un formulario de entrar a alguien que ya entró. Al revés vale lo
     * mismo — una zona de cuenta en la lista de invitado pediría datos que no existen.
     */
    test('ninguna zona es a la vez del índice y de invitado', () => {
        for (const zona of GUEST_ZONES) {
            assert.equal(HOME_ENTRIES.includes(zona), false, `«${zona}» está en las dos listas`);
            assert.equal(isGuestZone(zona), true);
        }

        for (const entrada of HOME_ENTRIES) {
            assert.equal(isGuestZone(entrada), false, `el índice ofrece «${entrada}», que es de invitado`);
        }
    });

    /** Y son zonas de verdad, no cadenas sueltas que nadie sabría pintar. */
    test('las zonas de invitado existen y tienen rótulo propio', () => {
        for (const zona of GUEST_ZONES) {
            assert.equal(isZone(zona), true, `«${zona}» no está declarada en ZONES`);
            assert.notEqual(
                titleKeyOf(zona), ZONE_TITLE_KEYS[DEFAULT_ZONE],
                `«${zona}» cae en el rótulo del índice: se pintaría con el título de otra pantalla`
            );
        }
    });

    test('y el índice no ofrece nada que no sea una zona, ni un enlace a sí mismo', () => {
        for (const entrada of HOME_ENTRIES) {
            assert.equal(isZone(entrada), true, `el índice ofrece «${entrada}», que no es una zona`);
        }

        assert.equal(HOME_ENTRIES.includes(DEFAULT_ZONE), false, 'el índice se enlaza a sí mismo');
        assert.equal(new Set(HOME_ENTRIES).size, HOME_ENTRIES.length, 'el índice repite una entrada');
    });
});

describe('las zonas retiradas y «volver» desde fuera', () => {
    /**
     * ⚠️⚠️ **Cambiar y recuperar la contraseña ya NO son zonas** (A4b de `acceso-con-codigo.md` §4.11, `#813`): en el cajón
     * ya nadie entra con ella. Con recuperar se fue la única pantalla que sembraba «entrar» debajo (`#125`).
     */
    test('cambiar y recuperar la contraseña ya no son zonas', () => {
        assert.equal('PASSWORD' in ZONES, false);
        assert.equal('FORGOT' in ZONES, false);
        assert.equal(isZone('password'), false);
        assert.equal(isZone('forgot'), false);
        assert.equal(HOME_ENTRIES.includes('password'), false, 'el índice ofrecería una pantalla que no existe');
    });

    /**
     * ⚠️⚠️ **Crear cuenta ya NO es una zona** (A4a de `acceso-con-codigo.md` §4.11, `#849`): es una cara de la puerta, a la
     * que se llega cuando el correo es nuevo. Antes era la otra pestaña de entrar (`DECISIONES #125`), y ya entonces no se
     * sembraba: «volver» desde ella no puede cambiar de cara, sale. Una URL vieja con esa zona no abre nada inventado.
     */
    test('crear cuenta ya no es una zona: es una cara de la puerta', () => {
        assert.equal('REGISTER' in ZONES, false);
        assert.equal(isZone('register'), false);
        assert.equal(isGuestZone('register'), false);
    });

    /**
     * ⚠️⚠️ **Pero su NOMBRE sigue llegando desde fuera, y lleva a la PUERTA**: los cinco CTA de alta de la landing piden
     * `register` (`AccountDoorWiringTest`), y `openAccount(zona)` es API del paquete. Sin el alias caerían en el índice,
     * que un invitado ve con los rótulos en blanco. Lo demás pasa tal cual (una zona que no existe la resuelve `reset()`).
     */
    test('el nombre de una zona retirada lleva a su sucesora, y el resto pasa tal cual', () => {
        assert.equal(zoneFor('register'), ZONES.LOGIN);
        assert.equal(zoneFor('forgot'), ZONES.LOGIN, 'recuperar, a la puerta: un invitado no ve el índice en blanco');
        assert.equal(zoneFor(ZONES.ORDERS), ZONES.ORDERS);
        assert.equal(zoneFor('no-existe'), 'no-existe');
    });

    /** ⚠️ Desde fuera no queda nada debajo: «volver» NO se mueve dentro del área, y eso es lo que la hace salir. */
    test('entrar de fuera deja la pila con una sola zona, y «volver» significa SALIR', () => {
        const nav = createNavigation();
        nav.go(ZONES.ORDERS);
        nav.reset(ZONES.LOGIN);

        assert.deepEqual(nav.trail, [ZONES.LOGIN]);
        assert.equal(nav.canBack, false);
        assert.equal(nav.back(), false, '`false` es lo que el store traduce en salir a la compra');
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

describe('el encabezado propio de una zona', () => {
    /**
     * ⚠️⚠️ Nace de que el título salía DOS VECES: las tres pantallas de auth reutilizan el formulario
     * del paso 5 del embudo —que trae su propio `auth__title` porque allí no hay armazón— y la
     * sección ponía además el de la zona, con el MISMO literal.
     */
    test('lo traen exactamente las pantallas de auth: la puerta y el alta de Google', () => {
        assert.equal(bringsOwnHeading(ZONES.LOGIN), true);
        assert.equal(bringsOwnHeading(ZONES.GOOGLE_SIGNUP), true);
    });

    /** Y ninguna otra: las zonas con sesión dependen del armazón para tener título. */
    test('ninguna zona con sesión lo trae', () => {
        for (const zone of HOME_ENTRIES.concat(ZONES.HOME)) {
            assert.equal(bringsOwnHeading(zone), false, `${zone} se quedaría SIN título`);
        }
    });

    /**
     * ⚠️ **`titleKeyOf()` sigue devolviendo su clave para las dos**, y a propósito: el rótulo se usa
     * en más sitios que el encabezado. Atar las dos cosas dejaría sin nombre a quien solo quiere el
     * rótulo.
     */
    test('pero su rótulo sigue existiendo', () => {
        for (const zone of [ZONES.LOGIN, ZONES.GOOGLE_SIGNUP]) {
            assert.ok(titleKeyOf(zone), `${zone} perdió su clave de rótulo`);
        }
    });

    /** Una zona que no existe no trae encabezado propio: el armazón pone el suyo por defecto. */
    test('una zona desconocida no lo trae', () => {
        assert.equal(bringsOwnHeading('lo-que-sea'), false);
    });
});
