import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { createMachine, STEPS } from './machine.js';
import { usePurchaseStore } from './store.js';

/**
 * La red del STORE, y nace de un fallo REAL que estuvo a punto de irse a producción.
 *
 * ⚠️ **El store no observa la máquina: la COPIA.** `step` se refresca en `boot()`, `go()` y `enter()`,
 * y en ningún sitio más. Una llamada DIRECTA a la máquina después de arrancar el store lo deja
 * desincronizado —la máquina en un paso y el store en otro— y **Vue pinta desde el store**.
 *
 * Eso es exactamente lo que pasaba con la vuelta de la pasarela: `index.js` aplicaba el desenlace
 * con `machine.enterOutcome()` DESPUÉS de `store.boot()`, así que quien volvía de pagar veía **el
 * catálogo** en vez de su reserva. La Fase 4 entera —las tres pantallas de desenlace— era invisible
 * en producción, y ningún test lo veía: la máquina se prueba sola, los componentes se montan con
 * props, y nadie ejecutaba la secuencia de montaje. Lo encontró el extremo a extremo con navegador.
 *
 * Pinia se puede usar aquí sin montar nada: un store fuera de componente solo necesita una instancia
 * activa. Por eso esta red SÍ es barata, y por eso no existía excusa para no tenerla.
 */

/** Reproduce EXACTAMENTE lo que hace `index.js` al montar. */
function mountSequence(outcome) {
    const pinia = createPinia();
    setActivePinia(pinia);

    const machine = createMachine();

    // El orden es el contrato: el desenlace se aplica ANTES de arrancar el store.
    if (outcome) machine.enterOutcome(outcome);

    const store = usePurchaseStore(pinia);
    store.boot(machine);

    return { machine, store };
}

describe('el store refleja la máquina', () => {
    test('sin desenlace, el cajón abre en el catálogo', () => {
        const { store } = mountSequence(null);

        assert.equal(store.step, STEPS.CATALOG);
        assert.equal(store.mode, 'catalog');
    });

    /**
     * ⚠️ **EL CASO QUE FALTABA.** Los tres desenlaces tienen que llegar al STORE, no solo a la máquina:
     * si `step` se queda en el catálogo, el cliente que acaba de pagar ve la lista de productos.
     */
    test('cada desenlace de la pasarela deja al STORE en su paso', () => {
        for (const [outcome, step] of [['confirmed', STEPS.CONFIRMED], ['failed', STEPS.DECLINED], ['verifying', STEPS.VERIFYING]]) {
            const { machine, store } = mountSequence(outcome);

            assert.equal(machine.step, step, `la máquina debería estar en ${step} con «${outcome}»`);
            assert.equal(store.step, step, `y el STORE también: es de donde pinta Vue`);
            assert.equal(store.mode, 'result', 'y el modo que publica hacia fuera es «result»');
        }
    });

    test('un desenlace desconocido no mueve el cajón', () => {
        const { store } = mountSequence('lo-que-sea');

        assert.equal(store.step, STEPS.CATALOG);
    });
});

describe('las transiciones pasan por el store', () => {
    test('`go` refleja el paso cuando la transición existe, y no cuando no', () => {
        const { store } = mountSequence(null);

        assert.equal(store.go(STEPS.DATE), true);
        assert.equal(store.step, STEPS.DATE);

        assert.equal(store.go(STEPS.CONFIRMED), false, 'del calendario no se salta al desenlace');
        assert.equal(store.step, STEPS.DATE, 'y un rechazo no puede dejar el store a medias');
    });

    test('`enter` aterriza sin comprobar la transición, y el store lo refleja', () => {
        const { store } = mountSequence(null);

        store.enter(STEPS.VERIFYING);

        assert.equal(store.step, STEPS.VERIFYING);
        assert.equal(store.mode, 'result');
    });

    /**
     * ⚠️ La guarda de la guarda: **mover la máquina POR DETRÁS del store lo desincroniza**. Se fija el
     * hecho para que nadie lo descubra otra vez en producción — si algún día el store llega a observar
     * la máquina de verdad, este caso será el que lo diga.
     */
    test('tocar la máquina a espaldas del store lo deja mintiendo', () => {
        const { machine, store } = mountSequence(null);

        machine.enter(STEPS.CONFIRMED);

        assert.equal(machine.step, STEPS.CONFIRMED);
        assert.equal(store.step, STEPS.CATALOG, 'el store NO se entera: por eso el orden de `index.js` importa');
    });
});
