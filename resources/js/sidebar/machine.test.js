import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { STEPS, canGo, createMachine, isIdentifying, modeOf, stepForOutcome } from './machine.js';

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

    test('un pago denegado ofrece reintentar, y también empezar de nuevo', () => {
        assert.ok(canGo(STEPS.DECLINED, STEPS.PAY));
        assert.ok(canGo(STEPS.DECLINED, STEPS.CATALOG));
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
});
