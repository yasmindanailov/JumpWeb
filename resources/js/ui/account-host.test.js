import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import { PENDING, reveal, takeOver } from './account-host.js';

/**
 * La red del dueño del hueco (`docs/specs/account-context-vue.md` §4.8).
 *
 * ⚠️ El DOM se dobla a mano en vez de montar un navegador: estas dos funciones tocan **dos cosas** de
 * un elemento —su contenido y una clase—, así que un doble con `textContent` y `classList` prueba
 * exactamente lo que hacen. El patrón es el de `sidebar/account/privacy.js`.
 */
function fakeHost({ pending = true, floor = '<form>salir</form>' } = {}) {
    const classes = new Set(['acct', ...(pending ? [PENDING] : [])]);

    return {
        textContent: floor,
        classList: {
            remove: (c) => classes.delete(c),
            contains: (c) => classes.has(c),
        },
        get pending() {
            return classes.has(PENDING);
        },
    };
}

describe('el hueco del bloque de cuenta', () => {
    describe('cuando el motor se hace cargo', () => {
        test('vacía el suelo servido y expande el hueco', () => {
            const host = fakeHost();

            assert.equal(takeOver(host), true);
            assert.equal(host.textContent, '', 'el suelo tiene que irse: si no, se ven DOS botones de salir');
            assert.equal(host.pending, false, 'y el hueco tiene que expandirse, o el bloque no se ve');
        });

        /** Sin hueco no hay nada que hacer, y no revienta: el motor puede montarse en otro contexto. */
        test('sin hueco no hace nada y lo dice', () => {
            assert.equal(takeOver(null), false);
            assert.equal(takeOver(undefined), false);
        });
    });

    describe('cuando el motor no llega', () => {
        /**
         * ⚠️⚠️ **Conservar el contenido es el punto entero.** Si `reveal()` vaciara «por simetría»,
         * un cliente con la red caída se quedaría **sin ninguna forma de cerrar sesión**: medido, este
         * suelo es el único sitio de la aplicación que sirve `route('logout')` en HTML.
         */
        test('expande el hueco SIN tocar el suelo servido', () => {
            const host = fakeHost();

            assert.equal(reveal(host), true);
            assert.equal(host.textContent, '<form>salir</form>', 'el suelo es lo único que le queda al cliente');
            assert.equal(host.pending, false);
        });

        test('sin hueco no hace nada y lo dice', () => {
            assert.equal(reveal(null), false);
        });
    });

    /** Los dos caminos son idempotentes: llamarlos dos veces no empeora nada. */
    test('llamarlos dos veces no cambia el resultado', () => {
        const host = fakeHost();

        takeOver(host);
        takeOver(host);

        assert.equal(host.textContent, '');
        assert.equal(host.pending, false);
    });
});
