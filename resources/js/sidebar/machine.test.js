import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { STEPS, canGo, createMachine, isIdentifying, isOutcome, modeOf, stepForOutcome, FUNNEL_STEPS, FUNNEL_TRANSITIONS } from './machine.js';

/**
 * Fase 4 · paso 4.1 — la red de la máquina de estados del cajón (`sidebar-spa.md` §4.8, CE-6).
 *
 * Corre con el runner integrado de Node (`npm run test:js`), sin dependencias nuevas. Existe porque
 * la máquina del sidebar Livewire está cubierta hoy por seis ficheros de test: transcribirla sin red
 * sería una pérdida neta de cobertura, y **el servidor no es lo que se mueve**.
 */

describe('modo publicado hacia fuera', () => {
    /**
     * ⚠️ Estas dos señales las consume gente de FUERA del cajón —`layout.blade.php` y
     * `account-context`—, y ninguna de sus clases aparece en el marcado del sidebar. Un motor que
     * las publique mal no rompe nada visible en el cajón: rompe el panel y los botones de login.
     */
    test('cada paso publica el modo que el layout espera', () => {
        assert.equal(modeOf(STEPS.CATALOG), 'catalog');
        assert.equal(modeOf(STEPS.DATE), 'booking');
        assert.equal(modeOf(STEPS.TIME), 'booking');
        assert.equal(modeOf(STEPS.CART), 'cart');
        assert.equal(modeOf(STEPS.IDENTIFY), 'cart');
        // ⚠️ El paso de PAGO es modo `cart`. Decía `result` hasta 4.3·1 y se descubrió MIDIENDO
        // contra `Purchase::stepModeMap()` (`8 => 'cart'`), no leyendo: la clase `is-{modo}` se
        // pinta fuera del cajón, así que ningún diff de árbol podía delatarlo.
        assert.equal(modeOf(STEPS.PAY), 'cart');
        assert.equal(modeOf(STEPS.CONFIRMED), 'result');
        assert.equal(modeOf(STEPS.DECLINED), 'result');
        assert.equal(modeOf(STEPS.VERIFYING), 'result');
    });

    test('identifying es verdadero SOLO en el paso de identificación', () => {
        const identifying = Object.values(STEPS).filter((s) => isIdentifying(s));

        assert.deepEqual(identifying, [STEPS.IDENTIFY]);
    });
});

describe('transiciones', () => {
    test('el camino de compra completo es transitable', () => {
        const machine = createMachine();

        assert.equal(machine.step, STEPS.CATALOG);
        assert.ok(machine.go(STEPS.DATE));
        assert.ok(machine.go(STEPS.TIME));
        assert.ok(machine.go(STEPS.CART));
        assert.ok(machine.go(STEPS.IDENTIFY));
        assert.ok(machine.go(STEPS.PAY));
        assert.ok(machine.go(STEPS.REDIRECTING));
    });

    test('un salto que no existe se rechaza y NO mueve el paso', () => {
        const machine = createMachine();

        assert.equal(machine.go(STEPS.PAY), false, 'del catálogo no se va a pagar');
        assert.equal(machine.step, STEPS.CATALOG, 'un rechazo no puede dejar el paso a medias');
    });

    /**
     * El navegador se va a la pasarela: de ahí no se «vuelve atrás» dentro del cajón. Si hubiera
     * salida, un doble clic podría reabrir el pago sobre un pedido que ya está en curso.
     */
    test('de la redirección a la pasarela no se sale', () => {
        const machine = createMachine({ step: STEPS.REDIRECTING });

        for (const step of Object.values(STEPS)) {
            assert.equal(machine.go(step), false, `no debería poder ir a ${step}`);
        }
    });

    test('desde el pago se puede volver a la cesta (corregir antes de pagar)', () => {
        assert.ok(canGo(STEPS.PAY, STEPS.CART));
    });

    /**
     * ⚠️ **Faltó desde 4.1 hasta el 2026-08-28** (`DECISIONES #215`): la barra-carrito del catálogo
     * («Ir al carrito», `foot.js` paso 1) hacía `go(CART)` y la máquina lo rechazaba en silencio. El
     * botón era mudo tras «Volver» y tras «+ Añadir otra reserva», medido en navegador.
     */
    test('desde el catálogo con cesta se va al carrito (la barra-carrito del pie)', () => {
        const machine = createMachine();

        assert.ok(canGo(STEPS.CATALOG, STEPS.CART));
        assert.ok(machine.go(STEPS.CART));
        assert.equal(machine.step, STEPS.CART);
    });

    /**
     * ⚠️ **Las tres salidas están MEDIDAS contra `Purchase::retryPayment()`** (4.6·2), y las dos que
     * había antes estaban mal: el reintento no vuelve a la pantalla de pago —reabre el cobro sobre un
     * pedido que ya existe y sale DIRECTO a la pasarela— y faltaba la salida a identificarse, que es lo
     * que hace el componente cuando la sesión se perdió entre la vuelta y el clic.
     */
    test('un pago denegado sale a la pasarela, al catálogo o a identificarse', () => {
        assert.ok(canGo(STEPS.DECLINED, STEPS.REDIRECTING), 'el reintento va directo a la pasarela');
        assert.ok(canGo(STEPS.DECLINED, STEPS.CATALOG), 'y `order_not_retryable` obliga a rehacer la reserva');
        assert.ok(canGo(STEPS.DECLINED, STEPS.IDENTIFY), 'y sin sesión hay que volver a identificarse');

        assert.equal(canGo(STEPS.DECLINED, STEPS.PAY), false, 'no se vuelve a confirmar lo que ya es un pedido');
    });

    /**
     * Con un terminal que no devuelve los datos firmados, el cajón abre en «verificando» y el
     * desenlace llega sondeando: tiene que poder terminar en los dos sitios.
     */
    test('verificando puede acabar confirmado o denegado', () => {
        assert.ok(canGo(STEPS.VERIFYING, STEPS.CONFIRMED));
        assert.ok(canGo(STEPS.VERIFYING, STEPS.DECLINED));
    });

    test('de la confirmación solo se sale empezando otra compra', () => {
        const machine = createMachine({ step: STEPS.CONFIRMED });

        assert.equal(machine.go(STEPS.PAY), false);
        assert.ok(machine.go(STEPS.CATALOG));
    });
});

describe('la vuelta de la pasarela', () => {
    /**
     * Aterriza SIN pasar por los pasos intermedios: el navegador se fue a otro dominio y volvió, así
     * que exigir una transición válida dejaría el cajón mudo justo cuando hay que decir en qué quedó
     * el pago.
     */
    test('cada desenlace abre el cajón en su paso', () => {
        assert.equal(stepForOutcome('confirmed'), STEPS.CONFIRMED);
        assert.equal(stepForOutcome('failed'), STEPS.DECLINED);
        assert.equal(stepForOutcome('verifying'), STEPS.VERIFYING);
    });

    /**
     * ⚠️ La precedencia que sostiene: el desenlace MANDA sobre la cesta restaurada. Se fija contra
     * `stepForOutcome` —que es quien traduce lo que escribe `Http\Sidebar\SidebarEntry`— para que los
     * dos conjuntos no puedan separarse: un desenlace nuevo que nadie añadiera aquí dejaría a quien
     * vuelve de pagar en el carrito de otra pestaña.
     */
    test('los pasos de desenlace son EXACTAMENTE los que abre la vuelta de la pasarela', () => {
        const fromOutside = ['confirmed', 'failed', 'verifying'].map(stepForOutcome);

        assert.deepEqual(fromOutside.filter(isOutcome), fromOutside);
        assert.deepEqual(
            Object.values(STEPS).filter(isOutcome).sort(),
            [...fromOutside].sort(),
            'ni de más ni de menos: un paso del embudo marcado como desenlace impediría abrir en el carrito',
        );
    });

    test('un desenlace desconocido no abre nada', () => {
        assert.equal(stepForOutcome('lo-que-sea'), null);
        assert.equal(stepForOutcome(null), null);

        const machine = createMachine();
        assert.equal(machine.enterOutcome('lo-que-sea'), false);
        assert.equal(machine.step, STEPS.CATALOG, 'un desenlace que no se entiende no mueve el cajón');
    });

    test('entrar por un desenlace no necesita transición válida', () => {
        const machine = createMachine();

        assert.ok(machine.enterOutcome('confirmed'));
        assert.equal(machine.step, STEPS.CONFIRMED);
    });
});

describe('intención de entrada', () => {
    /**
     * El entry se carga con `import()` en la PRIMERA apertura, así que la landing puede pedir «los
     * packs» antes de que el motor exista. Perder esa petición es exactamente la regresión que la
     * costura del paso 4.0a existe para evitar: con otro motor los `dispatch` no fallaban, no hacían
     * nada.
     */
    test('una intención que llega antes de estar listos se guarda y se aplica después', () => {
        const machine = createMachine();

        machine.queueIntent({ type: 'zone', slug: 'jump' });

        assert.deepEqual(machine.takeIntent(), { type: 'zone', slug: 'jump' });
    });

    test('se consume UNA sola vez: un adaptador que falle no la repite', () => {
        const machine = createMachine();

        machine.queueIntent({ type: 'packs' });

        assert.deepEqual(machine.takeIntent(), { type: 'packs' });
        assert.equal(machine.takeIntent(), null);
    });
});

describe('avisos de cambio', () => {
    test('cada movimiento avisa una vez, y un rechazo no avisa', () => {
        const seen = [];
        const machine = createMachine({ onChange: (step) => seen.push(step) });

        machine.go(STEPS.DATE);
        machine.go(STEPS.PAY);      // rechazada
        machine.enter(STEPS.CONFIRMED);

        assert.deepEqual(seen, [STEPS.DATE, STEPS.CONFIRMED]);
    });

    /** T1b de la analítica: `step_entered(from, to)` sale de aquí, y el paso anterior solo lo sabe la máquina. */
    test('el aviso lleva también el paso ANTERIOR, tanto al ir como al entrar desde fuera', () => {
        const seen = [];
        const machine = createMachine({ onChange: (step, previous) => seen.push([previous, step]) });

        machine.go(STEPS.DATE);
        machine.enterOutcome('failed');
        machine.restart();

        assert.deepEqual(seen, [[STEPS.CATALOG, STEPS.DATE], [STEPS.DATE, STEPS.DECLINED], [STEPS.DECLINED, STEPS.CATALOG]]);
    });
});

/**
 * ⚠️⚠️ **EL GRAFO DEL EMBUDO ESTÁ CERRADO, y este caso existe para que siga estándolo.**
 *
 * `DECISIONES #66` ya decidió que el cajón hospedará también el ÁREA DE CLIENTE —pedidos, reservas,
 * ajustes—, y **un área de cliente no es un embudo**: sus pantallas se navegan libremente, sin orden
 * ni vuelta atrás obligatoria. Colgarlas de `FUNNEL_TRANSITIONS` mezclaría dos modelos de navegación
 * en un mapa, y desde ese día cualquier cambio en uno obligaría a razonar sobre el otro.
 *
 * El caso no impide crecer: impide crecer POR AQUÍ. Cuando lleguen esas pantallas van en su propia
 * sección, con su propio modelo, y este mapa se queda como está.
 */
test('el grafo del embudo solo conoce los pasos del embudo', () => {
    const conocidos = new Set(FUNNEL_STEPS);

    // ⚠️ Se recorre el MAPA, no una lista de destinos que ya se sabe buenos. El primer intento de este
    // caso iteraba `FUNNEL_STEPS × FUNNEL_STEPS` y era INERTE: colar un `42` en el grafo no lo movía,
    // porque el 42 nunca llegaba a mirarse. Un test sobre un conjunto cerrado tiene que leer el
    // conjunto, no una copia de lo que se espera encontrar en él.
    for (const [desde, destinos] of Object.entries(FUNNEL_TRANSITIONS)) {
        assert.ok(conocidos.has(Number(desde)), `el grafo parte de ${desde}, que no es un paso del embudo`);

        for (const hasta of destinos) {
            assert.ok(conocidos.has(hasta), `el embudo lleva a ${hasta}, que no es un paso suyo`);
        }
    }

    assert.equal(
        Object.keys(FUNNEL_TRANSITIONS).length, FUNNEL_STEPS.length,
        'el grafo tiene que hablar de TODOS los pasos del embudo y de ninguno más',
    );

    assert.equal(FUNNEL_STEPS.length, 11, 'el embudo tiene ONCE pasos; una pantalla nueva aquí es una señal de alarma, no una feature');
    assert.equal(new Set(FUNNEL_STEPS).size, 11, 'sin repetidos');
});
