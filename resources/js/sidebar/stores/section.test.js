import { test, describe, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { SECTIONS } from '../section.js';
import { useSectionStore } from './section.js';

/** La red del store de secciones (`docs/specs/area-cliente.md` §4.1). */

describe('la sección activa', () => {
    beforeEach(() => setActivePinia(createPinia()));

    test('el cajón abre en la compra', () => {
        const store = useSectionStore();

        assert.equal(store.active, SECTIONS.PURCHASE);
        assert.equal(store.onPurchase, true);
        assert.equal(store.onAccount, false);
    });

    test('conmutar a la cuenta y volver', () => {
        const store = useSectionStore();

        assert.equal(store.showAccount(), true, 'la conmutación se reconoce');
        assert.equal(store.onAccount, true);

        assert.equal(store.showPurchase(), true);
        assert.equal(store.onPurchase, true);
    });

    test('pedir la sección en la que ya se está NO cuenta como movimiento', () => {
        const store = useSectionStore();

        // No es quisquilloso: es lo que evita repintados y avisos duplicados cuando dos sitios
        // distintos piden lo mismo (el botón de la cuenta y una intención de entrada, por ejemplo).
        assert.equal(store.showPurchase(), false);
    });

    test('⚠️ una sección inventada se rechaza y el cajón NO se queda en blanco', () => {
        const store = useSectionStore();

        assert.equal(store.show('ajustes'), false);
        assert.equal(store.active, SECTIONS.PURCHASE, 'sigue en una sección que existe');
    });
});
